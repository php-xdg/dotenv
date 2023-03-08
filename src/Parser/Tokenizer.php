<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Xdg\Dotenv\Exception\ParseError;

final class Tokenizer
{
    private int $pos = -1;
    private int $line = 1;
    private int $col = 0;
    private Buffer $buffer;
    private bool $quoted = false;

    /** @var \SplQueue<Token>  */
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
        public TokenizerState $state = TokenizerState::Initial,
    ) {
    }

    /**
     * @return \Iterator<int, Token>
     */
    public function tokenize(): \Iterator
    {
        $this->states = new \SplStack();
        $this->queue = new \SplQueue();
        $this->queue->setIteratorMode(\SplDoublyLinkedList::IT_MODE_DELETE|\SplDoublyLinkedList::IT_MODE_FIFO);
        $this->buffer = new Buffer($this->line, $this->col);
        do {
            $carryOn = $this->next();
            yield from $this->queue;
        } while ($carryOn);
        yield $this->make(TokenKind::EOF, '');
    }

    private function next(): bool
    {
        ADVANCE:
        $this->advance();
        RECONSUME:
        $cc = $this->input[$this->pos] ?? '';
        switch ($this->state) {
            case TokenizerState::Initial:
            INITIAL: {
                switch ($cc) {
                    case '':
                        return false;
                    case ' ':
                    case "\t":
                        $this->advance(\strspn($this->input, " \t", $this->pos));
                        goto RECONSUME;
                    case "\n":
                        $this->newline();
                        goto ADVANCE;
                    case '#':
                        $this->advance(\strcspn($this->input, "\n", $this->pos));
                        goto RECONSUME;
                    default:
                        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)=/A', $this->input, $m, 0, $this->pos)) {
                            $token = $this->make(TokenKind::Assign, $m[1]);
                            $this->advance(\strlen($m[0]) - 1);
                            $this->state = TokenizerState::Unquoted;
                            $this->queue->enqueue($token);
                            return true;
                        }
                        throw $this->unexpectedChar($cc, 'Expected whitespace, newline, a comment or an identifier');
                }
            }
            case TokenizerState::Unquoted:
            UNQUOTED: {
                $this->quoted = false;
                switch ($cc) {
                    case '':
                        $this->flushBuffer();
                        return false;
                    case ' ':
                    case "\t":
                        $this->flushBuffer();
                        $this->advance(\strspn($this->input, " \t", $this->pos));
                        $this->state = TokenizerState::Initial;
                        goto RECONSUME;
                    case "\n":
                        $this->flushBuffer();
                        $this->newline();
                        $this->state = TokenizerState::Initial;
                        goto ADVANCE;
                    case '\\':
                        $cn = $this->input[$this->pos + 1] ?? null;
                        if ($cn === null) {
                            $this->buffer->value .= '\\';
                            $this->flushBuffer();
                            return true;
                        }
                        $this->advance();
                        if ($cn === "\n") {
                            $this->newline();
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
                        throw new ParseError(sprintf(
                            'Unsupported command expansion on line %d, column %d',
                            $this->line,
                            $this->col,
                        ));
                    case '|':
                    case '&':
                    case ';':
                    case '<':
                    case '>':
                    case '(':
                    case ')':
                        throw new ParseError(sprintf(
                            'Unescaped special character "%s" in unquoted value on line %d, column %d',
                            $cc,
                            $this->line,
                            $this->col,
                        ));
                    case '$':
                        $this->flushBuffer();
                        $this->states->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto DOLLAR;
                    default:
                        $this->flushBuffer();
                        preg_match('/[^\\\\ \t\n\'"`$|&;<>()]+/A', $this->input, $m, 0, $this->pos);
                        $token = $this->make(TokenKind::Characters, $m[0]);
                        $this->advance(\strlen($m[0]) - 1);
                        $this->queue->enqueue($token);
                        return true;
                }
            } break;
            case TokenizerState::SingleQuoted:
            SINGLE_QUOTED: {
                switch ($cc) {
                    case '':
                        throw new ParseError(sprintf(
                            'Unterminated single-quoted string on line %d, column %d',
                            $this->buffer->line,
                            $this->buffer->col,
                        ));
                    case "'":
                        $this->state = $this->states->pop();
                        $this->flushBuffer();
                        return true;
                    case "\n":
                        $this->buffer->value .= "\n";
                        $this->newline();
                        goto ADVANCE;
                    default:
                        preg_match("/[^'\n]+/A", $this->input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->advance(\strlen($m[0]));
                        goto RECONSUME;
                }
            }
            case TokenizerState::DoubleQuoted:
            DOUBLE_QUOTED: {
                $this->quoted = true;
                switch ($cc) {
                    case '':
                        throw new ParseError(sprintf(
                            'Unterminated double-quoted string on line %d, column %d',
                            $this->buffer->line,
                            $this->buffer->col,
                        ));
                    case '"':
                        $this->state = $this->states->pop();
                        $this->flushBuffer();
                        return true;
                    case "\n":
                        $this->buffer->value .= "\n";
                        $this->newline();
                        goto ADVANCE;
                    case '\\':
                        $cn = $this->input[$this->pos + 1] ?? '';
                        switch ($cn) {
                            case '':
                                goto ADVANCE;
                            case "\n":
                                $this->advance();
                                $this->newline();
                                goto ADVANCE;
                            case '"':
                            case '$':
                            case '\\':
                                $this->advance();
                                $this->buffer->value .= $cn;
                                goto ADVANCE;
                            default:
                                $this->advance();
                                $this->buffer->value .= '\\' . $cn;
                                goto ADVANCE;
                        }
                    case '`':
                        throw new ParseError(sprintf(
                            'Unsupported command expansion on line %d, column %d',
                            $this->line,
                            $this->col,
                        ));
                    case '$':
                        $this->flushBuffer();
                        $this->states->push($this->state);
                        $this->state = TokenizerState::Dollar;
                        goto DOLLAR;

                    default:
                        preg_match('/[^\\\\"`\n$]+/A', $this->input, $m, 0, $this->pos);
                        $this->buffer->value .= $m[0];
                        $this->advance(\strlen($m[0]));
                        goto RECONSUME;
                }
            } break;
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
            } break;
            case TokenizerState::AfterDollar:
            AFTER_DOLLAR: {
                switch ($cc) {
                    case '':
                        $this->buffer->value .= '$';
                        $this->flushBuffer();
                        return false;
                    case '(':
                        throw new ParseError(sprintf(
                            'Unsupported command or arithmetic expansion on line %d, column %d',
                            $this->line,
                            $this->col,
                        ));
                    case '{':
                        $this->state = TokenizerState::AfterDollarOpenBrace;
                        goto ADVANCE;
                    default:
                        $this->buffer->value .= '$';
                        $this->state = $this->states->pop();
                        goto RECONSUME;
                }
            } break;
            case TokenizerState::AfterDollarOpenBrace:
            AFTER_DOLLAR_OPEN_BRACE: {
                if (!preg_match('/[a-zA-Z_][a-zA-Z0-9_]*/A', $this->input, $m, 0, $this->pos)) {
                    throw $this->unexpectedChar($cc, 'Expected an identifier');
                }
                $token = $this->make(TokenKind::ComplexExpansion, $m[0]);
                $this->queue->enqueue($token);
                $this->advance(\strlen($m[0]));
                $this->state = TokenizerState::AfterExpansionIdentifier;
                goto RECONSUME;
            } break;
            case TokenizerState::AfterExpansionIdentifier:
            AFTER_EXPANSION_IDENTIFIER: {
                if (!preg_match('/:?[?=+-]/A', $this->input, $m, 0, $this->pos)) {
                    throw $this->unexpectedChar($cc, 'Expected an expansion operator');
                }
                $token = $this->make(TokenKind::ExpansionOperator, $m[0]);
                $this->queue->enqueue($token);
                $this->advance(\strlen($m[0]) - 1);
                $this->state = TokenizerState::ExpansionArguments;
                return true;
            }
            case TokenizerState::ExpansionArguments:
            EXPANSION_ARGUMENTS: {
                switch ($cc) {
                    case '':
                        throw new ParseError(sprintf(
                            'Unterminated expansion on line %d, column %d',
                            $this->line,
                            $this->col,
                        ));
                    case '}';
                        $token = $this->make(TokenKind::CloseBrace, '}');
                        $this->queue->enqueue($token);
                        $this->state = $this->states->pop();
                        goto ADVANCE;
                    case '\\':
                        $cn = $this->input[$this->pos + 1] ?? '';
                        switch ($cn) {
                            case '':
                                goto ADVANCE;
                            case "\n":
                                $this->advance();
                                $this->newline();
                                goto ADVANCE;
                            default:
                                $this->advance();
                                if ($this->quoted) {
                                    $this->queue->enqueue($this->make(TokenKind::Characters, "\\{$cn}"));
                                    return true;
                                }
                                $this->queue->enqueue($this->make(TokenKind::Characters, $cn));
                                return true;
                        }
                    case '`':
                        throw new ParseError(sprintf(
                            'Unsupported command expansion on line %d, column %d',
                            $this->line,
                            $this->col,
                        ));
                    case "'":
                        $this->flushBuffer();
                        if ($this->quoted) {
                            $this->queue->enqueue($this->make(TokenKind::Characters, "'"));
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
                        $token = $this->make(TokenKind::Characters, $m[0]);
                        $this->advance(\strlen($m[0]) - 1);
                        $this->queue->enqueue($token);
                        return true;
                }
            } break;
        }
        return true;
    }

    private function matchSimpleExpansion(): ?Token
    {
        if (!preg_match(self::SIMPLE_EXPANSION_RX, $this->input, $m, \PREG_UNMATCHED_AS_NULL, $this->pos)) {
            return null;
        }
        if ($m['special'] !== null) {
            throw new ParseError(sprintf(
                'Unsupported shell special parameter "%s" on line %d, column %d',
                $m['special'],
                $this->line,
                $this->col,
            ));
        }
        $token = new Token(TokenKind::SimpleExpansion, $m['identifier'], $this->line, $this->col);
        $this->advance(\strlen($m[0]) - 1);
        return $token;
    }

    private function flushBuffer(): void
    {
        if ($this->buffer->value !== '') {
            $this->queue->enqueue(new Token(TokenKind::Characters, $this->buffer->value, $this->buffer->line, $this->buffer->col));
        }
        $this->buffer->value = '';
        $this->buffer->line = $this->line;
        $this->buffer->col = $this->col;
    }

    private function make(TokenKind $kind, string $value): Token
    {
        return new Token($kind, $value, $this->line, $this->col);
    }

    private function advance(int $n = 1): void
    {
        $this->pos += $n;
        $this->col += $n;
    }

    private function newline(): void
    {
        ++$this->line;
        $this->col = 0;
    }

    private function unexpectedChar(string $char, string $message): ParseError
    {
        return new ParseError(sprintf(
            'Unexpected character "%s" on line %d, column %d. %s',
            $char,
            $this->line,
            $this->col,
            $message,
        ));
    }
}
