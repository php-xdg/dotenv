<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Utils;

use Iterator;
use Xdg\Dotenv\Parser\SourcePosition;
use Xdg\Dotenv\Parser\TokenizerInterface;

final class MockTokenizer implements TokenizerInterface
{
    public function __construct(
        public readonly array $tokens,
    ) {
    }

    public function tokenize(): Iterator
    {
        yield from $this->tokens;
    }

    public function getPosition(int $offset): SourcePosition
    {
        return new SourcePosition(0, 0);
    }
}
