<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Parser;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Parser\SourcePosition;

final class SourcePositionTest extends TestCase
{
    #[DataProvider('fromOffsetProvider')]
    public function testFromOffset(string $input, int $offset, SourcePosition $expected): void
    {
        $position = SourcePosition::fromOffset($input, $offset);
        Assert::assertEquals($expected, $position);
    }

    public static function fromOffsetProvider(): iterable
    {
        yield 'offset 0' => [
            '', 0, new SourcePosition(1, 1),
        ];
        yield 'offset at EOF is valid' => [
            'abc', 3, new SourcePosition(1, 4),
        ];
        yield 'offset immediately after newline' => [
            "a\nb", 2, new SourcePosition(2, 1),
        ];
        yield 'offset is a newline' => [
            "a\nb\nc", 3, new SourcePosition(2, 2),
        ];
        yield 'negative offset' => [
            'foo', -12, new SourcePosition(1, 1),
        ];
        yield 'offset after EOF' => [
            "foo\nbar", 42, new SourcePosition(2, 4),
        ];
    }
}
