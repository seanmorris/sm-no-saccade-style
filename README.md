# sm-no-saccade-style

An ESLint style plugin optimized to reduce visual scan friction.

Traditional formatting scatters structural punctuation across the right edge of code, forcing repeated eye jumps (saccades) to locate commas, operators, and flow markers.

sm-no-saccade-style moves syntax to the left rail so code structure is visible immediately at line reacquisition points.

Less hunting. Faster parsing. Cleaner continuation flow.

Designed for developers who read vertically and edit multiline structures frequently.

## Design

Put control symbols where the eye naturally reacquires the next line.

This style favors:

- leading commas
- leading boolean / arithmetic continuation operators
- leading ternary markers
- Allman braces
- tab indentation
- tight control parentheses (`if(x)`)

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
	,
];
```

## Usage

```js
import smNoSaccadeStyle from 'sm-no-saccade-style';

export default [
	...smNoSaccadeStyle.configs.recommended,
];
```

## Rules

- `sm-no-saccade-style/leading-comma-lists`
- `sm-no-saccade-style/final-comma-line`
- `sm-no-saccade-style/leading-operators`
- `sm-no-saccade-style/allman-tabs`
- `sm-no-saccade-style/no-space-control-paren`
