<?php declare(strict_types=1);

namespace Xdg\Dotenv\Evaluator;

use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Exception\UndefinedVariable;
use Xdg\Dotenv\Parser\Source;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenizerInterface;
use Xdg\Dotenv\Parser\TokenKind;
use Xdg\Environment\EnvironmentProviderInterface;
use Xdg\Environment\Provider\ArrayProvider;

/**
 * An evaluator operating directly on a token stream, skipping the parsing phase completely.
 * This gives us a speedup of around 5% to 10%.
 *
 * TODO: At some point we might want to switch the evaluation specification to this algorithm.
 */
final class TokenEvaluator
{
    private Source $src;
    private array $scope;
    /**
     * @var \Iterator<int, Token>
     */
    private \Iterator $tokens;

    public function __construct(
        private readonly TokenizerInterface $tokenizer,
        private readonly bool $overrideEnv = false,
        private readonly EnvironmentProviderInterface $env = new ArrayProvider([], false),
    ) {
    }

    public function evaluate(Source $src): array
    {
        $this->src = $src;
        $this->scope = [];
        $this->tokens = $this->tokenizer->tokenize($src);
        while (true) {
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EOF:
                    return $this->scope;
                case TokenKind::Assign:
                    $key = $token->value;
                    if (!$this->overrideEnv && null !== $value = $this->env->get($key)) {
                        $this->scope[$key] = $this->skipCurrentAssignment($value);
                        break;
                    }
                    $this->scope[$key] = $this->evaluateAssignmentValue();
                    break;
                default:
                    throw $this->unexpected($token, TokenKind::Assign, TokenKind::EOF);
            }
        }
    }

    private function evaluateAssignmentValue(): string
    {
        $result = '';
        while (true) {
            $this->tokens->next();
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EOF:
                case TokenKind::Assign:
                    return $result;
                case TokenKind::Characters:
                    $result .= $token->value;
                    break;
                case TokenKind::SimpleExpansion:
                    $result .= $this->resolve($token->value) ?? '';
                    break;
                case TokenKind::StartExpansion:
                    $result .= $this->evaluateExpansion();
                    break;
                default:
                    throw $this->unexpected(
                        $token,
                        TokenKind::Characters,
                        TokenKind::SimpleExpansion,
                        TokenKind::StartExpansion,
                        TokenKind::Assign,
                        TokenKind::EOF,
                    );
            }
        }
    }

    private function evaluateExpansion(): string
    {
        $name = $this->tokens->current()->value;
        $value = $this->resolve($name);
        $operator = $this->parseOperator();
        return match ($operator) {
            '-' => match ($value) {
                null => $this->evaluateExpansionValue(),
                default => $this->skipCurrentExpansion($value),
            },
            ':-' => match ($value) {
                null, '' => $this->evaluateExpansionValue(),
                default => $this->skipCurrentExpansion($value),
            },
            '=' => match ($value) {
                null => $this->scope[$name] = $this->evaluateExpansionValue(),
                default => $this->skipCurrentExpansion($value),
            },
            ':=' => match ($value) {
                '', null => $this->scope[$name] = $this->evaluateExpansionValue(),
                default => $this->skipCurrentExpansion($value),
            },
            '+' => match ($value) {
                null => $this->skipCurrentExpansion(''),
                default => $this->evaluateExpansionValue(),
            },
            ':+' => match ($value) {
                '', null => $this->skipCurrentExpansion(''),
                default => $this->evaluateExpansionValue(),
            },
            '?' => match ($value) {
                null => throw UndefinedVariable::of($name, $this->evaluateExpansionValue()),
                default => $this->skipCurrentExpansion($value),
            },
            ':?' => match ($value) {
                null, '' => throw UndefinedVariable::of($name, $this->evaluateExpansionValue()),
                default => $this->skipCurrentExpansion($value),
            },
        };
    }

    private function evaluateExpansionValue(): string
    {
        $result = '';
        while (true) {
            $this->tokens->next();
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EndExpansion:
                    return $result;
                case TokenKind::Characters:
                    $result .= $token->value;
                    break;
                case TokenKind::SimpleExpansion:
                    $result .= $this->resolve($token->value) ?? '';
                    break;
                case TokenKind::StartExpansion:
                    $result .= $this->evaluateExpansion();
                    break;
                default:
                    throw $this->unexpected(
                        $token,
                        TokenKind::EndExpansion,
                        TokenKind::Characters,
                        TokenKind::SimpleExpansion,
                        TokenKind::StartExpansion,
                    );
            }
        }
    }

    private function parseOperator(): string
    {
        $this->tokens->next();
        $token = $this->tokens->current();
        if ($token->kind !== TokenKind::ExpansionOperator) {
            throw $this->unexpected($token, TokenKind::ExpansionOperator);
        }
        return $token->value;
    }

    private function resolve(string $key): ?string
    {
        if ($this->overrideEnv) {
            return $this->scope[$key] ?? $this->env->get($key);
        }
        return $this->env->get($key) ?? $this->scope[$key] ?? null;
    }

    private function skipCurrentAssignment(string $value): string
    {
        while (true) {
            $this->tokens->next();
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::EOF:
                case TokenKind::Assign:
                    return $value;
                default:
                    break;
            }
        }
    }

    private function skipCurrentExpansion(string $value): string
    {
        $nestingLevel = 0;
        while (true) {
            $this->tokens->next();
            $token = $this->tokens->current();
            switch ($token->kind) {
                case TokenKind::StartExpansion:
                    ++$nestingLevel;
                    break;
                case TokenKind::EndExpansion:
                    if ($nestingLevel === 0) {
                        return $value;
                    }
                    --$nestingLevel;
                    break;
                case TokenKind::EOF:
                    throw ParseError::in($this->src, $token->offset, 'Unterminated expansion.');
                default:
                    break;
            }
        }
    }

    private function unexpected(Token $token, TokenKind ...$expected): ParseError
    {
        return ParseError::unexpectedToken($this->src, $token, ...$expected);
    }
}
