<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Iterator;

interface TokenizerInterface
{
    /**
     * @return Iterator<int, Token>
     */
    public function tokenize(): Iterator;

    public function getPosition(int $offset): SourcePosition;
}
