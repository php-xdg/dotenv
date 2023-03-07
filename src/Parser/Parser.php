<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\Ast\Assignment;
use Xdg\Dotenv\Parser\Ast\AssignmentList;
use Xdg\Dotenv\Parser\Ast\ComplexReference;
use Xdg\Dotenv\Parser\Ast\CompositeValue;
use Xdg\Dotenv\Parser\Ast\ExpansionOperator;
use Xdg\Dotenv\Parser\Ast\SimpleReference;
use Xdg\Dotenv\Parser\Ast\SimpleValue;

final class Parser
{
    private Token $current;

    public function __construct(
        private readonly Tokenizer $tokenizer,
    ) {
    }

    public function parse(): AssignmentList
    {
        $nodes = [];
        $this->skipWhitespaceAndComments();
        while ($this->current->kind !== TokenKind::EOF) {
            $nodes[] = $this->parseAssignment();
            $this->skipWhitespaceAndComments();
        }

        return new AssignmentList($nodes);
    }

    private function parseAssignment(): Assignment
    {
        $name = $this->expect(TokenKind::Identifier);
        $this->expect(TokenKind::Equal);
        switch ($this->current->kind) {
            case TokenKind::Whitespace:
                throw new ParseError(sprintf(
                    'Whitespace after equal sign in assignment on line %d, column %d',
                    $this->current->line,
                    $this->current->col,
                ));
            case TokenKind::Newline:
            case TokenKind::EOF:
                return new Assignment($name->value, null);
            default:
                $value = $this->parseAssignmentValue();
                return new Assignment($name->value, $value);
        }
    }

    private function parseAssignmentValue(): SimpleValue|CompositeValue|SimpleReference|ComplexReference
    {
        $nodes = [];
        while (true) {
            $token = $this->current;
            switch ($token->kind) {
                case TokenKind::EOF:
                case TokenKind::Newline:
                case TokenKind::Whitespace:
                    return self::createValue($nodes);
                case TokenKind::SingleQuote:
                    self::pushValue($nodes, $this->parseSingleQuotedString());
                    break;
                case TokenKind::DoubleQuote:
                    self::pushValue($nodes, $this->parseDoubleQuotedString());
                    break;
                case TokenKind::Dollar:
                    self::pushValue($nodes, $this->parsePossibleReference());
                    break;
                case TokenKind::Escaped:
                    // https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_02_01
                    if ($token->value !== "\n") {
                        self::pushValue($nodes, $token->value);
                    }
                    $this->consume();
                    break;
                case TokenKind::Special:
                    throw ParseError::at(
                        sprintf('Unescaped special character "%s" in unquoted value', $token->value),
                        $token,
                    );
                case TokenKind::ShellParameter:
                    throw ParseError::at(
                        sprintf('Reserved shell parameter "%s" in unquoted value', $token->value),
                        $token,
                    );
                default:
                    $value = $this->charsUntil(
                        TokenKind::Newline,
                        TokenKind::Whitespace,
                        TokenKind::Dollar,
                        TokenKind::DoubleQuote,
                        TokenKind::SingleQuote,
                        TokenKind::Escaped,
                        TokenKind::Special,
                        TokenKind::ShellParameter,
                    );
                    self::pushValue($nodes, $value);
                    break;
            }
        }
    }

    /**
     * @link https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_02_02
     */
    private function parseSingleQuotedString(): SimpleValue
    {
        $start = $this->current;
        $value = $this->tokenizer->consumeSingleQuotedChars();
        $this->consume();
        if ($this->current->kind === TokenKind::EOF) {
            throw new ParseError(sprintf(
                'Unterminated single-quoted string on line %d, column %d',
                $start->line,
                $start->col,
            ));
        }
        $this->expect(TokenKind::SingleQuote);
        return new SimpleValue($value);
    }

    /**
     * @link https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_02_03
     */
    private function parseDoubleQuotedString(): SimpleValue|CompositeValue|SimpleReference|ComplexReference
    {
        $start = $this->expect(TokenKind::DoubleQuote);
        $nodes = [];
        while (true) {
            $token = $this->current;
            switch ($token->kind) {
                case TokenKind::EOF:
                    throw new ParseError(sprintf(
                        'Unterminated double-quoted string on line %d, column %d',
                        $start->line,
                        $start->col,
                    ));
                case TokenKind::Escaped:
                    $this->consume();
                    switch ($token->value) {
                        case "\n":
                            break; // line continuation
                        case '"':
                        case '$':
                        case '\\':
                            self::pushValue($nodes, $token->value);
                            break;
                        default:
                            self::pushValue($nodes, '\\' . $token->value);
                            break;
                    }
                    break;
                case TokenKind::DoubleQuote:
                    $this->consume();
                    return self::createValue($nodes);
                case TokenKind::Dollar:
                    self::pushValue($nodes, $this->parsePossibleReference(true));
                    break;
                case TokenKind::ShellParameter:
                    throw ParseError::at(
                        sprintf('Reserved shell parameter "%s" in double-quoted value', $token->value),
                        $token,
                    );
                case TokenKind::Special:
                    if ($token->value === '`') {
                        throw ParseError::at('Unsupported command expansion', $token);
                    }
                    $this->consume();
                    self::pushValue($nodes, $token->value);
                    break;
                default:
                    $value = $this->charsUntil(
                        TokenKind::Escaped,
                        TokenKind::DoubleQuote,
                        TokenKind::Dollar,
                        TokenKind::ShellParameter,
                        TokenKind::Special,
                    );
                    self::pushValue($nodes, $value);
                    break;
            }
        }
    }

    private function parsePossibleReference(bool $quoted = false): SimpleValue|SimpleReference|ComplexReference
    {
        $this->expect(TokenKind::Dollar);
        $token = $this->current;
        switch ($token->kind) {
            case TokenKind::Identifier:
                $this->consume();
                return new SimpleReference($token->value);
            case TokenKind::OpenBrace:
                $this->consume();
                $id = $this->expect(TokenKind::Identifier)->value;
                if ($this->current->kind === TokenKind::CloseBrace) {
                    $this->consume();
                    return new SimpleReference($id);
                }
                $op = $this->parseExpansionOperator();
                $rhs = $this->parseExpansionArguments($quoted);
                return new ComplexReference($id, $op, $rhs);
            case TokenKind::Special:
                if ($token->value === '(') {
                    throw ParseError::at('Unsupported command or arithmetic expansion', $token);
                }
            // fallthrough
            default:
                return new SimpleValue('$');
        }
    }

    private function parseExpansionOperator(): ExpansionOperator
    {
        $op = '';
        if ($this->current->kind === TokenKind::Colon) {
            $op .= ':';
            $this->consume();
        }
        $op .= $this->expectEither(
            TokenKind::Minus,
            TokenKind::Equal,
            TokenKind::Plus,
            TokenKind::QuestionMark,
        )->value;
        return ExpansionOperator::from($op);
    }

    private function parseExpansionArguments(
        bool $quoted = false,
    ): SimpleValue|CompositeValue|SimpleReference|ComplexReference {
        $nodes = [];
        while (true) {
            $token = $this->current;
            switch ($token->kind) {
                case TokenKind::EOF:
                    throw ParseError::unexpectedToken($token);
                case TokenKind::Escaped:
                    $this->consume();
                    switch ($token->value) {
                        case "\n":
                            break; // line continuation
                        default:
                            $value = $quoted ? "\\{$token->value}" : $token->value;
                            self::pushValue($nodes, $value);
                            break;
                    }
                    break;
                case TokenKind::SingleQuote:
                    if ($quoted) {
                        $this->consume();
                        self::pushValue($nodes, $token->value);
                    } else {
                        self::pushValue($nodes, $this->parseSingleQuotedString());
                    }
                    break;
                case TokenKind::DoubleQuote:
                    self::pushValue($nodes, $this->parseDoubleQuotedString());
                    break;
                case TokenKind::Dollar:
                    self::pushValue($nodes, $this->parsePossibleReference($quoted));
                    break;
                case TokenKind::CloseBrace:
                    $this->consume();
                    return self::createValue($nodes);
                case TokenKind::ShellParameter:
                    throw ParseError::at(
                        sprintf('Reserved shell parameter "%s" in expansion', $token->value),
                        $token,
                    );
                default:
                    $value = $this->charsUntil(
                        TokenKind::Escaped,
                        TokenKind::Dollar,
                        TokenKind::DoubleQuote,
                        TokenKind::SingleQuote,
                        TokenKind::CloseBrace,
                        TokenKind::ShellParameter,
                    );
                    self::pushValue($nodes, $value);
                    break;
            }
        }
    }

    private function consume(): void
    {
        $this->current = $this->tokenizer->next();
    }

    private function expect(TokenKind $kind): Token
    {
        $token = $this->current;
        if ($token->kind !== $kind) {
            throw ParseError::unexpectedToken($token, $kind);
        }
        $this->consume();
        return $token;
    }

    private function expectEither(TokenKind ...$kinds): Token
    {
        $token = $this->current;
        if (!\in_array($token->kind, $kinds, true)) {
            throw ParseError::unexpectedToken($token, ...$kinds);
        }
        $this->consume();
        return $token;
    }

    private function skipWhitespaceAndComments(): void
    {
        $this->tokenizer->skipWhitespaceAndComments();
        $this->consume();
    }

    private function charsUntil(TokenKind ...$until): string
    {
        $value = '';
        while (true) {
            $token = $this->current;
            if ($token->kind === TokenKind::EOF || \in_array($token->kind, $until, true)) {
                return $value;
            }
            $this->consume();
            $value .= $token->value;
        }
    }

    private static function createValue(array $nodes): SimpleValue|CompositeValue|SimpleReference|ComplexReference
    {
        return match (\count($nodes)) {
            0 => new SimpleValue(''),
            1 => $nodes[0],
            default => new CompositeValue($nodes),
        };
    }

    private static function pushValue(
        array &$nodes,
        string|SimpleValue|CompositeValue|SimpleReference|ComplexReference $value
    ): void {
        if (\is_string($value)) {
            $last = end($nodes);
            if ($last instanceof SimpleValue) {
                $last->value .= $value;
                return;
            }
            $nodes[] = new SimpleValue($value);
            return;
        }
        if ($value instanceof SimpleValue) {
            $last = end($nodes);
            if ($last instanceof SimpleValue) {
                $last->value .= $value->value;
                return;
            }
            $nodes[] = $value;
            return;
        }

        $nodes[] = $value;
    }
}
