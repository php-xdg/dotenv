<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Iterator;
use Xdg\Dotenv\Exception\ParseError;

interface TokenizerInterface
{
    /**
     * @param Source $src
     * @return Iterator<int, Token>
     * @throws ParseError
     */
    public function tokenize(Source $src): Iterator;
}
