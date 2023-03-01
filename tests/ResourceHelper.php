<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests;

final class ResourceHelper
{
    public static function path(string $relPath): string
    {
        return __DIR__ . '/resources/' . ltrim($relPath, '/');
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
