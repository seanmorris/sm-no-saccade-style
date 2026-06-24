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
const visibleMaps = maps.filter(map => map.visible && map.ready || map === activeMap).map(map => ({
	key: map.id,
	label: map.name,
	bounds: [map.x + camera.x, map.y + camera.y, map.width + marginX, map.height + marginY],
	layers: map.layers.filter(layer => layer.visible && layer.depth > minDepth).map(layer => ({
		name: layer.name,
		opacity: layer.opacity,
		tiles: layer.tiles.filter(tile => tile.index !== 0 && tile.visible).map(tile => ({
			id: tile.id,
			src: tile.src,
			position: [tile.x + offsetX, tile.y + offsetY]
		}))
	}))
}));

if (motionParent
	&& !world.motionGraph.getParent(motionParent)
	&& !maps.has(motionParent)
	&& (state.changed || queue.length && !paused))
{
	world.motionGraph.delete(this);
}
```

### sm-no-saccade-style

```js
const visibleMaps = maps
	.filter(map =>
		map.visible
		&& map.ready
		|| map === activeMap
	)
	.map(map => ({
		key: map.id
		, label: map.name
		, bounds: [
			map.x + camera.x
			, map.y + camera.y
			, map.width + marginX
			, map.height + marginY
		]
		, layers: map.layers
			.filter(layer =>
				layer.visible
				&& layer.depth > minDepth
			)
			.map(layer => ({
				name: layer.name
				, opacity: layer.opacity
				, tiles: layer.tiles
					.filter(tile =>
						tile.index !== 0
						&& tile.visible
					)
					.map(tile => ({
						id: tile.id
						, src: tile.src
						, position: [
							tile.x + offsetX
							, tile.y + offsetY
						]
					}))
			}))
	}));

if(motionParent
	&& !world.motionGraph.getParent(motionParent)
	&& !maps.has(motionParent)
	&& (
		state.changed
		|| queue.length
		&& !paused
	)
){
	world.motionGraph.delete(this);
}
```

## Usage

```js
import smNoSaccadeStyle from 'sm-no-saccade-style';

export default [
	...smNoSaccadeStyle.configs.recommended,
];
```

The recommended config works for both JavaScript and TypeScript.

## Style Guide

This section describes how to lay out code so it already matches the formatter and lint rules this package enforces.

### Lists and records

Keep commas on the next line for multiline arrays, objects, object patterns, and array patterns.

```js
const point = {
	x: 10
	, y: 20
	, label: 'spawn'
};

const bounds = [
	left
	, top
	, right
	, bottom
];

const {
	id
	, name
	, enabled
} = config;
```

Put exactly one space after a leading comma.

```js
const list = [
	a
	, b
	, c
];
```

Short grouped rows are allowed when they stay compact.

```js
const uv = [
	0.0, 0.0
	, 1.0, 0.0
	, 0.0, 1.0
];
```

When a grouped row gets too wide, split it so each item gets its own line.

```js
const values = [
	reallyLongIdentifierAlpha
	, reallyLongIdentifierBeta
	, reallyLongIdentifierGamma
];
```

Do not use trailing commas in the recommended preset.

```js
const user = {
	id: 1
	, name: 'Ada'
};
```

### Continuation operators

Put multiline continuation operators at the beginning of the continued line.

```js
const ready = cache.loaded
	&& !queue.length
	&& currentUser
	&& currentUser.enabled;

const total = subtotal
	+ shipping
	- discount;
```

Ternary markers also lead the continued line.

```js
const label = isReady
	? 'ready'
	: 'pending';
```

### Braces and heads

Use Allman braces for declarations and normal block forms.

```js
function refresh()
{
	render();
}

if(visible)
{
	render();
}

switch(mode)
{
	case 'edit':
		return edit();
}
```

Keep the opening brace on the same line when the body is inline by design:

- arrow functions with block bodies
- function expressions
- inline class expressions
- inline object methods

```js
promise.then(result => {
	handle(result);
});

const loader = function() {
	return cache.read();
};

const Widget = class {
	render()
	{
		return output;
	}
};

const api = {
	load() {
		return fetchData();
	}
};
```

Multiline control heads keep `{` on the same line as the closing `)`.

```js
if(primaryTarget
	&& !visited.has(primaryTarget)
	&& !queue.length
){
	visit(primaryTarget);
}
```

Multiline function heads also keep `{` on the same line as the closing `)`.

```js
class Scene
{
	constructor({
		width
		, height
	}){
		this.width = width;
		this.height = height;
	}
}
```

Empty inline bodies are allowed for compact declarations.

```js
class NullLogger
{
	info(){}
}

if(disabled){}
```

### Delimiter rails

Do not add extra blank lines between adjacent delimiter-only lines.

```js
const config = {
	range: [
		start
		, end
	]
};
```

Do not stack closing delimiters from different indentation rails on the same line when they belong to different openings.

```js
const loadSlices = layers.map(
	layer => layer.tiles.map(tile => ({
		id: tile.id
	}))
);
```

### Indentation and whitespace

Indent with tabs. Use alignment only where smart tabs make the structure clearer.

```js
const shortName = 1;
const longerKey = 2;
```

Use tight control parens.

```js
if(ready)
{
	run();
}

while(queue.length)
{
	drain(queue);
}
```

Avoid trailing spaces and keep one final newline at the end of each file.

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
