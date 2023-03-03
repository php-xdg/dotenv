# POSIX-compliant dotenv syntax specification

## Relation to POSIX

The POSIX-compliant dotenv syntax is a subset of the
[POSIX shell command language specification](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html).

Conforming implementations MUST follows the rules in the aforementioned specification for:
* variable assignment
* [parameter expansion](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_02)
* [quote removal](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_07)

Conforming implementations MUST apply the restrictions to the former rules that are defined in this document.

In conforming implementations, the following features
MUST NOT be supported AND result in a parse error:
* [positional parameters](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_05_01)
* [special parameters](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_05_02)
* [command substitution](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_03)
* [arithmetic expansion](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_04)
* string-length expansion (`${#name}`)
* pattern-matching expansions (`${name%pattern}`, `${name%%pattern}`, `${name#pattern}`, `${name##pattern}`)

In conforming implementations, the following MUST NOT be performed:
* [tilde expansion](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_01)
* [field splitting](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_05)
* [pathname expansion](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_06)


## General definitions

The file encoding MUST be UTF-8.

The file MUST NOT contain null bytes (ASCII code 0x00).

The `<newline>` character MUST be `LF` (ASCII code 0x0A).

The `<whitespace>` characters are:
* `<space>` (ASCII code 0x20).
* `<tab>` (ASCII code 0x09).

A dotenv file is a list of [assignment](#assignment-expressions) expressions,
separated by one or more `<whitespace>` and/or `<newline>` characters,
and optionally preceded or followed by any number of [comment](#comments) expressions.


## Assignment expressions

An `<assignment>` expression is of the form: `<identifier>=<value>`.

An `<identifier>` MUST start with a letter or underscore,
optionally followed by any number of letters, digits or underscores.

In other words, they MUST match the following regular expression:
```regexp
[a-zA-Z_][a-zA-Z0-9_]*
```

Whitespace is not allowed between the `<indentifier>` and the equal sign,
nor between the equal sign and the `<value>`.


## Assignment values

Assignment values follow the
[quoting rules](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_02)
defined in the POSIX specification.

An assignment value consists of a sequence of any number of:
* unquoted strings
* single-quoted strings
* double-quoted strings

In the following example, `FOO` evaluates to `foobarbaz`:

```sh
FOO=foo'bar'"baz"
```


### Escape character (backslash)

A `<backslash>` (ASCII code 0x5C) character can be used as an escape character
in unquoted and double-quoted strings, to preserve the literal value of the following character.

It has no effect in single-quoted strings.

In unquoted and double-quoted strings, if a `<newline>` follows the `<backslash>`,
this is interpreted as a line continuation (the `<backslash>` and `<newline>` are entirely ignored).

In the following example, all variables evaluate to `foobar`:

```sh
A=foo\
bar
B="foo\
bar"
```


### Unquoted strings

A `<whitespace>` or `<newline>` character cannot occur inside an unquoted string unless escaped with a `<backslash>`.

[Variable expansion](#variable-expansion) occurs in unquoted strings.

The following characters have a special meaning in shell scripts
and MUST be escaped in unquoted strings:

```
|  &  ;  <  >  (  )  `
```

Additionally, the following characters must be escaped if they are to represent themselves:

```
" ' $ <backslash> <space> <tab>
```


### Single-quoted strings

Single-quoted strings are sequences of characters enclosed in single quotes `'` (ASCII code 0x27).

Single-quoted strings **preserve the literal value** of each character within the single-quotes.

Variable expansion does not occur in unquoted strings.

A single-quote CANNOT occur within single-quotes.

Invalid:
```sh
FOO='foo\'bar'
```

Valid:
```sh
FOO='foo'\''bar'
FOO='foo'"'"'bar'
```


### Double-quoted strings

Double-quoted strings are sequences of characters enclosed in double quotes `"` (ASCII code 0x22).

A `<backslash>` character before a `"`, `$` or `\` preserves the literal meaning of the following character.

A `<backslash>` preceding a `<newline>` is interpreted as a line continuation.

[Variable expansion](#variable-expansion) occurs in double-quoted strings.


## Comments

A `<comment>` starts with the `#` character (ASCII 0x23) and continues up to (but not including) the next `<newline>` character.

The `#` character starts a comment if all the following conditions hold:
* it is not escaped (preceded by a `<backslash>` character)
* it does not appear inside a single or double-quoted string
* it is either:
  * the first character in the file
  * preceded by a `<whitespace>` or `<newline>` character that is not escaped

In the following example, all variables evaluate to `im#not-a-comment`:

```sh
A=im#not-a-comment
B='im#not-a-comment'
C='im'#not-a-comment
D="im#not-a-comment"
E="im"#not-a-comment
F=im\ #not-a-comment
G=im\
#not-a-comment
```

In the following example, all variables evaluate to `im`:

```sh
# a comment
  # an indented comment
A=im # a comment
B='im' # a comment
C="im" # a comment
D=im\
  # a comment
```


## Variable expansion

The POSIX-compliant dotenv syntax implements the following subset of the POSIX
[parameter expansion](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html#tag_18_06_02)
specification.

Variable expansions some in two forms: simple expansions and complex expansions.

### Simple expansions

Simple expansions start with a `$` character, followed by an [identifier](#assignment-expressions)
optionally enclosed in curly braces.

Here is an equivalent parsing expression grammar:

```peg
simple-expansion  <- "$" identifier / "${" identifer "}"
identifier        <- [a-zA-Z_][a-zA-Z0-9_]*
```

### Complex expansions

The syntax of complex expansions is as follows:

```peg
complex-expansion <- "${" identifer operator value? "}"
operator          <- ":"? ( "-" / "+" / "=" / "?" )
```
