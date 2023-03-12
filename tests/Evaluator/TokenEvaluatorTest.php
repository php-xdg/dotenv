<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Evaluator;

use PHPUnit\Framework\Attributes\DataProvider;
use Xdg\Dotenv\Evaluator\TokenEvaluator;
use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Dotenv\Parser\TokenKind;
use Xdg\Dotenv\Tests\Utils\MockTokenizer;
use Xdg\Environment\Provider\ArrayProvider;

final class TokenEvaluatorTest extends EvaluatorTestCase
{
    protected static function evaluate(string $input, array $env = [], bool $overrideEnv = false): array
    {
        $evaluator = new TokenEvaluator($overrideEnv, new ArrayProvider($env, false));
        return $evaluator->evaluate(new Tokenizer($input));
    }

    #[DataProvider('parseErrorsProvider')]
    public function testParseErrors(array $tokens): void
    {
        $this->expectException(ParseError::class);
        (new TokenEvaluator())->evaluate(new MockTokenizer($tokens));
    }

    public static function parseErrorsProvider(): iterable
    {
        yield 'unexpected token in top-level' => [
            [new Token(TokenKind::Characters, 'foo', 0)],
        ];
        yield 'unexpected token in assignment value' => [
            [
                new Token(TokenKind::Assign, 'foo', 0),
                new Token(TokenKind::EndExpansion, '}', 3),
            ],
        ];
        yield 'unexpected token in parse operator' => [
            [
                new Token(TokenKind::Assign, 'foo', 0),
                new Token(TokenKind::StartExpansion, 'bar', 3),
                new Token(TokenKind::Assign, 'baz', 6),
            ],
        ];
        yield 'unexpected token in expansion value' => [
            [
                new Token(TokenKind::Assign, 'foo', 0),
                new Token(TokenKind::StartExpansion, 'bar', 3),
                new Token(TokenKind::ExpansionOperator, ':-', 6),
                new Token(TokenKind::Assign, 'baz', 8),
            ],
        ];
        yield 'unexpected EOF while skipping expansion' => [
            [
                new Token(TokenKind::Assign, 'foo', 0),
                new Token(TokenKind::StartExpansion, 'bar', 3),
                new Token(TokenKind::ExpansionOperator, '+', 6),
                new Token(TokenKind::EOF, 'baz', 8),
            ],
        ];
    }
}
