<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Evaluator;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Exception\UndefinedVariable;
use Xdg\Dotenv\Tests\ResourceHelper;

abstract class EvaluatorTestCase extends TestCase
{
    abstract protected static function evaluate(string $input, array $env = [], bool $overrideEnv = false): array;

    #[DataProvider('specFilesProvider')]
    public function testSpecFiles(EvaluationTestDTO $dto): void
    {
        if ($dto->error) {
            $this->expectException($dto->error);
        }
        $result = static::evaluate($dto->input, $dto->env, $dto->override);
        if (!$dto->error) {
            Assert::assertEquals($dto->expected, $result);
        }
    }

    public static function specFilesProvider(): iterable
    {
        foreach (ResourceHelper::glob('evaluation/*/*.json') as $file) {
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
        Assert::assertSame($expected, static::evaluate($input));
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
        static::evaluate($input);
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

    #[DataProvider('unescapedSpecialCharsProvider')]
    public function testUnescapedSpecialChars(string $input): void
    {
        $this->expectException(ParseError::class);
        static::evaluate($input);
    }

    public static function unescapedSpecialCharsProvider(): iterable
    {
        // https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_02
        $chars = ['|', '&', ';', '<', '>', '(', ')', '`'];
        foreach ($chars as $c) {
            $key = sprintf('Unescaped "%s" in unquoted string', $c);
            yield $key => [
                sprintf('a=a%sb', $c),
            ];
        }
    }
}
