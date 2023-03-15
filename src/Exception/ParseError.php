<?php declare(strict_types=1);

namespace Xdg\Dotenv\Exception;

use Xdg\Dotenv\Parser\Source;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenKind;

final class ParseError extends \RuntimeException implements DotenvException
{
    public static function in(Source $src, int $offset, string $message): self
    {
        $pos = $src->getPosition($offset);
        return new self(
            $message . " in {$src->filename} on line {$pos->line}, column {$pos->column}."
        );
    }

    public static function unexpectedToken(Source $src, Token $token, TokenKind ...$expectedKinds): self
    {
        $message = sprintf(
            'Unexpected token `%s` ("%s")',
            $token->kind->name,
            $token->value,
        );
        $message .= match (\count($expectedKinds)) {
            0 => '',
            1 => ', expected: ' . $expectedKinds[0]->name,
            default => ', expected one of: ' . implode(', ', array_map(fn($k) => $k->name, $expectedKinds)),
        };

        return self::in($src, $token->offset, $message);
    }
}
