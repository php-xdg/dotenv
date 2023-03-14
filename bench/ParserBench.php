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
    #[ParamProviders(['inputProvider'])]
    public function default($args): void
    {
        $parser = new Parser(new Tokenizer($args[0]));
        $ast = $parser->parse();
    }

    #[Subject]
    #[ParamProviders(['inputProvider'])]
    public function spec($args): void
    {
        $parser = new ReferenceParser(new Tokenizer($args[0]));
        $ast = $parser->parse();
    }

    public static function inputProvider(): iterable
    {
        yield 'resources/big.env' => [file_get_contents(__DIR__.'/resources/big.env')];
    }
}
