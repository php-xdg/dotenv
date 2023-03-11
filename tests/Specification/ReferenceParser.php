<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Specification;

use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenizerInterface;
use Xdg\Dotenv\Parser\TokenKind;

final class ReferenceParser
{
    /** @var \Iterator<int, Token> */
    private readonly \Iterator $tokens;
    public function __construct(
        private readonly TokenizerInterface $tokenizer,
    ) {
    }

    public function parse(): array
    {
        $this->tokens = $this->tokenizer->tokenize();
        $nodes = [];
        while (true) {
            switch ($this->tokens->current()->kind) {
                case TokenKind::EOF:
                    return $nodes;
                default:
                    $nodes[] = $this->parseAnAssignment();
            }
        }
    }

    private function parseAnAssignment(): array
    {
        $token = $this->tokens->current();
        return match ($token->kind) {
            TokenKind::Assign => [
                'kind' => 'Assignment',
                'name' => $token->value,
                'value' => $this->parseAnAssignmentValue(),
            ],
            default => throw $this->unexpected($token, TokenKind::Assign),
        };
    }

    private function parseAnAssignmentValue(): array
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
                    $nodes[] = ['kind' => 'Characters', 'value' => $token->value];
                    break;
                case TokenKind::SimpleExpansion:
                    $nodes[] = [
                        'kind' => 'Expansion',
                        'name' => $token->value,
                        'operator' => '-',
                        'value' => [],
                    ];
                    break;
                case TokenKind::StartExpansion:
                    $nodes[] = [
                        'kind' => 'Expansion',
                        'name' => $token->value,
                        'operator' => $this->parseAnExpansionOperator(),
                        'value' => $this->parseAnExpansionValue(),
                    ];
                    break;
                default:
                    throw $this->unexpected($token);
            }
        }
    }

    private function parseAnExpansionOperator(): string
    {
        $this->tokens->next();
        $token = $this->tokens->current();
        if ($token->kind === TokenKind::ExpansionOperator) {
            return $token->value;
        }
        throw $this->unexpected($token, TokenKind::ExpansionOperator);
    }

    private function parseAnExpansionValue(): array
    {
        $nodes = [];
        while (true) {
            $this->tokens->next();
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EndExpansion:
                    return $nodes;
                case TokenKind::Characters:
                    $nodes[] = ['kind' => 'Characters', 'value' => $token->value];
                    break;
                case TokenKind::SimpleExpansion:
                    $nodes[] = [
                        'kind' => 'Expansion',
                        'name' => $token->value,
                        'operator' => '-',
                        'value' => [],
                    ];
                    break;
                case TokenKind::StartExpansion:
                    $nodes[] = [
                        'kind' => 'Expansion',
                        'name' => $token->value,
                        'operator' => $this->parseAnExpansionOperator(),
                        'value' => $this->parseAnExpansionValue(),
                    ];
                    break;
                default:
                    throw $this->unexpected($token);
            }
        }
    }

    private function unexpected(Token $token, TokenKind ...$expected): ParseError
    {
        return ParseError::unexpectedToken(
            $token,
            $this->tokenizer->getPosition($token->offset),
            ...$expected,
        );
    }
}
