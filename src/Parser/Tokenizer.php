<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Xdg\Dotenv\Exception\ParseError;

final class Tokenizer implements TokenizerInterface
{
    private int $pos = -1;
    private Buffer $buffer;
    private int $quotingLevel = 0;

    private \SplStack $returnStates;

    private const EXPANSION_RX = <<<'REGEXP'
    /
        [a-zA-Z_][a-zA-Z0-9_]*
        | (?: [@*#?$!-] | \d+ ) (*MARK:special)
    /Ax
    REGEXP;

    public function __construct(
        private readonly string $input,
        public TokenizerState $state = TokenizerState::AssignmentList,
    ) {
    }

    public function getPosition(int $offset): SourcePosition
    {
        return SourcePosition::fromOffset($this->input, $offset);
    }

    public function tokenize(): \Iterator
    {
        $this->returnStates = new \SplStack();
        $this->buffer = new Buffer(0);

        ADVANCE: ++$this->pos;
        RECONSUME: $cc = $this->input[$this->pos] ?? '';
        switch ($this->state) {
            case TokenizerState::AssignmentList: {
                switch ($cc) {
                    case '':
                        yield $this->eof();
                        return null;
                    case ' ':
                    case "\t":
                    case "\n":
                        $this->pos += \strspn($this->input, " \t\n", $this->pos);
                        goto RECONSUME;
                    case '#':
                        // comment state
                        $this->pos += \strcspn($this->input, "\n", $this->pos);
                        goto RECONSUME;
                    default:
                        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)=/A', $this->input, $m, 0, $this->pos)) {
                            // assignment name state
                            yield new Token(TokenKind::Assign, $m[1], $this->pos);
                            $this->pos += \strlen($m[0]);
                            $this->state = TokenizerState::AssignmentValue;
                            $this->buffer->offset = $this->pos;
                            goto RECONSUME;
                        }
                        throw $this->unexpectedChar($cc, 'Expected whitespace, newline, a comment or an identifier');
                }
            }
            case TokenizerState::AssignmentValue: {
                switch ($cc) {
                    case '':
                        yield from $this->flushTheTemporaryBuffer();
                        yield $this->eof();
                        return null;
                    case ' ':
                    case "\t":
                    case "\n":
                        yield from $this->flushTheTemporaryBuffer();
                        $this->pos += \strspn($this->input, " \t\n", $this->pos);
                        $this->state = TokenizerState::AssignmentList;
                        goto RECONSUME;
                    case '\\':
                        $this->state = TokenizerState::AssignmentValueEscape;
                        goto ADVANCE;
                    case "'":
                        $this->returnStates->push($this->state);
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    case '"':
                        $this->returnStates->push($this->state);
                        ++$this->quotingLevel;
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case '`':
                        throw ParseError::at('Unsupported command expansion', $this->input, $this->pos);
                    case '|':
                    case '&':
                    case ';':
                    case '<':
                    case '>':
                    case '(':
                    case ')':
                        throw ParseError::at("Unescaped special character '{$cc}'", $this->input, $this->pos);
                    case '$':
                        $this->returnStates->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    default:
                        preg_match('/[^\\\\ \t\n\'"`$|&;<>()]+/A', $this->input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        goto RECONSUME;
                }
            }
            case TokenizerState::AssignmentValueEscape: {
                switch ($cc) {
                    case '':
                        $this->buffer->value .= '\\';
                        yield from $this->flushTheTemporaryBuffer();
                        yield $this->eof();
                        return null;
                    case "\n":
                        $this->state = TokenizerState::AssignmentValue;
                        goto ADVANCE;
                    default:
                        $this->buffer->value .= $cc;
                        $this->state = TokenizerState::AssignmentValue;
                        goto ADVANCE;
                }
            }
            case TokenizerState::SingleQuoted:
            SINGLE_QUOTED: {
                switch ($cc) {
                    case '':
                        // TODO: properly track the error offset
                        throw ParseError::at('Unterminated single-quoted string', $this->input, $this->buffer->offset);
                    case "'":
                        $this->state = $this->returnStates->pop();
                        goto ADVANCE;
                    default:
                        preg_match("/[^']+/A", $this->input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        $cc = $this->input[$this->pos] ?? '';
                        goto SINGLE_QUOTED;
                }
            }
            case TokenizerState::DoubleQuoted:
            DOUBLE_QUOTED: {
                switch ($cc) {
                    case '':
                        // TODO: properly track the error offset
                        throw ParseError::at('Unterminated double-quoted string', $this->input, $this->buffer->offset);
                    case '`':
                        throw ParseError::at('Unsupported command expansion', $this->input, $this->pos);
                    case '"':
                        --$this->quotingLevel;
                        $this->state = $this->returnStates->pop();
                        goto ADVANCE;
                    case '\\':
                        $this->state = TokenizerState::DoubleQuotedEscape;
                        goto ADVANCE;
                    case '$':
                        $this->returnStates->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    default:
                        preg_match('/[^\\\\"`$]+/A', $this->input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        $cc = $this->input[$this->pos] ?? '';
                        goto DOUBLE_QUOTED;
                }
            }
            case TokenizerState::DoubleQuotedEscape: {
                switch ($cc) {
                    case '':
                        // TODO: properly track the error offset
                        throw ParseError::at('Unterminated double-quoted string', $this->input, $this->buffer->offset);
                    case "\n":
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case '"':
                    case '$':
                    case '`':
                    case '\\':
                        $this->buffer->value .= $cc;
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    default:
                        $this->buffer->value .= '\\' . $cc;
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                }
            }
            case TokenizerState::Dollar: {
                switch ($cc) {
                    case '(':
                        $type = match ($this->input[$this->pos + 1] ?? '') {
                            '(' => 'arithmetic',
                            default => 'command',
                        };
                        throw ParseError::at("Unsupported {$type} expansion", $this->input, $this->pos);
                    case '{':
                        $this->state = TokenizerState::DollarBrace;
                        goto ADVANCE;
                    default:
                        if (preg_match(self::EXPANSION_RX, $this->input, $m, 0, $this->pos)) {
                            if (isset($m['MARK'])) {
                                throw ParseError::at(
                                    sprintf('Unsupported special shell parameter "$%s"', $m[0]),
                                    $this->input,
                                    $this->pos,
                                );
                            }
                            yield from $this->flushTheTemporaryBuffer();
                            // simple expansion state
                            yield new Token(TokenKind::SimpleExpansion, $m[0], $this->pos);
                            $this->pos += \strlen($m[0]);
                            $this->state = $this->returnStates->pop();
                            goto RECONSUME;
                        }
                        $this->buffer->value .= '$';
                        $this->state = $this->returnStates->pop();
                        goto RECONSUME;
                }
            }
            case TokenizerState::DollarBrace: {
                if (preg_match(self::EXPANSION_RX, $this->input, $m, 0, $this->pos)) {
                    if (isset($m['MARK'])) {
                        throw ParseError::at(
                            sprintf('Unsupported special shell parameter "$%s"', $m[0]),
                            $this->input,
                            $this->pos,
                        );
                    }
                    yield from $this->flushTheTemporaryBuffer();
                    // part of the complex expansion state
                    $this->buffer->value = $m[0];
                    $this->pos += \strlen($m[0]);
                    $this->state = TokenizerState::ComplexExpansion;
                    goto RECONSUME;
                }
                throw $this->unexpectedChar($cc, 'Expected an identifier');
            }
            case TokenizerState::ComplexExpansion: {
                switch ($cc) {
                    case '}':
                        yield from $this->flushTheTemporaryBuffer(TokenKind::SimpleExpansion);
                        $this->state = $this->returnStates->pop();
                        goto ADVANCE;
                    default:
                        if (preg_match('/:?[?=+-]/A', $this->input, $m, 0, $this->pos)) {
                            yield from $this->flushTheTemporaryBuffer(TokenKind::ComplexExpansion);
                            // expansion operator state
                            yield new Token(TokenKind::ExpansionOperator, $m[0], $this->pos);
                            $this->pos += \strlen($m[0]);
                            $this->state = TokenizerState::ExpansionValue;
                            goto RECONSUME;
                        }
                        throw $this->unexpectedChar($cc, 'Expected "}" or an expansion operator');
                }
            }
            case TokenizerState::ExpansionValue: {
                switch ($cc) {
                    case '':
                        throw ParseError::at('Unterminated expansion', $this->input, $this->pos);
                    case '`':
                        throw ParseError::at('Unsupported command expansion', $this->input, $this->pos);
                    case '}';
                        yield from $this->flushTheTemporaryBuffer();
                        yield new Token(TokenKind::CloseBrace, '}', $this->pos);
                        $this->state = $this->returnStates->pop();
                        goto ADVANCE;
                    case '\\':
                        $this->state = TokenizerState::ExpansionValueEscape;
                        goto ADVANCE;
                    case "'":
                        if ($this->quotingLevel > 0) {
                            $this->buffer->value .= $cc;
                            goto ADVANCE;
                        }
                        $this->returnStates->push($this->state);
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    case '"':
                        $this->returnStates->push($this->state);
                        ++$this->quotingLevel;
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case '$':
                        $this->returnStates->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    default:
                        preg_match('/[^\\\\}$"`\']+/A', $this->input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        goto RECONSUME;
                }
            }
            case TokenizerState::ExpansionValueEscape: {
                switch ($cc) {
                    case '':
                        // TODO: properly track the error offset
                        throw ParseError::at('Unterminated expansion', $this->input, $this->pos);
                    case "\n":
                        $this->state = TokenizerState::ExpansionValue;
                        goto ADVANCE;
                    case '"':
                    case '$':
                    case '`':
                    case '\\':
                        $this->buffer->value .= $cc;
                        $this->state = TokenizerState::ExpansionValue;
                        goto ADVANCE;
                    default:
                        if ($this->quotingLevel > 0) {
                            $this->buffer->value .= '\\';
                        }
                        $this->buffer->value .= $cc;
                        $this->state = TokenizerState::ExpansionValue;
                        goto ADVANCE;
                }
            }
            // The following states from the spec have been inlined for performance.
            // @codeCoverageIgnoreStart
            case TokenizerState::Comment:
            case TokenizerState::AssignmentName:
            case TokenizerState::SimpleExpansion:
            case TokenizerState::ExpansionOperator:
                throw new \LogicException('Unused state: ' . $this->state->name);
            // @codeCoverageIgnoreEnd
        }
    }

    private function flushTheTemporaryBuffer(TokenKind $kind = TokenKind::Characters): iterable
    {
        if ($this->buffer->value !== '') {
            yield new Token($kind, $this->buffer->value, $this->buffer->offset);
        }
        $this->buffer->value = '';
        $this->buffer->offset = $this->pos;
    }

    private function eof(): Token
    {
        return new Token(TokenKind::EOF, '', $this->pos);
    }

    private function unexpectedChar(string $char, string $message): ParseError
    {
        $pos = SourcePosition::fromOffset($this->input, $this->pos);
        return new ParseError(sprintf(
            'Unexpected character "%s" in %s state on line %d, column %d. %s',
            $char,
            $this->state->name,
            $pos->line,
            $pos->column,
            $message,
        ));
    }
}
