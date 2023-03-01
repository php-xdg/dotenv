<?php declare(strict_types=1);

namespace Xdg\Dotenv\Evaluator;

use Xdg\Dotenv\Exception\UndefinedVariable;
use Xdg\Dotenv\Parser\Ast\Assignment;
use Xdg\Dotenv\Parser\Ast\AssignmentList;
use Xdg\Dotenv\Parser\Ast\ComplexReference;
use Xdg\Dotenv\Parser\Ast\CompositeValue;
use Xdg\Dotenv\Parser\Ast\ExpansionOperator;
use Xdg\Dotenv\Parser\Ast\SimpleReference;
use Xdg\Dotenv\Parser\Ast\SimpleValue;
use Xdg\Environment\EnvironmentProviderInterface;
use Xdg\Environment\Provider\ArrayProvider;

final class Evaluator
{
    private array $scope = [];

    public function __construct(
        private readonly bool $overrideEnv = false,
        private readonly EnvironmentProviderInterface $env = new ArrayProvider([], false),
    ) {}

    public function evaluate(AssignmentList $ast): array
    {
        foreach ($ast->nodes as $assignment) {
            $this->evaluateAssignment($assignment);
        }

        return $this->scope;
    }

    private function evaluateAssignment(Assignment $node): void
    {
        $key = $node->key;
        if (!$this->overrideEnv && null !== $value = $this->env->get($key)) {
            $this->scope[$key] = $value;
            return;
        }
        if ($node->value === null) {
            $this->scope[$key] = $this->resolve($key) ?? '';
            return;
        }
        $this->scope[$key] = $this->evaluateExpression($node->value);
    }

    private function evaluateExpression(SimpleValue|CompositeValue|SimpleReference|ComplexReference $expr): string
    {
        if ($expr instanceof SimpleValue) {
            return $expr->value;
        }
        if ($expr instanceof CompositeValue) {
            $value = '';
            foreach ($expr->nodes as $node) {
                $value .= $this->evaluateExpression($node);
            }
            return $value;
        }
        return $this->evaluateReference($expr);
    }

    private function evaluateReference(SimpleReference|ComplexReference $ref): string
    {
        $key = $ref->id;
        $value = $this->resolve($key);
        if ($ref instanceof SimpleReference) {
            return $value ?? '';
        }
        return match ($ref->op) {
            ExpansionOperator::Minus => match ($value) {
                null => $this->evaluateExpression($ref->rhs),
                default => $value,
            },
            ExpansionOperator::ColonMinus => match ($value) {
                '', null => $this->evaluateExpression($ref->rhs),
                default => $value,
            },
            ExpansionOperator::Equal => match ($value) {
                null => $this->scope[$key] = $this->evaluateExpression($ref->rhs),
                default => $value,
            },
            ExpansionOperator::ColonEqual => match ($value) {
                '', null => $this->scope[$key] = $this->evaluateExpression($ref->rhs),
                default => $value,
            },
            ExpansionOperator::Plus => match ($value) {
                null => '',
                default => $this->evaluateExpression($ref->rhs),
            },
            ExpansionOperator::ColonPlus => match ($value) {
                '', null => '',
                default => $this->evaluateExpression($ref->rhs),
            },
            ExpansionOperator::Question => match ($value) {
                null => throw UndefinedVariable::of($key, $this->evaluateExpression($ref->rhs)),
                default => $value,
            },
            ExpansionOperator::ColonQuestion => match ($value) {
                null, '' => throw UndefinedVariable::of($key, $this->evaluateExpression($ref->rhs)),
                default => $value,
            },
        };
    }

    private function resolve(string $key): ?string
    {
        if ($this->overrideEnv) {
            return $this->scope[$key] ?? $this->env->get($key);
        }
        return $this->env->get($key) ?? $this->scope[$key] ?? null;
    }
}
