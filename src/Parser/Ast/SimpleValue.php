<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class SimpleValue extends Node
{
    public function __construct(
        public string $value,
    ) {
    }
}
