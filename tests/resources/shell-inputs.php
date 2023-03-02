<?php declare(strict_types=1);

return [
    [
        'desc' => 'unknown escaped char in unquoted value',
        'input' => 'a\\b',
    ],
    [
        'desc' => 'unknown escaped char in double-quoted value',
        'input' => '"a\\b"',
    ],
    [
        'desc' => 'unquoted, concatenate quoting styles',
        'input' => 'a\'b\'"c"$\'d\'$"e"',
    ],
    [
        'desc' => 'quoted, concatenate quoting styles',
        'input' => '"a\'b\'$\'c\'$\\"d\\""',
    ],
    [
        'desc' => 'unquoted, line continuation',
        'input' => "a\\\nb",
    ],
    [
        'desc' => 'double-quoted, line continuation + whitespace',
        'input' => "\"a\\\n  b\"",
    ],
    [
        'desc' => 'single-quoted, line continuation + whitespace',
        'input' => "'a\\\n  b'",
    ],
    [
        'desc' => 'whitespace in unquoted expansion',
        'input' => "\${NOPE:-foo  bar}"
    ],
    [
        'desc' => 'line continuation in unquoted expansion',
        'input' => "\${NOPE:-foo\\\n  bar}"
    ],
    [
        'desc' => 'line continuation in double-quoted expansion',
        'input' => "\"\${NOPE:-foo\\\n  bar}\"",
    ],
    [
        'desc' => 'no line continuations in single-quoted expansions',
        'input' => "'\${NOPE:-foo\\\n  bar}'",
    ],
    [
        'desc' => 'line continuation in single-quoted expansion in double-quoted string',
        'input' => "\"\${NOPE:-'foo\\\n  bar'}\"",
    ],
    [
        'desc' => 'unknown escaped char in unquoted expansion',
        'input' => '${NOPE:-fo\\o}',
    ],
    [
        'desc' => 'unknown escaped char in quoted expansion',
        'input' => '"${NOPE:-fo\\o}"',
    ],
    [
        'desc' => 'concatenation of quoting styles in unquoted string in double-quoted string',
        'input' => '"${NOPE:-a\'b\'"c"${NADA:-d\'e\'"f"}}"',
    ],
    [
        'desc' => 'carriage-return in unquoted value',
        'input' => "a\rb=0",
    ],
    [
        'desc' => 'form-feed in unquoted value',
        'input' => "a\fb=0",
    ],
    [
        'desc' => 'vertical-tab in unquoted value',
        'input' => "a\vb=0",
    ],
];
