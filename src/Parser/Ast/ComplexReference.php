<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class ComplexReference extends Node
{
    public function __construct(
        public readonly string $id,
        public readonly ExpansionOperator $op,
        public SimpleValue|CompositeValue|SimpleReference|self $rhs,
    ) {
    }
}
