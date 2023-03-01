<?php declare(strict_types=1);

use Xdg\Dotenv\Tests\ResourceHelper;

require __DIR__ . '/../vendor/autoload.php';

$dataSet = require ResourceHelper::path('shell-inputs.php');
$results = [];
foreach ($dataSet as $datum) {
    $expected = evaluateInput($datum['input'], $datum['setup'] ?? '');
    $results[] = [
        ...$datum,
        'expected' => $expected,
    ];
}
$blob = json_encode($results, \JSON_PRETTY_PRINT);
file_put_contents(ResourceHelper::path('shell.json'), $blob);

function evaluateInput(string $input, string $setup = ''): string
{
    $id = uniqid();
    $script = <<<SHELL
    {$setup}
    __TEST_{$id}__={$input}
    printf '%s' "\${__TEST_{$id}__}"
    SHELL;
    $args = ['/bin/sh', '-c', $script];
    $p = proc_open($args, [1 => ['pipe', 'w']], $pipes, env_vars: []);
    $output = stream_get_contents($pipes[1]);
    if (proc_close($p) !== 0 || $output === false) {
        throw new \RuntimeException(sprintf(
            'Error running script with input: "%s"',
            $input,
        ));
    }
    return $output;
}
