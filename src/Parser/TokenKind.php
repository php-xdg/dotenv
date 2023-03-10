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
    case StartExpansion;
    case ExpansionOperator;
    case EndExpansion;
}
