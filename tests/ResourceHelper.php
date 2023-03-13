<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests;

class ResourceHelper
{
    protected const ROOT = __DIR__ . '/resources';

    public static function path(string $relPath): string
    {
        return static::ROOT . '/' . ltrim($relPath, '/');
    }

    public static function glob(string $pattern): array
    {
        return glob(self::path($pattern), \GLOB_BRACE);
    }

    public static function json(string $relPath): mixed
    {
        $blob = file_get_contents(self::path($relPath));
        return json_decode($blob, true, 512, \JSON_THROW_ON_ERROR);
    }
}
