# sm-no-saccade-style

[![npm version](https://img.shields.io/npm/v/sm-no-saccade-style?style=for-the-badge)](https://www.npmjs.com/package/sm-no-saccade-style)
[![npm license](https://img.shields.io/npm/l/sm-no-saccade-style?style=for-the-badge)](https://www.npmjs.com/package/sm-no-saccade-style)
[![CI](https://img.shields.io/github/actions/workflow/status/seanmorris/sm-no-saccade-style/ci.yml?branch=master&style=for-the-badge)](https://github.com/seanmorris/sm-no-saccade-style/actions/workflows/ci.yml)
[![Codecov](https://img.shields.io/codecov/c/github/seanmorris/sm-no-saccade-style?style=for-the-badge)](https://codecov.io/gh/seanmorris/sm-no-saccade-style)

## Why saccades matter

When reading code, the eye does not move smoothly across the page. It jumps in quick motions called saccades, then pauses briefly to take in detail.

Those jumps are cheap when the next meaningful symbol is easy to predict and easy to find. They become expensive when punctuation that controls structure is scattered across the far right edge of lines, because the eye has to keep leaving the left rail to confirm where lists continue, where expressions break, and where blocks open or close.

That repeated scanning adds friction. It slows reacquisition after every line break, increases miss risk in dense multiline code, and makes editing harder because structural markers are not where the eye naturally returns.

sm-no-saccade-style is built to reduce that cost. It pulls high-signal syntax back toward the left side of the line so continuation structure is visible earlier, with less hunting and fewer long corrective eye movements.

## Design

Put control symbols where the eye naturally reacquires the next line.

This style favors:

- leading commas
- no trailing commas
- leading boolean / arithmetic continuation operators
- leading ternary markers
- Allman braces for declarations and block forms
- same-line braces for inline functions, inline class expressions, and inline object methods
- no extra blank lines between adjacent delimiter-only lines
- at most one blank line between a closing delimiter line and the next opening delimiter line
- tab indentation
- tight control parentheses (`if(x)`)
- no trailing whitespace outside strings
- final trailing newline

## Example

### Conventional

```js
const value = source ||
	fallback ||
	defaultValue;

const list = [
	'a',
	'b',
	'c',
];
```

### sm-no-saccade-style

```js
const value = source
	|| fallback
	|| defaultValue;

const list = [
	'a'
	, 'b'
	, 'c'
];
```

## Usage

```js
import smNoSaccadeStyle from 'sm-no-saccade-style';

export default [
	...smNoSaccadeStyle.configs.recommended,
];
```

The recommended config works for both JavaScript and TypeScript.

## Rules

- `sm-no-saccade-style/leading-comma-lists`
  Moves commas to the left rail for multiline arrays and objects.
- `sm-no-saccade-style/final-comma-line`
  Forbids trailing commas in the recommended preset. The rule itself still supports `allow`, `require`, and `forbid`.
- `sm-no-saccade-style/leading-operators`
  Moves continuation operators to the beginning of the next line.
- `sm-no-saccade-style/allman-tabs`
  Enforces Allman braces for declarations and block forms, but keeps inline functions, inline class expressions, and inline object methods on the same line.
- `sm-no-saccade-style/no-double-closing-gap`
  Forbids extra blank lines between delimiter-only lines: no blank lines for `open -> open` or `close -> close`, and at most one blank line for `close -> open`.
- `sm-no-saccade-style/no-space-control-paren`
  Enforces tight control parens like `if(x)`.
- `sm-no-saccade-style/no-trailing-whitespace`
  Strips trailing spaces and tabs unless they are part of template-string content.
- `@stylistic/eol-last`
  Requires one final trailing newline at end of file.
