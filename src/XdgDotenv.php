<?php declare(strict_types=1);

namespace Xdg\Dotenv;

use Xdg\Dotenv\Evaluator\Evaluator;
use Xdg\Dotenv\Exception\IOError;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Environment\EnvironmentProviderInterface;
use Xdg\Environment\Provider\ChainProvider;
use Xdg\Environment\Provider\EnvSuperGlobalProvider;
use Xdg\Environment\Provider\GetenvProvider;
use Xdg\Environment\Provider\ServerSuperGlobalProvider;

final class XdgDotenv
{
    public static function load(
        array|string $files,
        bool $override = false,
        ?EnvironmentProviderInterface $env = null,
    ): array {
        $env ??= self::getDefaultProvider();
        $scope = self::evaluate($files, $override, $env);
        foreach ($scope as $key => $value) {
            if ($override || $env->get($key) === null) {
                $env->set($key, $value);
            }
        }
        return $scope;
    }

    public static function evaluate(
        array|string $files,
        bool $override = false,
        ?EnvironmentProviderInterface $env = null,
    ): array {
        if (!\is_array($files)) {
            $files = [$files];
        }
        $env ??= self::getDefaultProvider();
        $evaluator = new Evaluator($override, $env);
        $scope = [];
        foreach ($files as $file) {
            if (false === $input = @file_get_contents($file)) {
                throw new IOError("Failed to read file: {$file}");
            }
            $parser = new Parser(new Tokenizer($input));
            $scope = $evaluator->evaluate($parser->parse());
        }
        return $scope;
    }

    private static function getDefaultProvider(): EnvironmentProviderInterface
    {
        return new ChainProvider(
            new EnvSuperGlobalProvider(false),
            new ServerSuperGlobalProvider(false),
            new GetenvProvider(false),
        );
    }
}
