<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

final class Token
{
    public function __construct(
        public readonly TokenKind $kind,
        public readonly string $value,
        public readonly int $offset,
    ) {
    }
}
