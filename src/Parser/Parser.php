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
        $name = $this->expect(TokenKind::Assign)->value;
        switch ($this->tokens->current()->kind) {
            case TokenKind::EOF:
            case TokenKind::Assign:
                return new Assignment($name, null);
            default:
                $value = $this->parseAssignmentValue();
                return new Assignment($name, $value);
        }
    }

    private function parseAssignmentValue(): SimpleValue|CompositeValue|SimpleReference|ComplexReference
    {
        $nodes = [];
        while (true) {
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EOF:
                case TokenKind::Assign:
                    return self::createValue($nodes);
                case TokenKind::Characters:
                    $this->tokens->next();
                    $nodes[] = new SimpleValue($token->value);
                    break;
                case TokenKind::SimpleExpansion:
                    $this->tokens->next();
                    $nodes[] = new SimpleReference($token->value);
                    break;
                case TokenKind::ComplexExpansion:
                    $this->tokens->next();
                    $op = $this->expect(TokenKind::ExpansionOperator)->value;
                    $rhs = $this->parseExpansionArguments();
                    $nodes[] = new ComplexReference($token->value, ExpansionOperator::from($op), $rhs);
                    break;
                default:
                    throw ParseError::unexpectedToken(
                        $token,
                        $this->tokenizer->getPosition($token->offset),
                        TokenKind::Characters,
                        TokenKind::SimpleExpansion,
                        TokenKind::ComplexExpansion,
                    );
            }
        }
    }

    private function parseExpansionArguments(): SimpleValue|CompositeValue|SimpleReference|ComplexReference
    {
        $nodes = [];
        while (true) {
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::CloseBrace:
                    $this->tokens->next();
                    return self::createValue($nodes);
                case TokenKind::Characters:
                    $this->tokens->next();
                    $nodes[] = new SimpleValue($token->value);
                    break;
                case TokenKind::SimpleExpansion:
                    $this->tokens->next();
                    $nodes[] = new SimpleReference($token->value);
                    break;
                case TokenKind::ComplexExpansion:
                    $this->tokens->next();
                    $op = $this->expect(TokenKind::ExpansionOperator)->value;
                    $rhs = $this->parseExpansionArguments();
                    $nodes[] = new ComplexReference($token->value, ExpansionOperator::from($op), $rhs);
                    break;
                default:
                    throw ParseError::unexpectedToken(
                        $token,
                        $this->tokenizer->getPosition($token->offset),
                        TokenKind::Characters,
                        TokenKind::SimpleExpansion,
                        TokenKind::ComplexExpansion,
                        TokenKind::CloseBrace,
                    );
            }
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

    private function expect(TokenKind $kind): Token
    {
        $token = $this->tokens->current();
        if ($token->kind !== $kind) {
            throw ParseError::unexpectedToken($token, $this->tokenizer->getPosition($token->offset), $kind);
        }
        $this->tokens->next();
        return $token;
    }
}
