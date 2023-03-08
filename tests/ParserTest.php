<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\Parser;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\TokenizerInterface;
use Xdg\Dotenv\Parser\TokenKind;

final class ParserTest extends TestCase
{
    #[DataProvider('parseErrorsProvider')]
    public function testParseErrors(array $tokens): void
    {
        $tokenizer = $this->createMock(TokenizerInterface::class);
        $tokenizer->method('tokenize')->willReturnCallback(static function() use ($tokens) {
            yield from $tokens;
            yield new Token(TokenKind::EOF, '', -1, -1);
        });
        $parser = new Parser($tokenizer);
        $this->expectException(ParseError::class);
        $parser->parse();
    }

    public static function parseErrorsProvider(): iterable
    {
        yield 'unexpected token in assignment value' => [
            [
                new Token(TokenKind::Assign, 'foo', 0, 0),
                new Token(TokenKind::ExpansionOperator, 'foo', 0, 0),
            ],
        ];
        yield 'unexpected token in expansion operator' => [
            [
                new Token(TokenKind::Assign, 'foo', 0, 0),
                new Token(TokenKind::ComplexExpansion, 'foo', 0, 0),
                new Token(TokenKind::Assign, 'bar', 0, 0),
            ],
        ];
        yield 'unexpected token in expansion arguments' => [
            [
                new Token(TokenKind::Assign, 'foo', 0, 0),
                new Token(TokenKind::ComplexExpansion, 'foo', 0, 0),
                new Token(TokenKind::ExpansionOperator, ':-', 0, 0),
                new Token(TokenKind::Assign, 'bar', 0, 0),
            ],
        ];
    }
}
