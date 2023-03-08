<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

enum TokenizerState
{
    case Initial;
    case Unquoted;
    case SingleQuoted;
    case DoubleQuoted;
    case Dollar;
    case AfterDollar;
    case AfterDollarOpenBrace;
    case AfterExpansionIdentifier;
    case ExpansionArguments;
}
