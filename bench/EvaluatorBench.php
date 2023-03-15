<?php declare(strict_types=1);

namespace Xdg\Dotenv\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputMode;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\RetryThreshold;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use Xdg\Dotenv\Evaluator\Evaluator;
use Xdg\Dotenv\Evaluator\TokenEvaluator;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Source;
use Xdg\Dotenv\Parser\Tokenizer;

#[RetryThreshold(2.0)]
#[Iterations(10)]
#[Revs(100)]
#[OutputMode('throughput')]
#[OutputTimeUnit('seconds')]
final class EvaluatorBench
{
    #[Subject]
    #[ParamProviders(['inputProvider'])]
    public function ast($args): void
    {
        $evaluator = new Evaluator();
        $parser = new Parser(new Tokenizer());
        $result = $evaluator->evaluate($parser->parse($args[0]));
    }

    #[Subject]
    #[ParamProviders(['inputProvider'])]
    public function tokens($args): void
    {
        $evaluator = new TokenEvaluator(new Tokenizer());
        $result = $evaluator->evaluate($args[0]);
    }

    public static function inputProvider(): iterable
    {
        yield 'resources/big.env' => [Source::fromFile(__DIR__.'/resources/big.env')];
    }
}
