# xdg/dotenv

[![codecov](https://codecov.io/gh/php-xdg/dotenv/branch/main/graph/badge.svg?token=QE672UK2ZG)](https://codecov.io/gh/php-xdg/dotenv)

PHP implementation of the [POSIX-compliant dotenv file format specification](https://github.com/php-xdg/dotenv-spec).

## Installation

```sh
composer require xdg/dotenv
```

## Usage

Loading environment variables from a set of dotenv files:

```php
use Xdg\Dotenv\XdgDotenv;

$env = XdgDotenv::load([
    __DIR__ . '/.env',
    __DIR__ . '/.env.local',
]);
// $env is an associative array containing the loaded variables.
var_dump($env);
```

If you want to evaluate the dotenv files without loading them into the environment,
use the following:

```php
use Xdg\Dotenv\XdgDotenv;

$env = XdgDotenv::evaluate([
    __DIR__ . '/.env',
    __DIR__ . '/.env.local',
]);
// $env is an associative array containing the loaded variables.
var_dump($env);
```
