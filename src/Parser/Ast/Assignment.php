<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class Assignment
{
    public function __construct(
        public readonly string $name,
        /** @var array<string|Expansion> */
        public readonly array $value = [],
    ) {
    }
}
