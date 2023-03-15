<?php declare(strict_types=1);

namespace Xdg\Dotenv\Parser;

final class SourcePosition
{
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {
    }

    public static function fromOffset(string $input, int $offset): self
    {
        // we allow offsets at strlen($input) for EOF tokens.
        $offset = min(\strlen($input), max(0, $offset));
        $lineCount = \substr_count($input, "\n", 0, $offset);
        if ($lineCount === 0) {
            return new self(1, $offset + 1);
        }

        $searchOffset = -(\strlen($input) - $offset);
        if ($input[$offset] ?? '' === "\n") {
            $searchOffset--;
        }
        $lastLineOffset = strrpos($input, "\n", $searchOffset);
        $col = $offset - $lastLineOffset;

        return new self($lineCount + 1, $col);
    }
}
