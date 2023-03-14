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
        $parser = new Parser(new Tokenizer($args[0]));
        $result = $evaluator->evaluate($parser->parse());
    }

    #[Subject]
    #[ParamProviders(['inputProvider'])]
    public function tokens($args): void
    {
        $evaluator = new TokenEvaluator();
        $result = $evaluator->evaluate(new Tokenizer($args[0]));
    }

    public static function inputProvider(): iterable
    {
        yield 'resources/big.env' => [file_get_contents(__DIR__.'/resources/big.env')];
    }
}
