<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

enum TokenizerState
{
    case AssignmentList;
    case Comment;
    case AssignmentName;
    case AssignmentValue;
    case AssignmentValueEscape;
    case SingleQuoted;
    case DoubleQuoted;
    case DoubleQuotedEscape;
    case Dollar;
    case SimpleExpansion;
    case ComplexExpansionStart;
    case ComplexExpansion;
    case ExpansionOperator;
    case ExpansionValue;
    case ExpansionValueEscape;
}
