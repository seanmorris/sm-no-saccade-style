# sm-no-saccade-style

## Why saccades matter

When reading code, the eye does not move smoothly across the page. It jumps in quick motions called saccades, then pauses briefly to take in detail.

Those jumps are cheap when the next meaningful symbol is easy to predict and easy to find. They become expensive when punctuation that controls structure is scattered across the far right edge of lines, because the eye has to keep leaving the left rail to confirm where lists continue, where expressions break, and where blocks open or close.

That repeated scanning adds friction. It slows reacquisition after every line break, increases miss risk in dense multiline code, and makes editing harder because structural markers are not where the eye naturally returns.

sm-no-saccade-style is built to reduce that cost. It pulls high-signal syntax back toward the left side of the line so continuation structure is visible earlier, with less hunting and fewer long corrective eye movements.

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
