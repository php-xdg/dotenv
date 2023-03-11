<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class Expansion
{
    public function __construct(
        public readonly string $name,
        public readonly ExpansionOperator $operator = ExpansionOperator::Minus,
        /** @var array<string|Expansion> $value */
        public readonly array $value = [],
    ) {
    }
}
