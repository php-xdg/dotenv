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
use Xdg\Dotenv\Tests\Specification\ReferenceParser;

#[RetryThreshold(2.0)]
#[Iterations(10)]
#[Revs(100)]
#[OutputMode('throughput')]
#[OutputTimeUnit('seconds')]
final class ParserBench
{
    #[Subject]
    public function default(): void
    {
        $input = file_get_contents(__DIR__.'/resources/big.env');
        $parser = new Parser(new Tokenizer($input));
        $ast = $parser->parse();
    }

    #[Subject]
    public function spec(): void
    {
        $input = file_get_contents(__DIR__.'/resources/big.env');
        $parser = new ReferenceParser(new Tokenizer($input));
        $ast = $parser->parse();
    }
}
