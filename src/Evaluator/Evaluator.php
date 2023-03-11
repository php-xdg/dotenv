<?php declare(strict_types=1);

namespace Xdg\Dotenv\Evaluator;

use Xdg\Dotenv\Exception\UndefinedVariable;
use Xdg\Dotenv\Parser\Ast\Assignment;
use Xdg\Dotenv\Parser\Ast\AssignmentList;
use Xdg\Dotenv\Parser\Ast\Expansion;
use Xdg\Dotenv\Parser\Ast\ExpansionOperator;
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
        $key = $node->name;
        if (!$this->overrideEnv && null !== $value = $this->env->get($key)) {
            $this->scope[$key] = $value;
            return;
        }
        $this->scope[$key] = $this->evaluateExpression($node->value);
    }

    private function evaluateExpression(array $nodes): string
    {
        $result = '';
        foreach ($nodes as $node) {
            $result .= match (true) {
                \is_string($node) => $node,
                $node instanceof Expansion => $this->evaluateExpansion($node),
            };
        }
        return $result;
    }

    private function evaluateExpansion(Expansion $node): string
    {
        $key = $node->name;
        $value = $this->resolve($key);
        return match ($node->operator) {
            ExpansionOperator::Minus => match ($value) {
                null => $this->evaluateExpression($node->value),
                default => $value,
            },
            ExpansionOperator::ColonMinus => match ($value) {
                '', null => $this->evaluateExpression($node->value),
                default => $value,
            },
            ExpansionOperator::Equal => match ($value) {
                null => $this->scope[$key] = $this->evaluateExpression($node->value),
                default => $value,
            },
            ExpansionOperator::ColonEqual => match ($value) {
                '', null => $this->scope[$key] = $this->evaluateExpression($node->value),
                default => $value,
            },
            ExpansionOperator::Plus => match ($value) {
                null => '',
                default => $this->evaluateExpression($node->value),
            },
            ExpansionOperator::ColonPlus => match ($value) {
                '', null => '',
                default => $this->evaluateExpression($node->value),
            },
            ExpansionOperator::Question => match ($value) {
                null => throw UndefinedVariable::of($key, $this->evaluateExpression($node->value)),
                default => $value,
            },
            ExpansionOperator::ColonQuestion => match ($value) {
                null, '' => throw UndefinedVariable::of($key, $this->evaluateExpression($node->value)),
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
