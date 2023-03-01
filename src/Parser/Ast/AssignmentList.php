<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

final class AssignmentList extends Node
{
    public function __construct(
        /** @var Assignment[] */
        public readonly array $nodes,
    ) {
    }
}
