<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Specification;

use Xdg\Dotenv\Exception\UndefinedVariable;

final class ReferenceEvaluator
{
    private array $localScope;
    private array $env;
    private bool $override;

    public function evaluate(array $nodes, array $env = [], bool $override = false): array
    {
        $this->localScope = [];
        $this->env = $env;
        $this->override = $override;

        foreach ($nodes as $node) {
            if ($node['kind'] !== 'Assignment') {
                throw $this->unexpectedNode($node);
            }
            $this->evaluateAnAssignment($node);
        }
        return $this->localScope;
    }

    private function evaluateAnAssignment(array $node): void
    {
        $name = $node['name'];
        if (!$this->override && isset($this->env[$name])) {
            $value = $this->env[$name];
        } else {
            $value = $this->evaluateAnExpression($node['value']);
        }
        $this->localScope[$name] = $value;
    }

    private function evaluateAnExpression(array $nodeList): string
    {
        $result = '';
        foreach ($nodeList as $node) {
            $result .= match ($node['kind']) {
                'Characters' => $node['value'],
                'Expansion' => $this->evaluateAnExpansion($node),
                default => throw $this->unexpectedNode($node),
            };
        }
        return $result;
    }

    private function evaluateAnExpansion(array $node): string
    {
        $name = $node['name'];
        $operator = $node['operator'];
        $nodeList = $node['value'];
        $value = $this->resolveAName($name);
        switch ($operator) {
            case '-':
                if ($value === null) {
                    $value = $this->evaluateAnExpression($nodeList);
                }
                break;
            case ':-':
                if ($value === null || $value === '') {
                    $value = $this->evaluateAnExpression($nodeList);
                }
                break;
            case '+':
                if ($value === null) {
                    $value = '';
                } else {
                    $value = $this->evaluateAnExpression($nodeList);
                }
                break;
            case ':+':
                if ($value === null || $value === '') {
                    $value = '';
                } else {
                    $value = $this->evaluateAnExpression($nodeList);
                }
                break;
            case '=':
                if ($value === null) {
                    $value = $this->evaluateAnExpression($nodeList);
                    $this->localScope[$name] = $value;
                }
                break;
            case ':=':
                if ($value === null || $value === '') {
                    $value = $this->evaluateAnExpression($nodeList);
                    $this->localScope[$name] = $value;
                }
                break;
            case '?':
                if ($value === null) {
                    $message = $this->evaluateAnExpression($nodeList);
                    throw UndefinedVariable::of($name, $message);
                }
                break;
            case ':?':
                if ($value === null || $value === '') {
                    $message = $this->evaluateAnExpression($nodeList);
                    throw UndefinedVariable::of($name, $message);
                }
                break;
            default:
                throw new \RuntimeException(sprintf('Unknown expansion operator %s', $operator));
        }
        return $value;
    }

    private function resolveAName(string $name): ?string
    {
        if ($this->override) {
            return $this->localScope[$name] ?? $this->env[$name] ?? null;
        }
        return $this->env[$name] ?? $this->localScope[$name] ?? null;
    }

    private function unexpectedNode(array $node): \Exception
    {
        return new \RuntimeException(sprintf('Unexpected node %s', $node['kind']));
    }
}
