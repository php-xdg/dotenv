<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser\Ast;

enum ExpansionOperator: string
{
    case Minus = '-';
    case ColonMinus = ':-';
    case Equal = '=';
    case ColonEqual = ':=';
    case Plus = '+';
    case ColonPlus = ':+';
    case Question = '?';
    case ColonQuestion = ':?';
}
