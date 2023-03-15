<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Evaluator;

use Xdg\Dotenv\Evaluator\Evaluator;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Source;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Environment\Provider\ArrayProvider;

final class EvaluatorTest extends EvaluatorTestCase
{
    protected static function evaluate(string $input, array $env = [], bool $overrideEnv = false): array
    {
        $evaluator = new Evaluator($overrideEnv, new ArrayProvider($env, false));
        $parser = new Parser(new Tokenizer());
        $ast = $parser->parse(Source::fromString($input));
        return $evaluator->evaluate($ast);
    }
}
