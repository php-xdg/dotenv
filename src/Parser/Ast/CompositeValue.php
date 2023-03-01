<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class CompositeValue extends Node
{
    public function __construct(
        /** @var array<SimpleValue|CompositeValue|SimpleReference|ComplexReference> */
        public readonly array $nodes,
    ) {
    }
}
