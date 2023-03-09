<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Xdg\Dotenv\Exception\ParseError;

final class Tokenizer implements TokenizerInterface
{
    private int $pos = -1;
    private Buffer $buffer;
    private bool $quoted = false;

    /** @var \SplQueue<Token> */
    private \SplQueue $queue;
    private \SplStack $states;

    private const SIMPLE_EXPANSION_RX = <<<'REGEXP'
    /
        \$
        (?<brace> { )?
        (?:
            (?<special> [@*#?$!-] | \d+ )
            | (?<identifier> [a-zA-Z_][a-zA-Z0-9_]* )
        )
        (?(<brace>) } )
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
        $this->states = new \SplStack();
        $this->queue = new \SplQueue();
        $this->queue->setIteratorMode(\SplDoublyLinkedList::IT_MODE_DELETE | \SplDoublyLinkedList::IT_MODE_FIFO);
        $this->buffer = new Buffer(0);
        do {
            $carryOn = $this->next();
            yield from $this->queue;
        } while ($carryOn);
        yield new Token(TokenKind::EOF, '', $this->pos);
    }

    private function next(): bool
    {
        ADVANCE:
        ++$this->pos;
        RECONSUME:
        $cc = $this->input[$this->pos] ?? '';
        switch ($this->state) {
            case TokenizerState::AssignmentList:
            ASSIGNMENT_LIST: {
                switch ($cc) {
                    case '':
                        return false;
                    case ' ':
                    case "\t":
                    case "\n":
                        $this->pos += \strspn($this->input, " \t\n", $this->pos);
                        goto RECONSUME;
                    case '#':
                        $this->pos += \strcspn($this->input, "\n", $this->pos);
                        goto RECONSUME;
                    default:
                        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)=/A', $this->input, $m, 0, $this->pos)) {
                            $token = new Token(TokenKind::Assign, $m[1], $this->pos);
                            $this->pos += \strlen($m[0]) - 1;
                            $this->state = TokenizerState::AssignmentValue;
                            $this->queue->enqueue($token);
                            return true;
                        }
                        throw $this->unexpectedChar($cc, 'Expected whitespace, newline, a comment or an identifier');
                }
            }
            case TokenizerState::AssignmentValue:
            ASSIGNMENT_VALUE: {
                $this->quoted = false;
                switch ($cc) {
                    case '':
                        $this->flushBuffer();
                        return false;
                    case ' ':
                    case "\t":
                    case "\n":
                        $this->flushBuffer();
                        $this->pos += \strspn($this->input, " \t\n", $this->pos);
                        $this->state = TokenizerState::AssignmentList;
                        goto RECONSUME;
                    case '\\':
                        $cn = $this->input[$this->pos + 1] ?? null;
                        if ($cn === null) {
                            $this->buffer->value .= '\\';
                            $this->flushBuffer();
                            return true;
                        }
                        ++$this->pos;
                        if ($cn === "\n") {
                            goto ADVANCE;
                        }
                        $this->buffer->value .= $cn;
                        goto ADVANCE;
                    case "'":
                        $this->flushBuffer();
                        $this->states->push($this->state);
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    case '"':
                        $this->flushBuffer();
                        $this->states->push($this->state);
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
                        $this->flushBuffer();
                        $this->states->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto DOLLAR;
                    default:
                        $this->flushBuffer();
                        preg_match('/[^\\\\ \t\n\'"`$|&;<>()]+/A', $this->input, $m, 0, $this->pos);
                        $token = new Token(TokenKind::Characters, $m[0], $this->pos);
                        $this->pos += \strlen($m[0]) - 1;
                        $this->queue->enqueue($token);
                        return true;
                }
            }
            case TokenizerState::SingleQuoted:
            SINGLE_QUOTED: {
                switch ($cc) {
                    case '':
                        throw ParseError::at('Unterminated single-quoted string', $this->input, $this->buffer->offset);
                    case "'":
                        $this->state = $this->states->pop();
                        $this->flushBuffer();
                        return true;
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
                $this->quoted = true;
                switch ($cc) {
                    case '':
                        throw ParseError::at('Unterminated double-quoted string', $this->input, $this->buffer->offset);
                    case '"':
                        $this->state = $this->states->pop();
                        $this->flushBuffer();
                        return true;
                    case '\\':
                        $cn = $this->input[$this->pos + 1] ?? '';
                        switch ($cn) {
                            case '':
                                goto ADVANCE;
                            case "\n":
                                ++$this->pos;
                                goto ADVANCE;
                            case '"':
                            case '$':
                            case '`':
                            case '\\':
                                ++$this->pos;
                                $this->buffer->value .= $cn;
                                goto ADVANCE;
                            default:
                                ++$this->pos;
                                $this->buffer->value .= '\\' . $cn;
                                goto ADVANCE;
                        }
                    case '`':
                        throw ParseError::at('Unsupported command expansion', $this->input, $this->pos);
                    case '$':
                        $this->flushBuffer();
                        $this->states->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto DOLLAR;

                    default:
                        preg_match('/[^\\\\"`$]+/A', $this->input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->pos += \strlen($m[0]);
                        $cc = $this->input[$this->pos] ?? '';
                        goto DOUBLE_QUOTED;
                }
            }
            case TokenizerState::Dollar:
            DOLLAR: {
                if ($token = $this->matchSimpleExpansion()) {
                    $this->flushBuffer();
                    $this->queue->enqueue($token);
                    $state = $this->states->pop();
                    $this->state = $state;
                    return true;
                }
                $this->state = TokenizerState::AfterDollar;
                goto ADVANCE;
            }
            case TokenizerState::AfterDollar:
            AFTER_DOLLAR: {
                switch ($cc) {
                    case '':
                        $this->buffer->value .= '$';
                        $this->flushBuffer();
                        return false;
                    case '(':
                        throw ParseError::at('Unsupported command or arithmetic expansion', $this->input, $this->pos);
                    case '{':
                        $this->state = TokenizerState::AfterDollarOpenBrace;
                        goto ADVANCE;
                    default:
                        $this->buffer->value .= '$';
                        $this->state = $this->states->pop();
                        goto RECONSUME;
                }
            }
            case TokenizerState::AfterDollarOpenBrace:
            AFTER_DOLLAR_OPEN_BRACE: {
                if (!preg_match('/[a-zA-Z_][a-zA-Z0-9_]*/A', $this->input, $m, 0, $this->pos)) {
                    throw $this->unexpectedChar($cc, 'Expected an identifier');
                }
                $token = new Token(TokenKind::ComplexExpansion, $m[0], $this->pos);
                $this->queue->enqueue($token);
                $this->pos += \strlen($m[0]);
                $this->state = TokenizerState::AfterExpansionIdentifier;
                goto RECONSUME;
            }
            case TokenizerState::AfterExpansionIdentifier:
            AFTER_EXPANSION_IDENTIFIER: {
                if (!preg_match('/:?[?=+-]/A', $this->input, $m, 0, $this->pos)) {
                    throw $this->unexpectedChar($cc, 'Expected an expansion operator');
                }
                $token = new Token(TokenKind::ExpansionOperator, $m[0], $this->pos);
                $this->queue->enqueue($token);
                $this->pos += \strlen($m[0]) - 1;
                $this->state = TokenizerState::ExpansionArguments;
                return true;
            }
            case TokenizerState::ExpansionArguments:
            EXPANSION_ARGUMENTS: {
                switch ($cc) {
                    case '':
                        throw ParseError::at('Unterminated expansion', $this->input, $this->pos);
                    case '}';
                        $token = new Token(TokenKind::CloseBrace, '}', $this->pos);
                        $this->queue->enqueue($token);
                        $this->state = $this->states->pop();
                        goto ADVANCE;
                    case '\\':
                        $cn = $this->input[$this->pos + 1] ?? '';
                        switch ($cn) {
                            case '':
                                goto ADVANCE;
                            case "\n":
                                ++$this->pos;
                                goto ADVANCE;
                            default:
                                ++$this->pos;
                                $value = $this->quoted ? "\\{$cn}" : $cn;
                                $this->queue->enqueue(new Token(TokenKind::Characters, $value, $this->pos));
                                return true;
                        }
                    case '`':
                        throw ParseError::at('Unsupported command expansion', $this->input, $this->pos);
                    case "'":
                        $this->flushBuffer();
                        if ($this->quoted) {
                            $this->queue->enqueue(new Token(TokenKind::Characters, "'", $this->pos));
                            return true;
                        }
                        $this->states->push($this->state);
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    case '"':
                        $this->flushBuffer();
                        $this->states->push($this->state);
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case '$':
                        $this->flushBuffer();
                        $this->states->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto DOLLAR;
                    default:
                        $this->flushBuffer();
                        preg_match('/[^\\\\}$"`\']+/A', $this->input, $m, 0, $this->pos);
                        $token = new Token(TokenKind::Characters, $m[0], $this->pos);
                        $this->pos += \strlen($m[0]) - 1;
                        $this->queue->enqueue($token);
                }
            }
        }
        return true;
    }

    private function matchSimpleExpansion(): ?Token
    {
        if (!preg_match(self::SIMPLE_EXPANSION_RX, $this->input, $m, \PREG_UNMATCHED_AS_NULL, $this->pos)) {
            return null;
        }
        if ($m['special'] !== null) {
            throw ParseError::at(
                "Unsupported special shell parameter: {$m['special']}",
                $this->input,
                $this->pos,
            );
        }
        $token = new Token(TokenKind::SimpleExpansion, $m['identifier'], $this->pos);
        $this->pos += \strlen($m[0]) - 1;
        return $token;
    }

    private function flushBuffer(): void
    {
        if ($this->buffer->value !== '') {
            $this->queue->enqueue(new Token(TokenKind::Characters, $this->buffer->value, $this->buffer->offset));
        }
        $this->buffer->value = '';
        $this->buffer->offset = $this->pos;
    }

    private function unexpectedChar(string $char, string $message): ParseError
    {
        $pos = SourcePosition::fromOffset($this->input, $this->pos);
        return new ParseError(sprintf(
            'Unexpected character "%s" on line %d, column %d. %s',
            $char,
            $pos->line,
            $pos->column,
            $message,
        ));
    }
}
