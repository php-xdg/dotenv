<?php declare(strict_types=1);

namespace Xdg\Dotenv\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputMode;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\RetryThreshold;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Tokenizer;

#[RetryThreshold(2.0)]
final class ParserBench
{
    #[Subject]
    #[Iterations(10)]
    #[Revs(100)]
    #[OutputMode('throughput')]
    #[OutputTimeUnit('seconds')]
    public function parse(): void
    {
        $input = file_get_contents(__DIR__.'/resources/big.env');
        $parser = new Parser(new Tokenizer($input));
        $ast = $parser->parse();
    }
}
