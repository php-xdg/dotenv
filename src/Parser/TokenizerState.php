<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

enum TokenizerState
{
    case AssignmentList;
    case AssignmentValue;
    case AssignmentValueEscape;
    case SingleQuoted;
    case DoubleQuoted;
    case DoubleQuotedEscape;
    case Dollar;
    case AfterDollar;
    case AfterDollarOpenBrace;
    case AfterExpansionIdentifier;
    case ExpansionValue;
    case ExpansionValueEscape;
}
