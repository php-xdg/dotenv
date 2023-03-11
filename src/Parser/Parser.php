<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\Ast\Assignment;
use Xdg\Dotenv\Parser\Ast\AssignmentList;
use Xdg\Dotenv\Parser\Ast\Expansion;
use Xdg\Dotenv\Parser\Ast\ExpansionOperator;

final class Parser
{
    /**
     * @var \Iterator<int, Token>
     */
    private readonly \Iterator $tokens;

    public function __construct(
        private readonly TokenizerInterface $tokenizer,
    ) {
    }

    public function parse(): AssignmentList
    {
        $this->tokens = $this->tokenizer->tokenize();
        $nodes = [];
        while ($this->tokens->current()->kind !== TokenKind::EOF) {
            $nodes[] = $this->parseAssignment();
        }
        return new AssignmentList($nodes);
    }

    private function parseAssignment(): Assignment
    {
        $token = $this->tokens->current();
        return match ($token->kind) {
            TokenKind::Assign => new Assignment($token->value, $this->parseAssignmentValue()),
            default => throw $this->unexpected($token, TokenKind::Assign),
        };
    }

    /**
     * @return array<string|Expansion>
     */
    private function parseAssignmentValue(): array
    {
        $nodes = [];
        while (true) {
            $this->tokens->next();
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EOF:
                case TokenKind::Assign:
                    return $nodes;
                case TokenKind::Characters:
                    $nodes[] = $token->value;
                    break;
                case TokenKind::SimpleExpansion:
                    $nodes[] = new Expansion($token->value);
                    break;
                case TokenKind::StartExpansion:
                    $op = $this->parseExpansionOperator();
                    $rhs = $this->parseExpansionValue();
                    $nodes[] = new Expansion($token->value, $op, $rhs);
                    break;
                default:
                    throw $this->unexpected(
                        $token,
                        TokenKind::Assign,
                        TokenKind::Characters,
                        TokenKind::SimpleExpansion,
                        TokenKind::StartExpansion,
                    );
            }
        }
    }

    private function parseExpansionOperator(): ExpansionOperator
    {
        $this->tokens->next();
        $token = $this->tokens->current();
        return match ($token->kind) {
            TokenKind::ExpansionOperator => ExpansionOperator::from($token->value),
            default => throw $this->unexpected($token, TokenKind::ExpansionOperator),
        };
    }

    /**
     * @return array<string|Expansion>
     */
    private function parseExpansionValue(): array
    {
        $nodes = [];
        while (true) {
            $this->tokens->next();
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EndExpansion:
                    return $nodes;
                case TokenKind::Characters:
                    $nodes[] = $token->value;
                    break;
                case TokenKind::SimpleExpansion:
                    $nodes[] = new Expansion($token->value);
                    break;
                case TokenKind::StartExpansion:
                    $op = $this->parseExpansionOperator();
                    $rhs = $this->parseExpansionValue();
                    $nodes[] = new Expansion($token->value, $op, $rhs);
                    break;
                default:
                    throw $this->unexpected(
                        $token,
                        TokenKind::Characters,
                        TokenKind::SimpleExpansion,
                        TokenKind::StartExpansion,
                        TokenKind::EndExpansion,
                    );
            }
        }
    }

    private function unexpected(Token $token, TokenKind ...$expected): ParseError
    {
        return ParseError::unexpectedToken($token, $this->tokenizer->getPosition($token->offset), ...$expected);
    }
}
