<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Evaluator\Evaluator;
use Xdg\Dotenv\Exception\UndefinedVariable;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Environment\Provider\ArrayProvider;

final class EvaluatorTest extends TestCase
{
    public static function evaluate(string $input, array $env = [], bool $overrideEnv = false): array
    {
        $evaluator = new Evaluator($overrideEnv, new ArrayProvider($env, false));
        $ast = (new Parser(new Tokenizer($input)))->parse();
        return $evaluator->evaluate($ast);
    }

    #[DataProvider('specFilesProvider')]
    public function testSpecFiles(EvaluationTestDTO $dto): void
    {
        if ($dto->error) {
            $this->expectException($dto->error);
        }
        $result = self::evaluate($dto->input, $dto->scope, $dto->override);
        if (!$dto->error) {
            Assert::assertEquals($dto->expected, $result);
        }
    }

    public static function specFilesProvider(): iterable
    {
        foreach (ResourceHelper::glob('{syntax,expansion}/*.json') as $file) {
            $blob = file_get_contents($file);
            $dir = basename(dirname($file));
            $name = basename($file);
            $data = json_decode($blob, true, 512, \JSON_THROW_ON_ERROR);
            foreach ($data as $i => $datum) {
                $dto = EvaluationTestDTO::fromArray($datum);
                $key = sprintf('%s/%s > %d: %s', $dir, $name, $i, $dto->desc);
                yield $key => [$dto];
            }
        }
    }

    #[DataProvider('shellCompatibilityProvider')]
    public function testShellCompatibility(string $input, array $expected): void
    {
        Assert::assertSame($expected, self::evaluate($input));
    }

    public static function shellCompatibilityProvider(): iterable
    {
        foreach (ResourceHelper::json('shell.json') as $i => $test) {
            $input = sprintf("%s\n__TEST__=%s", $test['setup'] ?? '', $test['input']);
            $key = sprintf('#%d %s', $i, $test['desc']);
            yield $key => [
                $input,
                ['__TEST__' => $test['expected']],
            ];
        }
    }

    #[DataProvider('undefinedValueMessagesProvider')]
    public function testUndefinedValueMessages(string $input, string $expected): void
    {
        $this->expectException(UndefinedVariable::class);
        $this->expectExceptionMessage($expected);
        self::evaluate($input);
    }

    public static function undefinedValueMessagesProvider(): iterable
    {
        yield 'message is set' => [
            'a=${foo?"An error message"}',
            'An error message',
        ];
        yield 'message is not set' => [
            'a=${foo?}',
            'Missing required value for variable "foo"',
        ];
    }
}
