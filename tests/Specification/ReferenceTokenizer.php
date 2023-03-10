<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Specification;

use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\Buffer;
use Xdg\Dotenv\Parser\SourcePosition;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenizerInterface;
use Xdg\Dotenv\Parser\TokenizerState;
use Xdg\Dotenv\Parser\TokenKind;

/**
 * This tokenizer must implement the specification algorithm to the letter,
 * without optimizations of any kind.
 */
final class ReferenceTokenizer implements TokenizerInterface
{
    private int $pos;
    private TokenizerState $state;
    private Buffer $temporaryBuffer;

    public function __construct(
        private readonly string $input,
    ) {
    }

    public function getPosition(int $offset): SourcePosition
    {
        return SourcePosition::fromOffset($this->input, $offset);
    }

    public function tokenize(): \Iterator
    {
        $this->pos = -1;
        $this->state = TokenizerState::AssignmentList;
        $this->temporaryBuffer = new Buffer(0);
        $quotingLevel = 0;
        $returnStates = new \SplStack();

        ADVANCE: ++$this->pos;
        RECONSUME: $cc = $this->input[$this->pos] ?? '';
        switch ($this->state) {
            case TokenizerState::AssignmentList:
                switch ($cc) {
                    case '':
                        yield $this->eof();
                        return null;
                    case ' ':
                    case "\t":
                    case "\n":
                        goto ADVANCE;
                    case '#':
                        $this->state = TokenizerState::Comment;
                        goto ADVANCE;
                    default:
                        if ($cc === '_' || \ctype_alpha($cc)) {
                            $this->temporaryBuffer->value = $cc;
                            $this->state = TokenizerState::AssignmentName;
                            goto ADVANCE;
                        }
                        throw $this->unexpectedChar($cc);
                }
            case TokenizerState::Comment:
                switch ($cc) {
                    case '':
                        yield $this->eof();
                        return null;
                    case "\n":
                        $this->state = TokenizerState::AssignmentList;
                        goto ADVANCE;
                    default:
                        goto ADVANCE;
                }
            case TokenizerState::AssignmentName:
                switch ($cc) {
                    case '=':
                        yield from $this->flushTheTemporaryBuffer(TokenKind::Assign);
                        $this->state = TokenizerState::AssignmentValue;
                        goto ADVANCE;
                    default:
                        if ($cc === '_' || \ctype_alnum($cc)) {
                            $this->temporaryBuffer->value .= $cc;
                            goto ADVANCE;
                        }
                        throw $this->unexpectedChar($cc);
                }
            case TokenizerState::AssignmentValue:
                switch ($cc) {
                    case '':
                        yield from $this->flushTheTemporaryBuffer();
                        yield $this->eof();
                        return null;
                    case " ":
                    case "\t":
                    case "\n":
                        yield from $this->flushTheTemporaryBuffer();
                        $this->state = TokenizerState::AssignmentList;
                        goto ADVANCE;
                    case '\\':
                        $this->state = TokenizerState::AssignmentValueEscape;
                        goto ADVANCE;
                    case "'":
                        $returnStates->push($this->state);;
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    case '"':
                        $returnStates->push($this->state);;
                        $this->state = TokenizerState::DoubleQuoted;
                        ++$quotingLevel;
                        goto ADVANCE;
                    case '$':
                        $returnStates->push($this->state);;
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    case '`':
                    case '|':
                    case '&':
                    case ';':
                    case '<':
                    case '>':
                    case '(':
                    case ')':
                        throw $this->unexpectedChar($cc);
                    default:
                        $this->temporaryBuffer->value .= $cc;
                        goto ADVANCE;
                }
            case TokenizerState::AssignmentValueEscape:
                switch ($cc) {
                    case '':
                        $this->temporaryBuffer->value .= '\\';
                        yield from $this->flushTheTemporaryBuffer();
                        yield $this->eof();
                        return null;
                    case "\n":
                        $this->state = TokenizerState::AssignmentValue;
                        goto ADVANCE;
                    default:
                        $this->temporaryBuffer->value .= $cc;
                        $this->state = TokenizerState::AssignmentValue;
                        goto ADVANCE;
                }
            case TokenizerState::SingleQuoted:
                switch ($cc) {
                    case '':
                        throw $this->unexpectedChar($cc);
                    case "'":
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    default:
                        $this->temporaryBuffer->value .= $cc;
                        goto ADVANCE;
                }
            case TokenizerState::DoubleQuoted:
                switch ($cc) {
                    case '':
                    case '`':
                        throw $this->unexpectedChar($cc);
                    case '"':
                        --$quotingLevel;
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    case '\\':
                        $this->state = TokenizerState::DoubleQuotedEscape;
                        goto ADVANCE;
                    case '$':
                        $returnStates->push($this->state);;
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    default:
                        $this->temporaryBuffer->value .= $cc;
                        goto ADVANCE;
                }
            case TokenizerState::DoubleQuotedEscape:
                switch ($cc) {
                    case '':
                        throw $this->unexpectedChar($cc);
                    case "\n":
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case '"':
                    case '$':
                    case '`':
                    case '\\':
                        $this->temporaryBuffer->value .= $cc;
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    default:
                        $this->temporaryBuffer->value .= '\\' . $cc;
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                }
            case TokenizerState::Dollar:
                switch ($cc) {
                    case '@':
                    case '*':
                    case '#':
                    case '?':
                    case '$':
                    case '!':
                    case '-':
                    case '(':
                        throw $this->unexpectedChar($cc);
                    case '{':
                        $this->state = TokenizerState::DollarBrace;
                        goto ADVANCE;
                    default:
                        if ($cc === '_' || \ctype_alpha($cc)) {
                            yield from $this->flushTheTemporaryBuffer();
                            $this->temporaryBuffer->value = $cc;
                            $this->state = TokenizerState::SimpleExpansion;
                            goto ADVANCE;
                        }
                        if (\ctype_digit($cc)) {
                            throw $this->unexpectedChar($cc);
                        }
                        $this->temporaryBuffer->value .= '$';
                        $this->state = $returnStates->pop();
                        goto RECONSUME;
                }
            case TokenizerState::DollarBrace:
                switch ($cc) {
                    case '@':
                    case '*':
                    case '#':
                    case '?':
                    case '$':
                    case '!':
                    case '-':
                        throw $this->unexpectedChar($cc);
                    default:
                        if ($cc === '_' || \ctype_alpha($cc)) {
                            yield from $this->flushTheTemporaryBuffer();
                            $this->temporaryBuffer->value = $cc;
                            $this->state = TokenizerState::ComplexExpansion;
                            goto ADVANCE;
                        }
                        throw $this->unexpectedChar($cc);
                }
            case TokenizerState::SimpleExpansion:
                if ($cc === '_' || \ctype_alnum($cc)) {
                    $this->temporaryBuffer->value .= $cc;
                    goto ADVANCE;
                }
                yield from $this->flushTheTemporaryBuffer(TokenKind::SimpleExpansion);
                $this->state = $returnStates->pop();
                goto RECONSUME;
            case TokenizerState::ComplexExpansion:
                switch ($cc) {
                    case '}':
                        yield from $this->flushTheTemporaryBuffer(TokenKind::SimpleExpansion);
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    case ':':
                        yield from $this->flushTheTemporaryBuffer(TokenKind::ComplexExpansion);
                        $this->temporaryBuffer->value = $cc;
                        $this->state = TokenizerState::ExpansionOperator;
                        goto ADVANCE;
                    case '?':
                    case '=':
                    case '+':
                    case '-':
                        yield from $this->flushTheTemporaryBuffer(TokenKind::ComplexExpansion);
                        yield new Token(TokenKind::ExpansionOperator, $cc, $this->pos);
                        $this->state = TokenizerState::ExpansionValue;
                        goto ADVANCE;
                    default:
                        if ($cc === '_' || \ctype_alnum($cc)) {
                            $this->temporaryBuffer->value .= $cc;
                            goto ADVANCE;
                        }
                        throw $this->unexpectedChar($cc);
                }
            case TokenizerState::ExpansionOperator:
                switch ($cc) {
                    case '?':
                    case '=':
                    case '+':
                    case '-':
                        $this->temporaryBuffer->value .= $cc;
                        yield from $this->flushTheTemporaryBuffer(TokenKind::ExpansionOperator);
                        $this->state = TokenizerState::ExpansionValue;
                        goto ADVANCE;
                    default:
                        throw $this->unexpectedChar($cc);
                }
            case TokenizerState::ExpansionValue:
                switch ($cc) {
                    case '':
                    case '`':
                        throw $this->unexpectedChar($cc);
                    case '}':
                        yield from $this->flushTheTemporaryBuffer();
                        yield new Token(TokenKind::CloseBrace, '}', $this->pos);
                        $this->state = $returnStates->pop();
                        goto ADVANCE;
                    case '\\':
                        $this->state = TokenizerState::ExpansionValueEscape;
                        goto ADVANCE;
                    case '$':
                        $returnStates->push($this->state);;
                        $this->state = TokenizerState::Dollar;
                        goto ADVANCE;
                    case '"':
                        $returnStates->push($this->state);
                        ++$quotingLevel;
                        $this->state = TokenizerState::DoubleQuoted;
                        goto ADVANCE;
                    case "'":
                        if ($quotingLevel > 0) {
                            $this->temporaryBuffer->value .= $cc;
                            goto ADVANCE;
                        }
                        $returnStates->push($this->state);;
                        $this->state = TokenizerState::SingleQuoted;
                        goto ADVANCE;
                    default:
                        $this->temporaryBuffer->value .= $cc;
                        goto ADVANCE;
                }
            case TokenizerState::ExpansionValueEscape:
                switch ($cc) {
                    case '':
                        throw $this->unexpectedChar($cc);
                    case "\n":
                        $this->state = TokenizerState::ExpansionValue;
                        goto ADVANCE;
                    default:
                        if ($quotingLevel > 0) {
                            $this->temporaryBuffer->value .= '\\';
                        }
                        $this->temporaryBuffer->value .= $cc;
                        $this->state = TokenizerState::ExpansionValue;
                        goto ADVANCE;
                }
        }
    }

    /**
     * @return iterable<Token>
     */
    private function flushTheTemporaryBuffer(TokenKind $kind = TokenKind::Characters): iterable
    {
        if ($this->temporaryBuffer->value !== '') {
            yield new Token($kind, $this->temporaryBuffer->value, $this->temporaryBuffer->offset);
        }
        $this->temporaryBuffer->value = '';
    }

    private function eof(): Token
    {
        return new Token(TokenKind::EOF, '', $this->pos);
    }

    private function unexpectedChar(string $char): ParseError
    {
        $msg = sprintf(
            'Unexpected character "%s" in %s state at position %d',
            $char,
            $this->state->name,
            $this->pos,
        );
        return new ParseError($msg);
    }
}
