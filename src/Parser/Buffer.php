<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

final class Buffer
{
    public string $value = '';
    public function __construct(
        public int $line,
        public int $col,
    ) {
    }
}
