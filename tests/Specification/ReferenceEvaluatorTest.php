<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Specification;

use Xdg\Dotenv\Tests\Evaluator\EvaluatorTestCase;

final class ReferenceEvaluatorTest extends EvaluatorTestCase
{
    protected static function evaluate(string $input, array $env = [], bool $overrideEnv = false): array
    {
        $evaluator = new ReferenceEvaluator();
        $ast = (new ReferenceParser(new ReferenceTokenizer($input)))->parse();
        return $evaluator->evaluate($ast, $env, $overrideEnv);
    }
}
