<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Utils;

use Iterator;
use Xdg\Dotenv\Parser\Source;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenizerInterface;

final class MockTokenizer implements TokenizerInterface
{
    public function __construct(
        public readonly array $tokens,
    ) {
    }

    public function tokenize(Source $src): Iterator
    {
        yield from $this->tokens;
    }

    public function toSource(): Source
    {
        return Source::fromString(
            implode('', array_map(fn(Token $t) => $t->value, $this->tokens))
        );
    }
}
