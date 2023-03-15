<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

use Xdg\Dotenv\Exception\IOError;

final class Source
{
    private function __construct(
        public readonly string $bytes,
        public readonly string $filename,
    ) {
    }

    public static function fromFile(string $path): self
    {
        return new self(self::read($path), $path);
    }

    public static function fromString(string $bytes): self
    {
        return new self($bytes, '<unknown>');
    }
    public function getPosition(int $offset): SourcePosition
    {
        return SourcePosition::fromOffset($this->bytes, $offset);
    }
    private static function read(string $path): string
    {
        if (false === $contents = @file_get_contents($path)) {
            throw new IOError("Failed to read file: {$path}");
        }
        return $contents;
    }
}
