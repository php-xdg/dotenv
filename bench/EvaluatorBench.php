<?php declare(strict_types=1);

namespace Xdg\Dotenv\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputMode;
use PhpBench\Attributes\OutputTimeUnit;
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
    public function ast(): void
    {
        $input = file_get_contents(__DIR__.'/resources/big.env');
        $evaluator = new Evaluator();
        $parser = new Parser(new Tokenizer($input));
        $result = $evaluator->evaluate($parser->parse());
    }

    #[Subject]
    public function tokens(): void
    {
        $input = file_get_contents(__DIR__.'/resources/big.env');
        $evaluator = new TokenEvaluator();
        $result = $evaluator->evaluate(new Tokenizer($input));
    }
}
