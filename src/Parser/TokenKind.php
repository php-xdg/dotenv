<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

/**
 * @internal
 */
enum TokenKind
{
    case EOF;
    case Characters;
    case Assign;
    case SimpleExpansion;
    case ComplexExpansion;
    case ExpansionOperator;
    case CloseBrace;
}
