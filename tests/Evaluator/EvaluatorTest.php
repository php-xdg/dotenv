<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Evaluator;

use Xdg\Dotenv\Evaluator\Evaluator;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Environment\Provider\ArrayProvider;

final class EvaluatorTest extends EvaluatorTestCase
{
    protected static function evaluate(string $input, array $env = [], bool $overrideEnv = false): array
    {
        $evaluator = new Evaluator($overrideEnv, new ArrayProvider($env, false));
        $ast = (new Parser(new Tokenizer($input)))->parse();
        return $evaluator->evaluate($ast);
    }
}
