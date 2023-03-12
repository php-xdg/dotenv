<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Parser;

use Xdg\Dotenv\Parser\Token;

final class TokenizationTestDTO
{
    public function __construct(
        public readonly string $desc,
        public readonly string $input,
        public readonly array $expected = [],
        public readonly ?string $error = null,
    ) {
    }

    public static function fromJson(array $test): self
    {
        return new self(...$test);
    }

    /**
     * Converts a Token object to the format expected by tokenization tests.
     *
     * @return array{kind: string, value: string}
     */
    public static function convertToken(Token $token): array
    {
        return [
            'kind' => $token->kind->name,
            'value' => $token->value,
        ];
    }
}
