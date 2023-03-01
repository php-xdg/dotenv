<?php declare(strict_types=1);

namespace Xdg\Dotenv\Exception;

use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenKind;

final class ParseError extends \RuntimeException implements DotenvException
{
    public static function unexpectedToken(Token $token, TokenKind ...$expectedKinds): self
    {
        $message = sprintf(
            'Unexpected token `%s` ("%s") on line %d, column %d',
            $token->kind->name,
            $token->value,
            $token->line,
            $token->col,
        );
        $message .= match (\count($expectedKinds)) {
            0 => '',
            1 => ', expected: ' . $expectedKinds[0]->name,
            default => 'expected one of: ' . implode(', ', array_map(fn($k) => $k->name, $expectedKinds)),
        };

        return new self($message);
    }
}
