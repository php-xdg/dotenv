<?php declare(strict_types=1);

namespace Xdg\Dotenv\Exception;

final class UndefinedVariable extends \RuntimeException implements DotenvException
{
    public static function of(string $key, string $message): self
    {
        if (!$message) {
            return new self(sprintf(
                'Missing required value for variable "%s"',
                $key,
            ));
        }
        return new self($message);
    }
}
