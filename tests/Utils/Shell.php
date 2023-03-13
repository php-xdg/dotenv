<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Utils;

enum Shell: string {
    case Default = 'sh';
    case Ash = 'ash';
    case Bash = 'bash';
    case Dash = 'dash';
    case Ksh = 'ksh';
    case Mksh = 'mksh';
    case Posh = 'posh';
    case Yash = 'yash';
    case Zsh = 'zsh';

    public function command(string ...$args): array
    {
        $cmd = match ($this) {
            self::Ash =>  ['busybox', $this->value, '-c'],
            self::Bash =>  [$this->value, '--posix', '-c'],
            self::Ksh, self::Mksh =>  [$this->value, '-o', 'posix', '-c'],
            self::Yash =>  [$this->value, '-o', 'posixly-correct', '-c'],
            self::Zsh =>  [$this->value, '--emulate', 'sh', '-c'],
            default => [$this->value, '-c'],
        };
        return [...$cmd, ...$args];
    }
}
