<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Iterator;
use Xdg\Dotenv\Exception\ParseError;

interface TokenizerInterface
{
    /**
     * @return Iterator<int, Token>
     * @throws ParseError
     */
    public function tokenize(): Iterator;

    public function getPosition(int $offset): SourcePosition;
}
