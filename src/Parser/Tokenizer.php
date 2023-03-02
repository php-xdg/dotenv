<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

/**
 * @internal
 */
final class Tokenizer
{
    private int $pos = -1;
    private int $line = 1;
    private int $col = 0;

    private const IDENT_RX = '/[A-Za-z_][A-Za-z0-9_]*/A';

    private const WS_RX = '/[ \t]+/A';

    /**
     * This pattern MUST NOT match any character that can start a token
     * other than TokenKind.Characters.
     */
    private const CHARACTERS_RX = <<<'REGEXP'
    /
        [^\\#\n\x20\tA-Za-z_${}=+:?"'|&;<>()`-]+
    /Ax
    REGEXP;

    public function __construct(
        public readonly string $input,
    ) {
    }

    public function next(): Token
    {
        $this->advance();
        switch ($cc = $this->input[$this->pos] ?? '') {
            case '':
                return $this->make(TokenKind::EOF, '');
            case '\\':
                $cn = $this->input[$this->pos + 1] ?? null;
                if ($cn !== null) {
                    $token = $this->make(TokenKind::Escaped, $cn);
                    $this->advance();
                    return $token;
                }
                return $this->make(TokenKind::Characters, '\\');
            case ' ':
            case "\t":
                return $this->consumeWhitespace();
            case "\n":
                $token = $this->make(TokenKind::Newline, "\n");
                $this->newline();
                return $token;
            default:
                if ($kind = TokenKind::tryFromChar($cc)) {
                    return $this->make($kind, $cc);
                }
                if ($token = $this->matchIdentifier()) {
                    return $token;
                }
                return $this->consumeCharacters();
        }
    }

    private function matchIdentifier(): ?Token
    {
        if (!preg_match(self::IDENT_RX, $this->input, $m, 0, $this->pos)) {
            return null;
        }
        $token = $this->make(TokenKind::Identifier, $m[0]);
        $this->advance(\strlen($m[0]) - 1);
        return $token;
    }

    private function consumeWhitespace(): Token
    {
        preg_match(self::WS_RX, $this->input, $m, 0, $this->pos);
        $token = $this->make(TokenKind::Whitespace, $m[0]);
        $this->advance(\strlen($m[0]) - 1);
        return $token;
    }

    private function consumeCharacters(): Token
    {
        preg_match(self::CHARACTERS_RX, $this->input, $m, 0, $this->pos);
        $token = $this->make(TokenKind::Characters, $m[0]);
        $this->advance(\strlen($m[0]) - 1);
        return $token;
    }

    private function advance(int $n = 1): void
    {
        $this->pos += $n;
        $this->col += $n;
    }

    private function newline(): void
    {
        ++$this->line;
        $this->col = 0;
    }

    private function make(TokenKind $kind, string $value): Token
    {
        return new Token($kind, $value, $this->line, $this->col);
    }
}