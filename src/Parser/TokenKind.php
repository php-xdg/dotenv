<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

/**
 * @internal
 */
enum TokenKind
{
    case EOF;
    case Characters;
    case Escaped;
    case Newline;
    case Whitespace;
    case Identifier;
    case Hash;
    case Dollar;
    case OpenBrace;
    case CloseBrace;
    case Equal;
    case Plus;
    case Minus;
    case Colon;
    case QuestionMark;
    case DoubleQuote;
    case SingleQuote;
    case Special;

    public static function tryFromChar(string $char): ?self
    {
        return match ($char) {
            '#' => self::Hash,
            '$' => self::Dollar,
            '{' => self::OpenBrace,
            '}' => self::CloseBrace,
            '=' => self::Equal,
            '+' => self::Plus,
            '-' => self::Minus,
            ':' => self::Colon,
            '?' => self::QuestionMark,
            '"' => self::DoubleQuote,
            "'" => self::SingleQuote,
            // https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_02
            '|', '&', ';', '<', '>', '(', ')', '`' => self::Special,
            default => null,
        };
    }
}
