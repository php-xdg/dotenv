<?php declare(strict_types=1);

namespace Xdg\Dotenv\Tests\Parser;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xdg\Dotenv\Exception\ParseError;
use Xdg\Dotenv\Parser\SourcePosition;
use Xdg\Dotenv\Parser\Token;
use Xdg\Dotenv\Parser\Tokenizer;
use Xdg\Dotenv\Parser\TokenKind;
use Xdg\Dotenv\Tests\Specification\ReferenceTokenizer;
use Xdg\Dotenv\Tests\SpecResourceHelper;

final class TokenizerTest extends TestCase
{
    #[DataProvider('specificationProvider')]
    public function testSpecification(TokenizationTestDTO $dto): void
    {
        $tokenizer = new Tokenizer($dto->input);
        if ($dto->error) {
            $this->expectException(ParseError::class);
        }
        $tokens = array_map(
            TokenizationTestDTO::convertToken(...),
            iterator_to_array($tokenizer->tokenize(), false),
        );
        Assert::assertSame($dto->expected, $tokens);
    }

    #[DataProvider('specificationProvider')]
    public function testReferenceTokenizer(TokenizationTestDTO $dto): void
    {
        $tokenizer = new ReferenceTokenizer($dto->input);
        if ($dto->error) {
            $this->expectException(ParseError::class);
        }
        $tokens = array_map(
            TokenizationTestDTO::convertToken(...),
            iterator_to_array($tokenizer->tokenize(), false),
        );
        Assert::assertSame($dto->expected, $tokens);
    }

    public static function specificationProvider(): iterable
    {
        foreach (SpecResourceHelper::glob('tokenization/*.json') as $file) {
            $cases = json_decode(file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
            $fileName = basename($file);
            foreach ($cases as $i => $case) {
                $dto = TokenizationTestDTO::fromJson($case);
                $key = sprintf('%s > %d: %s', $fileName, $i, $dto->desc);
                yield $key => [$dto];
            }
        }
    }

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

    #[DataProvider('tokenOffsetsProvider')]
    public function testTokenOffsets(string $input, array $expected): void
    {
        $tokenizer = new Tokenizer($input);
        $tokens = iterator_to_array($tokenizer->tokenize(), false);
        Assert::assertEquals($expected, $tokens);
    }

    public static function tokenOffsetsProvider(): iterable
    {
        yield [
            "# a\nb=c",
            [
                new Token(TokenKind::Assign, 'b', 4),
                new Token(TokenKind::Characters, 'c', 6),
                new Token(TokenKind::EOF, '', 7),
            ],
        ];
        yield [
            'a=b\'c\'"d"',
            [
                new Token(TokenKind::Assign, 'a', 0),
                new Token(TokenKind::Characters, 'bcd', 2),
                new Token(TokenKind::EOF, '', 9),
            ]
        ];
        yield [
            'a=b"c" b="c"d',
            [
                new Token(TokenKind::Assign, 'a', 0),
                new Token(TokenKind::Characters, 'bc', 2),
                new Token(TokenKind::Assign, 'b', 7),
                new Token(TokenKind::Characters, 'cd', 9),
                new Token(TokenKind::EOF, '', 13),
            ]
        ];
        yield [
            'a=b"$c" b="c"${d}',
            [
                new Token(TokenKind::Assign, 'a', 0),
                new Token(TokenKind::Characters, 'b', 2),
                new Token(TokenKind::SimpleExpansion, 'c', 4),
                new Token(TokenKind::Assign, 'b', 8),
                new Token(TokenKind::Characters, 'c', 10),
                new Token(TokenKind::SimpleExpansion, 'd', 13),
                new Token(TokenKind::EOF, '', 17),
            ]
        ];
    }

    #[DataProvider('errorPositionsProvider')]
    public function testErrorPositions(string $input, int $line, int $col): void
    {
        $this->expectExceptionMessage("on line {$line}, column {$col}");
        $tokenizer = new Tokenizer($input);
        $tokens = iterator_to_array($tokenizer->tokenize(), false);
    }

    public static function errorPositionsProvider(): iterable
    {
        yield 'unterminated single-quoted string' => [
            "A=\nB=foo'bar", 2, 6,
        ];
        yield 'unterminated double-quoted string' => [
            'a=foo"${b-"bar"}', 1, 6,
        ];
        yield 'unterminated expansion' => [
            "A=\nB=\nC=\${foo-\${bar}", 3, 4,
        ];
    }
}
