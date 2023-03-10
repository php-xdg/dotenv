<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenKind;
use Xdg\Dotenv\Tests\Utils\MockTokenizer;

final class ParserTest extends TestCase
{
    #[DataProvider('parseErrorsProvider')]
    public function testParseErrors(array $tokens): void
    {
        $tokenizer = new MockTokenizer([
            ...$tokens,
            new Token(TokenKind::EOF, '', -1),
        ]);
        $parser = new Parser($tokenizer);
        $this->expectException(ParseError::class);
        $parser->parse();
    }

    public static function parseErrorsProvider(): iterable
    {
        yield 'unexpected token in assignment value' => [
            [
                new Token(TokenKind::Assign, 'foo', 0),
                new Token(TokenKind::ExpansionOperator, '-', 0),
            ],
        ];
        yield 'unexpected token in expansion operator' => [
            [
                new Token(TokenKind::Assign, 'foo', 0),
                new Token(TokenKind::StartExpansion, 'foo', 0),
                new Token(TokenKind::Assign, 'bar', 0),
            ],
        ];
        yield 'unexpected token in expansion arguments' => [
            [
                new Token(TokenKind::Assign, 'foo', 0),
                new Token(TokenKind::StartExpansion, 'foo', 0),
                new Token(TokenKind::ExpansionOperator, ':-', 0),
                new Token(TokenKind::Assign, 'bar', 0),
            ],
        ];
    }
}
