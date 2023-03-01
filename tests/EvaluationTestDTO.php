<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests;

use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Exception\UndefinedVariable;

final class EvaluationTestDTO
{
    public function __construct(
        public readonly string $desc,
        public readonly string $input,
        public readonly array $scope = [],
        public readonly bool $override = false,
        public readonly array $expected = [],
        public readonly ?string $error = null,
    ) {
    }

    public static function fromArray(array $test): self
    {
        $test['error'] = match ($test['error'] ?? null) {
            null => null,
            'ParseError', true => ParseError::class,
            'UndefinedVariable' => UndefinedVariable::class,
        };
        return new self(...$test);
    }
}
