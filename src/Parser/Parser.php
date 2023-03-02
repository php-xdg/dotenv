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
    private ?int $lastAssignmentLine = null;
    private ?int $lastExportLine = null;

    public function __construct(
        private readonly Tokenizer $tokenizer,
    ) {
    }

    public function parse(): AssignmentList
    {
        $nodes = [];
        $this->consume();
        $this->skipWhitespaceAndComments();
        while ($this->current->kind !== TokenKind::EOF) {
            $nodes[] = $this->parseAssignment();
            $this->skipWhitespaceAndComments();
        }

        return new AssignmentList($nodes);
    }

    private function parseAssignment(): Assignment
    {
        $name = $this->skipExportStatement();
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
                    throw new ParseError(sprintf(
                        'Unescaped special character "%s" in unquoted value on line %d, column %d',
                        $token->value,
                        $token->line,
                        $token->col,
                    ));
                default:
                    $value = $this->charsUntil(
                        TokenKind::Newline,
                        TokenKind::Whitespace,
                        TokenKind::Dollar,
                        TokenKind::DoubleQuote,
                        TokenKind::SingleQuote,
                        TokenKind::Escaped,
                        TokenKind::Special,
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
        $start = $this->expect(TokenKind::SingleQuote);
        $value = '';
        while (true) {
            $token = $this->current;
            switch ($token->kind) {
                case TokenKind::EOF:
                    throw new ParseError(sprintf(
                        'Unterminated single-quoted string on line %d, column %d',
                        $start->line,
                        $start->col,
                    ));
                case TokenKind::SingleQuote:
                    $this->consume();
                    return new SimpleValue($value);
                case TokenKind::Escaped:
                    $this->consume();
                    if ($token->value === "'") {
                        $value .= '\\';
                        return new SimpleValue($value);
                    }
                    $value .= "\\{$token->value}";
                    break;
                default:
                    $this->consume();
                    $value .= $token->value;
                    break;
            }
        }
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
                case TokenKind::DoubleQuote:
                    $this->consume();
                    return self::createValue($nodes);
                case TokenKind::Dollar:
                    self::pushValue($nodes, $this->parsePossibleReference(true));
                    break;
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
                default:
                    $value = $this->charsUntil(TokenKind::Dollar, TokenKind::DoubleQuote, TokenKind::Escaped);
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
                default:
                    $value = $this->charsUntil(
                        TokenKind::Escaped,
                        TokenKind::Dollar,
                        TokenKind::DoubleQuote,
                        TokenKind::SingleQuote,
                        TokenKind::CloseBrace,
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

    private function skipExportStatement(): Token
    {
        $name = $this->expect(TokenKind::Identifier);
        if (strcasecmp('export', $name->value) === 0) {
            if ($this->lastExportLine === $name->line) {
                throw new ParseError("Multiple export statements on line {$name->line}");
            }
            if ($this->lastAssignmentLine === $name->line) {
                throw new ParseError("Export statement must precede assignments on line {$name->line}");
            }
            $this->lastExportLine = $name->line;
            $this->expect(TokenKind::Whitespace);
            $name = $this->expect(TokenKind::Identifier);
        }
        $this->lastAssignmentLine = $name->line;
        return $name;
    }

    private function skipWhitespaceAndComments(): void
    {
        while (true) {
            switch ($this->current->kind) {
                case TokenKind::Whitespace:
                case TokenKind::Newline:
                    $this->consume();
                    break;
                case TokenKind::Hash:
                    $this->skipUntil(TokenKind::Newline);
                    break;
                default:
                    return;
            };
        }
    }

    private function skipUntil(TokenKind ...$until): void
    {
        while (true) {
            if ($this->current->kind === TokenKind::EOF || \in_array($this->current->kind, $until, true)) {
                return;
            }
            $this->consume();
        }
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