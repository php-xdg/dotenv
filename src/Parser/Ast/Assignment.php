<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class Assignment extends Node
{
    public function __construct(
        public readonly string $key,
        public readonly SimpleValue|CompositeValue|SimpleReference|ComplexReference|null $value,
    ) {
    }
}
