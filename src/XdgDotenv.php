<?php declare(strict_types=1);

namespace Xdg\Dotenv;

use Xdg\Dotenv\Evaluator\Evaluator;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Source;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Environment\EnvironmentProviderInterface;
use Xdg\Environment\Provider\ChainProvider;
use Xdg\Environment\Provider\EnvSuperGlobalProvider;
use Xdg\Environment\Provider\ReadonlyGetenvProvider;
use Xdg\Environment\Provider\ServerSuperGlobalProvider;

final class XdgDotenv
{
    /**
     * Loads environment variables from the specified set of files,
     * and exports them into the current process's environment.
     *
     * @param string|string[] $files The file path(s) to load.
     * @param bool $override Whether to override existing environment variables.
     * @param EnvironmentProviderInterface|null $env An environment provider, or null to use the default provider.
     * @return array<string, string> The environment variables defined in the specified files.
     */
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

    /**
     * Evaluates the specified set of files,
     * and returns the environment variables defined in those files as an associative array.
     *
     * @param string|string[] $files The file path(s) to load.
     * @param bool $override Whether to override existing environment variables.
     * @param EnvironmentProviderInterface|null $env An environment provider, or null to use the default provider.
     * @return array<string, string> The environment variables defined in the specified files.
     */
    public static function evaluate(
        array|string $files,
        bool $override = false,
        ?EnvironmentProviderInterface $env = null,
    ): array {
        if (!\is_array($files)) {
            $files = [$files];
        }
        $env ??= self::getDefaultProvider();
        $parser = new Parser(new Tokenizer());
        $evaluator = new Evaluator($override, $env);
        $scope = [];
        foreach ($files as $file) {
            $src = Source::fromFile($file);
            $scope = $evaluator->evaluate($parser->parse($src));
        }
        return $scope;
    }

    private static function getDefaultProvider(): EnvironmentProviderInterface
    {
        return new ChainProvider(
            new EnvSuperGlobalProvider(false),
            new ServerSuperGlobalProvider(false),
            new ReadonlyGetenvProvider(false),
        );
    }
}
