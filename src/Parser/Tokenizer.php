<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Xdg\Dotenv\Exception\ParseError;

final class Tokenizer implements TokenizerInterface
{
    private Source $src;
    private TokenizerState $state;
    private int $pos = -1;
    private Buffer $buffer;

    private const EXPANSION_RX = <<<'REGEXP'
    /
        [a-zA-Z_][a-zA-Z0-9_]*
        | (?: [@*#?$!-] | \d+ ) (*MARK:special)
    /Ax
    REGEXP;

    public function tokenize(Source $src): \Iterator
    {
        $this->src = $src;
        $input = $src->bytes;
        $this->state = TokenizerState::AssignmentList;
        $this->pos = -1;
        $this->buffer = new Buffer();
        $returnStates = new \SplStack();
        // error position tracking
        $lastOpenedSingleQuote = 0;
        $quotingStack = new \SplStack();
        $expansionStack = new \SplStack();

        ADVANCE: ++$this->pos;
        RECONSUME: $cc = $input[$this->pos] ?? '';
        switch ($this->state) {
            case TokenizerState::AssignmentList: {
                switch ($cc) {
                    case '':
                        yield $this->eof();
                        return null;
                    case ' ':
                    case "\t":
                    case "\n":
                        $this->pos += \strspn($input, " \t\n", $this->pos);
                        goto RECONSUME;
                    case '#':
                        // comment state
                        $this->pos += \strcspn($input, "\n", $this->pos);
                        goto RECONSUME;
                    default:
                        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)=/A', $input, $m, 0, $this->pos)) {
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
                        $this->pos += \strspn($input, " \t\n", $this->pos);
                        $this->state = TokenizerState::AssignmentList;
                        goto RECONSUME;
                    case '\\':
                        $this->state = TokenizerState::AssignmentValueEscape;
                        goto ADVANCE;
                    case "'":
                        $lastOpenedSingleQuote = $this->pos;
                        $returnStates->push($this->state);
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    case '"':
                        $returnStates->push($this->state);
                        $quotingStack->push($this->pos);
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case '`':
                        throw ParseError::in($src, $this->pos, 'Unsupported command expansion');
                    case '|':
                    case '&':
                    case ';':
                    case '<':
                    case '>':
                    case '(':
                    case ')':
                        throw ParseError::in($src, $this->pos, "Unescaped special character '{$cc}'");
                    case '$':
                        $returnStates->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    default:
                        preg_match('/[^\\\\ \t\n\'"`$|&;<>()]+/A', $input, $m, 0, $this->pos);
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
                        throw ParseError::in($src, $lastOpenedSingleQuote, 'Unterminated single-quoted string');
                    case "'":
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    default:
                        preg_match("/[^']+/A", $input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        $cc = $input[$this->pos] ?? '';
                        goto SINGLE_QUOTED;
                }
            }
            case TokenizerState::DoubleQuoted:
            DOUBLE_QUOTED: {
                switch ($cc) {
                    case '':
                        // TODO: properly track the error offset
                        throw ParseError::in($src, $quotingStack->top(), 'Unterminated double-quoted string');
                    case '`':
                        throw ParseError::in($src, $this->pos, 'Unsupported command expansion');
                    case '"':
                        $quotingStack->pop();
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    case '\\':
                        $this->state = TokenizerState::DoubleQuotedEscape;
                        goto ADVANCE;
                    case '$':
                        $returnStates->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    default:
                        preg_match('/[^\\\\"`$]+/A', $input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        $cc = $input[$this->pos] ?? '';
                        goto DOUBLE_QUOTED;
                }
            }
            case TokenizerState::DoubleQuotedEscape: {
                switch ($cc) {
                    case '':
                        // TODO: properly track the error offset
                        throw ParseError::in($src, $this->buffer->offset, 'Unterminated double-quoted string');
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
                        $type = match ($input[$this->pos + 1] ?? '') {
                            '(' => 'arithmetic',
                            default => 'command',
                        };
                        throw ParseError::in($src, $this->pos, "Unsupported {$type} expansion");
                    case '{':
                        $expansionStack->push($this->pos);
                        yield from $this->flushTheTemporaryBuffer();
                        $this->state = TokenizerState::ComplexExpansionStart;
                        goto ADVANCE;
                    default:
                        if (preg_match(self::EXPANSION_RX, $input, $m, 0, $this->pos)) {
                            if (isset($m['MARK'])) {
                                throw ParseError::in($src, $this->pos, "Unsupported special shell parameter \${$m[0]}");
                            }
                            yield from $this->flushTheTemporaryBuffer();
                            // simple expansion state
                            yield new Token(TokenKind::SimpleExpansion, $m[0], $this->pos - 1);
                            $this->pos += \strlen($m[0]);
                            $this->state = $returnStates->pop();
                            goto RECONSUME;
                        }
                        $this->buffer->value .= '$';
                        $this->state = $returnStates->pop();
                        goto RECONSUME;
                }
            }
            case TokenizerState::ComplexExpansionStart: {
                if (preg_match(self::EXPANSION_RX, $input, $m, 0, $this->pos)) {
                    if (isset($m['MARK'])) {
                        throw ParseError::in($src, $this->pos, "Unsupported special shell parameter \${{$m[0]}}");
                    }
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
                        $expansionStack->pop();
                        yield from $this->flushTheTemporaryBuffer(TokenKind::SimpleExpansion, -1);
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    default:
                        if (preg_match('/:?[?=+-]/A', $input, $m, 0, $this->pos)) {
                            yield from $this->flushTheTemporaryBuffer(TokenKind::StartExpansion, -1);
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
                        throw ParseError::in($src, $expansionStack->top(), 'Unterminated expansion');
                    case '`':
                        throw ParseError::in($src, $this->pos, 'Unsupported command expansion');
                    case '}';
                        $expansionStack->pop();
                        yield from $this->flushTheTemporaryBuffer();
                        yield new Token(TokenKind::EndExpansion, '}', $this->pos);
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    case '\\':
                        $this->state = TokenizerState::ExpansionValueEscape;
                        goto ADVANCE;
                    case "'":
                        if (!$quotingStack->isEmpty()) {
                            $this->buffer->value .= $cc;
                            goto ADVANCE;
                        }
                        $lastOpenedSingleQuote = $this->pos;
                        $returnStates->push($this->state);
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    case '"':
                        $returnStates->push($this->state);
                        $quotingStack->push($this->pos);
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case '$':
                        $returnStates->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    default:
                        preg_match('/[^\\\\}$"`\']+/A', $input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        goto RECONSUME;
                }
            }
            case TokenizerState::ExpansionValueEscape: {
                switch ($cc) {
                    case '':
                        throw ParseError::in($src, $expansionStack->top(), 'Unterminated expansion');
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
                        if (!$quotingStack->isEmpty()) {
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

    private function flushTheTemporaryBuffer(TokenKind $kind = TokenKind::Characters, int $offset = 0): iterable
    {
        if ($this->buffer->value !== '') {
            yield new Token($kind, $this->buffer->value, $this->buffer->offset + $offset);
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
        $message = sprintf(
            'Unexpected character "%s" in %s state (%s)',
            $char,
            $this->state->name,
            $message,
        );
        return ParseError::in($this->src, $this->pos, $message);
    }
}
