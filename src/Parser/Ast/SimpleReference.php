<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class SimpleReference extends Node
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
