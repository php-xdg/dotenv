<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Utils;

use Xdg\Dotenv\Tests\Evaluator\EvaluationTestDTO;
use Xdg\Dotenv\Tests\ResourceHelper;

final class ShellRunner
{
    private const SCRIPT_TPL = <<<'SHELL'
    set -o allexport
    %3$s

    %2$s
    # newline
    %1$s -r 'echo json_encode(getenv());'
    SHELL;

    private const FILTER_ENV = [
        '_' => false,
        'HOME' => false,
        'LOGNAME' => false,
        'OLDPWD' => false,
        'PWD' => false,
        'PATH' => false,
        'SHLVL' => false,
    ];

    public function runFile(string $path, Shell $shell = Shell::Default): array
    {
        $file = match (str_starts_with($path, '/')) {
            true => $path,
            false => ResourceHelper::path($path),
        };
        $blob = file_get_contents($file);
        $dir = basename(dirname($file));
        $name = basename($file);
        $data = json_decode($blob, true, 512, \JSON_THROW_ON_ERROR);
        $results = [];
        foreach ($data as $i => $datum) {
            $dto = EvaluationTestDTO::fromArray($datum);
            $key = sprintf('%s: %s/%s > %d: %s', $shell->name, $dir, $name, $i, $dto->desc);
            $result = $this->runTest($dto, $shell);
            $results[$key] = $result;
        }
        return $results;
    }

    /**
     * @return array{error: bool, result: array<string, string>}
     */
    public function runTest(EvaluationTestDTO $dto, Shell $shell = Shell::Default): array
    {
        $result = $this->run($dto->input, $dto->env, $shell);
        if ($dto->error) {
            $result['success'] = $result['error'] !== false;
        } else {
            if ($result['error'] !== false) {
                $result['success'] = false;
            } else {
                $diff = array_diff_assoc($dto->expected, $result['result']);
                $result['success'] = empty($diff);
            }
        }

        return $result;
    }

    /**
     * @return array{error: bool, result: array<string, string>}
     */
    public function run(string $input, array $env = [], Shell $shell = Shell::Default): array
    {
        $script = sprintf(self::SCRIPT_TPL, \PHP_BINARY, $input, self::serializeScope($env));
        $p = proc_open($shell->command($script), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, env_vars: $env);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $exitCode = proc_close($p);
        if ($exitCode !== 0 || $stdout === false) {
            return ['error' => trim($stderr), 'result' => []];
        }

        $result = array_filter(
            json_decode($stdout, true, \JSON_THROW_ON_ERROR),
            fn($k) => self::FILTER_ENV[$k] ?? true,
            \ARRAY_FILTER_USE_KEY,
        );
        return ['error' => false, 'result' => $result];
    }

    private static function serializeScope(array $scope): string
    {
        $out = [];
        foreach ($scope as $key => $value) {
            $out[] = sprintf('%s=%s', $key, escapeshellarg($value));
        }
        return implode("\n", $out);
    }
}
