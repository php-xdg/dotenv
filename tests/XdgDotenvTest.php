<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Exception\IOError;
use Xdg\Dotenv\XdgDotenv;
use Xdg\Environment\Provider\ArrayProvider;

final class XdgDotenvTest extends TestCase
{
    #[DataProvider('loadProvider')]
    public function testLoad(array $files, array $env, bool $override, array $expected): void
    {
        $files = array_map(ResourceHelper::path(...), $files);
        $env = new ArrayProvider($env, false);
        XdgDotenv::load($files, $override, $env);
        $expected = new ArrayProvider($expected, false);
        Assert::assertEquals($expected, $env);
    }

    public static function loadProvider(): iterable
    {
        yield 'modifies environment when a variable is not defined' => [
            ['env/000.env'],
            [], false,
            ['FOO' => 'foo', 'BAR' => 'foobar'],
        ];
        yield 'does not modify environment when a variable is defined' => [
            ['env/000.env'],
            ['FOO' => 'nope'], false,
            ['FOO' => 'nope', 'BAR' => 'nopebar'],
        ];
        yield 'modifies environment when a variable is defined and override is true' => [
            ['env/000.env'],
            ['FOO' => 'nope'], true,
            ['FOO' => 'foo', 'BAR' => 'foobar'],
        ];
    }

    public function testEvaluateMergesScopeFromFiles(): void
    {
        $env = new ArrayProvider([], false);
        $files = [
            ResourceHelper::path('env/000.env'),
            ResourceHelper::path('env/001.env'),
        ];
        $scope = XdgDotenv::evaluate($files, false, $env);
        $expected = [
            'FOO' => 'foo',
            'BAR' => 'foobar',
            'BAZ' => 'foobarbaz',
        ];
        Assert::assertSame($expected, $scope);
    }

    public function testIOError(): void
    {
        $this->expectException(IOError::class);
        $name = ResourceHelper::path(uniqid('i-dont-exist-'));
        XdgDotenv::evaluate($name);
    }
}
