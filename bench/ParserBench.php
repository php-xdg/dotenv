<?php declare(strict_types=1);

namespace Xdg\Dotenv\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputMode;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\RetryThreshold;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Source;
use Xdg\Dotenv\Parser\Tokenizer;

#[RetryThreshold(2.0)]
#[Iterations(10)]
#[Revs(100)]
 #[OutputMode('throughput')]
 #[OutputTimeUnit('seconds')]
final class ParserBench
{
    #[Subject]
    #[ParamProviders(['inputProvider'])]
    public function default($args): void
    {
        $parser = new Parser(new Tokenizer());
        $ast = $parser->parse($args[0]);
    }

    public static function inputProvider(): iterable
    {
        yield 'resources/big.env' => [Source::fromFile(__DIR__.'/resources/big.env')];
    }
}
