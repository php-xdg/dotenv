<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Parser;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Parser\SourcePosition;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Dotenv\Parser\TokenKind;

final class TokenizerTest extends TestCase
{
    public function testGetPosition(): void
    {
        $tokenizer = new Tokenizer($input = "a\nb\nc");
        $pos = $tokenizer->getPosition(\strlen($input) - 1);
        $expected = new SourcePosition(3, 1);
        Assert::assertEquals($expected, $pos);
    }

    #[DataProvider('tokenizationStopsAfterEOFProvider')]
    public function testTokenizationStopsAfterEOF(string $input, array $expected): void
    {
        $tokenizer = new Tokenizer($input);
        $tokens = iterator_to_array($tokenizer->tokenize(), false);
        Assert::assertEquals($expected, $tokens);
    }

    public static function tokenizationStopsAfterEOFProvider(): iterable
    {
        yield 'assignment list state' => [
            '#empty',
            [new Token(TokenKind::EOF, '', 6)],
        ];
        yield 'assignment value state' => [
            'a=',
            [
                new Token(TokenKind::Assign, 'a', 0),
                new Token(TokenKind::EOF, '', 2),
            ],
        ];
        yield 'assignment value escape state' => [
            'a=\\',
            [
                new Token(TokenKind::Assign, 'a', 0),
                new Token(TokenKind::Characters, '\\', 2),
                new Token(TokenKind::EOF, '', 3),
            ],
        ];
    }
}
