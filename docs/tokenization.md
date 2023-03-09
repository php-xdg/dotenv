# Tokenization

Implementations must act as if they used the following state machine to tokenize POSIX-compliant dotenv files.

The state machine must start in the [assignment list state](#assignment-list-state).

Most states consume a single character, which may have various side effects,
and either switches the state machine to a new state
to [reconsume](#reconsume) the [current input character](#current-input-character),
or switches it to a new state to consume the [next character](#next-input-character),
or stays in the same state to consume the next character.
Some states have more complicated behavior and can consume several characters before switching to another state.

## Definitions

### Next input character
The next input character is the first character in the input stream
that has not yet been consumed or explicitly ignored.
Initially, the next input character is the first character in the input.

### Current input character
The current input character is the last character to have been consumed.

### Reconsume
When a state says to reconsume a matched character in a specified state,
that means to switch to that state,
but when it attempts to consume the [next input character](#next-input-character),
provide it with the [current input character](#current-input-character) instead.

### Temporary buffer
The temporary buffer is a string of codepoints that is initially empty.

### Flush the temporary buffer
When a state says to flush the temporary buffer, with an optional `type` argument:
1. If the [temporary buffer](#temporary-buffer) is not empty:
   * Create a new token.
   * Set its type to the value of the `type` argument if given or CHARACTERS otherwise.
   * Set its value to the contents of the temporary buffer.
   * Emit the newly created token.
2. Set the [temporary buffer](#temporary-buffer) to the empty string.

### Stack of return states
The stack of return states is a stack of states, used in some states to return to the state they were invoked from.
It is initially empty.

### Return state
The return state is the state that is currently on top of the [stack of return states](#stack-of-return-states).

When a state says to switch to the return state:
* pop a state off the stack of return states
* switch to the state returned by the previous step

When a state says to reconsume the return state:
* pop a state off the stack of return states
* [reconsume](#reconsume) in the state returned by the previous step

### Quoted flag
The quoted flag is a boolean flag which is initially set to `false`.
### ASCII upper alpha
An ASCII upper alpha is a code point in the range U+0041 (A) to U+005A (Z), inclusive.
### ASCII lower alpha
An ASCII lower alpha is a code point in the range U+0061 (a) to U+007A (z), inclusive.
### ASCII alpha
An ASCII alpha is an [ASCII upper alpha](#ascii-upper-alpha) or [ASCII lower alpha](#ascii-lower-alpha).
### ASCII digit
An ASCII digit is a code point in the range U+0030 (0) to U+0039 (9), inclusive.


## State machine

The tokenizer state machine consists of the states defined in the following subsections.


### Assignment list state

Consume the [next input character](#next-input-character).

* EOF:
  * Emit an end-of-file token.
* U+0020 SPACE, U+0009 TAB, U+000A LINEFEED:
  * Ignore the character
* U+0023 NUMBER SIGN:
  * Switch to the [comment state](#comment-state)
* [ASCII alpha](#ascii-alpha) or U+005F LOW LINE:
  * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
  * Switch to the [assignment name state](#assignment-name-state)
* anything else:
  * Parse error

### Comment state

Consume the [next input character](#next-input-character).

* EOF:
  * Emit an end-of-file token.
* U+000A LINEFEED:
  * Switch to the [assignment list state](#assignment-list-state)
* anything else:
  * Ignore the character

### Assignment name state

Consume the [next input character](#next-input-character).

* [ASCII alpha](#ascii-alpha), [ASCII digit](#ascii-digit) or U+005F LOW LINE:
  * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
* U+003D EQUALS SIGN:
  * [Flush the temporary buffer](#flush-the-temporary-buffer) as an ASSIGN token.
  * Switch to the [assignment value state](#assignment-value-state)
* anything else:
  * Parse error

### Assignment value state

Set the [quoted flag](#quoted-flag) to `false`.

Consume the [next input character](#next-input-character).

* EOF:
  * [Flush the temporary buffer](#flush-the-temporary-buffer).
  * Emit an end-of-file token.
* U+0020 SPACE, U+0009 TAB, U+000A LINEFEED:
  * [Flush the temporary buffer](#flush-the-temporary-buffer).
  * Switch to the [assignment list state](#assignment-list-state).
* U+005C REVERSE SOLIDUS:
  * Switch to the [assignment value escape state](#assignment-value-escape-state).
* U+0027 APOSTROPHE:
  * Push the current state onto the [stack of return states](#stack-of-return-states).
  * Switch to the [single-quoted state](#single-quoted-state).
* U+0022 QUOTATION MARK:
  * Push the current state onto the [stack of return states](#stack-of-return-states).
  * Switch to the [double-quoted state](#double-quoted-state).
* U+0024 DOLLAR SIGN:
  * Push the current state onto the [stack of return states](#stack-of-return-states).
  * Switch to the [dollar state](#dollar-state)
* U+0060 GRAVE ACCENT:
  * Parse error: unsupported command expansion.
* U+007C VERTICAL LINE,
  U+0026 AMPERSAND,
  U+003B SEMICOLON,
  U+003C LESS-THAN SIGN,
  U+003E GREATER-THAN SIGN,
  U+0028 LEFT PARENTHESIS,
  U+0029 RIGHT PARENTHESIS:
    * Parse error: unescaped reserved shell character.
* anything else:
  * Append the [current input character](#current-input-character) to the temporary buffer.


### Assignment value escape state

Consume the [next input character](#next-input-character).

* EOF:
  * Append a U+005C REVERSE SOLIDUS codepoint to the temporary buffer.
  * [Flush the temporary buffer](#flush-the-temporary-buffer).
  * Emit an end-of-file token.
* U+000A LINEFEED:
  * Switch the [assignment value state](#assignment-value-state)
* anything else:
  * Append the [current input character](#current-input-character) to the temporary buffer.
  * Switch the [assignment value state](#assignment-value-state)

### Single-quoted state

Consume the [next input character](#next-input-character).

* EOF:
  * Parse error: unterminated single-quoted string.
* U+0027 APOSTROPHE:
  * Switch to the [return state](#return-state).
* anything else:
  * Append the [current input character](#current-input-character) to the temporary buffer.

### Double-quoted state

Set the [quoted flag](#quoted-flag) to `true`.

Consume the [next input character](#next-input-character).

* EOF:
  * Parse error: unterminated double-quoted string.
* U+0060 GRAVE ACCENT:
  * Parse error: unsupported command expansion.
* U+0022 QUOTATION MARK:
  * Switch to the [return state](#return-state).
* U+005C REVERSE SOLIDUS:
  * Switch to the [double-quoted escape state](#double-quoted-escape-state)
* U+0024 DOLLAR SIGN:
  * Push the current state onto the [stack of return states](#stack-of-return-states).
  * Switch to the [dollar state](#dollar-state)

### Double-quoted escape state

Consume the [next input character](#next-input-character).

* EOF:
  * Parse error: unterminated double-quoted string.
* U+000A LINEFEED:
  * Switch the [double-quoted state](#double-quoted-state).
* U+0022 QUOTATION MARK,
  U+0024 DOLLAR SIGN,
  U+0060 GRAVE ACCENT,
  U+005C REVERSE SOLIDUS:
    * Append the [current input character](#current-input-character) to the temporary buffer.
    * Switch the [double-quoted state](#double-quoted-state).
* anything else:
  * Append a U+005C REVERSE SOLIDUS codepoint to the temporary buffer.
  * Append the [current input character](#current-input-character) to the temporary buffer.
  * Switch the [double-quoted state](#double-quoted-state).

### Dollar state

Consume the [next input character](#next-input-character).

* [ASCII digit](#ascii-digit),
  U+0040 COMMERCIAL AT,
  U+002A ASTERISK,
  U+0023 NUMBER SIGN,
  U+003F QUESTION MARK,
  U+0024 DOLLAR SIGN,
  U+0021 EXCLAMATION MARK,
  U+002D HYPHEN-MINUS:
    * Parse error: unsupported special shell parameter
* U+0028 LEFT PARENTHESIS:
  * Parse error: unsupported command or arithmetic expansion
* U+007B LEFT CURLY BRACKET:
  * Switch to the [dollar brace state](#dollar-brace-state)
* [ASCII alpha](#ascii-alpha) or U+005F LOW LINE:
  * [Flush the temporary buffer](#flush-the-temporary-buffer).
  * Append the [current input character](#current-input-character) to the temporary buffer.
  * Switch to the [simple expansion state](#simple-expansion-state).
* anything else:
  * Append a U+0024 DOLLAR SIGN codepoint to the temporary buffer.
  * [Reconsume](#reconsume) in the [return state](#return-state).

### Dollar brace state

Consume the [next input character](#next-input-character).

* [ASCII alpha](#ascii-alpha) or U+005F LOW LINE:
  * [Flush the temporary buffer](#flush-the-temporary-buffer).
  * Append the [current input character](#current-input-character) to the temporary buffer.
  * Switch to the [complex expansion state](#complex-expansion-state).
* [ASCII digit](#ascii-digit),
  U+0040 COMMERCIAL AT,
  U+002A ASTERISK,
  U+0023 NUMBER SIGN,
  U+003F QUESTION MARK,
  U+0024 DOLLAR SIGN,
  U+0021 EXCLAMATION MARK,
  U+002D HYPHEN-MINUS:
    * Parse error: unsupported special shell parameter
* anything else:
    * Parse error

### Simple expansion state

Consume the [next input character](#next-input-character).

* [ASCII alpha](#ascii-alpha), [ASCII digit](#ascii-digit) or U+005F LOW LINE:
  * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
* anything else:
  * [Flush the temporary buffer](#flush-the-temporary-buffer) as a SIMPLE_EXPANSION token.
  * [Reconsume](#reconsume) in the [return state](#return-state)

### Complex expansion state

Consume the [next input character](#next-input-character).

* [ASCII alpha](#ascii-alpha), [ASCII digit](#ascii-digit) or U+005F LOW LINE:
  * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
* U+007D RIGHT CURLY BRACKET:
  * [Flush the temporary buffer](#flush-the-temporary-buffer) as a SIMPLE_EXPANSION token.
  * Switch to the [return state](#return-state).
* U+003A COLON:
  * [Flush the temporary buffer](#flush-the-temporary-buffer) as a COMPLEX_EXPANSION token.
  * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
  * Switch to the [expansion operator state](#expansion-operator-state)
* U+003F QUESTION MARK,
  U+003D EQUALS SIGN,
  U+002B PLUS SIGN,
  U+002D HYPHEN-MINUS:
    * [Flush the temporary buffer](#flush-the-temporary-buffer) as a COMPLEX_EXPANSION token.
    * Create a new EXPANSION_OPERATOR token and set its value to the [current input character](#current-input-character).
    * Emit the newly created token.
    * Switch to the [expansion arguments state](#expansion-value-state)
* anything else:
  * Parse error.

### Expansion operator state

Consume the [next input character](#next-input-character).

* U+003F QUESTION MARK,
  U+003D EQUALS SIGN,
  U+002B PLUS SIGN,
  U+002D HYPHEN-MINUS:
    * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
    * [Flush the temporary buffer](#flush-the-temporary-buffer) as an EXPANSION_OPERATOR token.
    * Switch to the [expansion arguments state](#expansion-value-state).
* anything else:
  * Parse error.

### Expansion value state

Consume the [next input character](#next-input-character).

* EOF:
  * Parse error: unterminated expansion.
* U+0060 GRAVE ACCENT:
  * Parse error: unsupported command expansion.
* U+007D RIGHT CURLY BRACKET:
  * [Flush the temporary buffer](#flush-the-temporary-buffer).
  * Emit a new CLOSE_BRACE token.
  * Switch to the [return state](#return-state).
* U+005C REVERSE SOLIDUS:
  * Switch to the [expansion arguments escape state](#expansion-value-escape-state).
* U+0024 DOLLAR SIGN:
  * Push the current state onto the [stack of return states](#stack-of-return-states).
  * Switch to the [dollar state](#dollar-state)
* U+0022 QUOTATION MARK:
  * Push the current state onto the [stack of return states](#stack-of-return-states).
  * Switch to the [double-quoted state](#double-quoted-state).
* U+0027 APOSTROPHE:
  * If the [quoted flag](#quoted-flag) is `true`, then:
    * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
  * Otherwise:  
    * Push the current state onto the [stack of return states](#stack-of-return-states).
    * Switch to the [single-quoted state](#single-quoted-state).
* anything else:
  * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).

### Expansion value escape state

Consume the [next input character](#next-input-character).

* EOF:
  * Parse error: unterminated expansion.
* U+000A LINEFEED:
  * Switch to the [expansion arguments state](#expansion-value-state).
* anything else:
  * If the [quoted flag](#quoted-flag) is `true`, append a U+005C REVERSE SOLIDUS codepoint to the [temporary buffer](#temporary-buffer).
  * Append the [current input character](#current-input-character) to the [temporary buffer](#temporary-buffer).
  * Switch to the [expansion arguments state](#expansion-value-state).
  