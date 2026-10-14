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

## Line length and separation

The recommended preset uses a 100-column limit and four-column tab stops. It wraps code at token boundaries and reports lines that still need attention. Multiline literals and literal payloads too long to fit are exempt by default; their contents are never changed.

A run of more than 10 nonblank code lines produces a warning suggesting semantic separation. The rule does not choose where to insert blank lines. Comment-only lines and multiline literal contents break the run.

Override the limits in flat config:

```js
export default [
    ...smNoSaccadeStyle.configs.recommended,
    {
        rules: {
            'sm-no-saccade-style/max-line-length': ['error', { max: 100, tabWidth: 4 }],
            'sm-no-saccade-style/allman-tabs': ['error', { maxLineLength: 100 }],
            'sm-no-saccade-style/object-layout': ['error', { max: 100 }],
            'sm-no-saccade-style/max-unseparated-lines': ['warn', { max: 10 }],
        },
    },
];
```

Set `ignoreLiterals: false` on `max-line-length` to report long literal lines too. The formatter still leaves literal contents unchanged.

To require a consistent layout within each parameter or argument list, enable
`sm-no-saccade-style/parameter-layout`. Once a list has a break between items,
every item starts on its own line below the opening parenthesis, using leading
commas. An explicitly expanded list stays expanded even when it would fit inline.
Multiline callback bodies and literal contents remain intact. A callback body
alone does not force the surrounding argument list to expand.

```js
{
    rules: {
        'sm-no-saccade-style/parameter-layout': ['error', { max: 120 }],
        'sm-no-saccade-style/max-line-length': ['error', { max: 120 }],
        'sm-no-saccade-style/allman-tabs': ['error', { maxLineLength: 120 }],
        'sm-no-saccade-style/object-layout': ['error', { max: 120 }],
    },
}
```

Classes, functions, and methods have a blank line before their declaration. Attached JSDoc and decorators stay with the declaration; the blank line goes before them. The same applies to the first class method, object methods, and assigned functions, including property assignments such as `window.run = () => { ... }`. Inline callback expressions stay with their enclosing expression.

## Style Guide

This section describes how to lay out code so it already matches the formatter and lint rules this package enforces.

### Statement groups and long calls

Keep related assignments together. Insert a blank line before a following control statement or multiline call, and after a completed block. This includes a nested closing run such as `}}` and a callback ending in `});`. Do not insert a blank line before another closing brace or an attached `else`, `elseif` (PHP), `catch`, `finally`, or `do…while` clause. Leading comments stay with their statements, and trailing comments stay with their closing braces. Directly nested controls stay together.

Separate completed multiline expressions and variable declarations from the next statement, including object assignments and callback calls followed by another argument. A `return` after other work also gets a preceding blank line. Complete compact function bodies remain on one line.

Wrap a long call at its outer argument list before splitting a nested call that fits. Each argument keeps its expression intact where possible, and the outer closing parenthesis returns to the opening line's indentation:

```js
entities[object.id] = registry.create(
	...this.factories[className](object, map)
);
```

This also applies when an argument contains a compact object:

```js
window.dispatchEvent(
	Object.assign(new Event('gamepaddisconnected'), {gamepad: menuPads[0]})
);
```

When a callback returns an object, expand that object's properties before splitting short expressions inside them. Callback bodies, comments, and literal contents stay intact when an enclosing call needs to wrap.

```js
menuPads[0].buttons = Array.from({length: 17}, (_, i) => ({
	pressed: buttons.includes(i)
	, value: buttons.includes(i) ? 1 : 0
}));
```

The separate warning for runs longer than 10 lines still asks for human judgment when no syntactic boundary determines a useful break.

### Parameters, calls, and loop headers

Use one space after commas in function definitions and calls, and after semicolons in `for` headers. This also covers methods, arrow functions, constructor calls, and TypeScript signatures. Multiline headers keep their line breaks; no trailing spaces are added.

```js
function add(left, right){ return left + right; }

for(let i = 0; i < count; i++)
{
	add(i, count);
}
```

### Lists and records

Keep commas on the next line for multiline arrays, objects, object patterns, and array patterns.

Expanded objects and object patterns put every property on its own line, including the first property immediately below `{`. The closing brace aligns with the opening line. Compact nested objects may remain inline. The `object-layout` rule uses the same configurable line limit for expanding long object assignments and returned objects.

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

Short grouped array rows are allowed when they stay compact. Expanded objects always use one property per line.

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

Object colons allow no space before `:` and at least one space after `:`. If any value in a multiline object is padded for alignment, the values are aligned as a group.

```js
const services = {
	ksqlServer:  'http://ksql-server:8088/info'
	, krestApi: 'http://krest-server:8082/topics'
};
```

### Continuation operators

Put spaces around assignment operators and default-value `=`, arrow `=>`, and ternary `? :` on the same line. Existing alignment spaces and line breaks are preserved. This rule does not change comparison or arithmetic spacing.

```js
const state = (value = 0) => value ? 'ready' : 'pending';
total += value;
```

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

Compact bodies are allowed only when the complete construct fits on one line within the limit and the language requires its braces. Multiline heads always have multiline bodies. Optional control braces expand to standard blocks, even for an empty body. Existing braces are preserved.

In multiline statements, `else`, `else if`, `catch`, `finally`, and the terminating `while` of a `do` loop start on the next line after `}`, with no blank line between them. This also applies after nested closing runs such as `}}`. Comments stay in place. A complete one-line `try` statement keeps its clauses on that line.

```js
if(ready)
{
	start();
}
else if(pending)
{
	wait();
}
else
{
	recover();
}
```

```js
function add(){ return; };

class Counter { value = 0; }

try{ work(); } catch(error){ report(error); }

if(disabled)
{
}
```

### Delimiter rails

Multiline `()`, `{}`, and `[]` close at the indentation level of the line where they opened. Use a separate closing line when needed. Do not add extra blank lines between adjacent delimiter-only lines.

```js
const config = {
	range: [
		start
		, end
	]
};
```

Do not stack closing delimiters from different indentation rails on the same line when they belong to different openings. Nested block braces are the exception: existing runs such as `}}` and `} }` stay together at the first closing brace's indentation. A closing run still needs a blank line before the next statement, unless another `}` or an attached clause follows. Object, array, and call delimiters keep their own indentation rails.

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

Class property initializers keep at least one space before and after `=`. If any initialized property in a class body is padded before `=`, the initializers are aligned as a group.

```js
class Settings
{
	short      = 1;
	longerName = 2;
	mid        = 3;
}
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

- `sm-no-saccade-style/statement-spacing`
  Separates control flow and multiline call statements from preceding work, and completed blocks and multiline statements from following statements. Adds a blank line before a return following other work. Comments stay attached; connected clauses, directly nested controls, and consecutive closing braces stay together.
- `sm-no-saccade-style/expression-spacing`
  Spaces assignments, defaults, arrows, and ternaries without changing existing alignment or line breaks.
- `sm-no-saccade-style/object-layout`
  Puts every property of an expanded object or object pattern on its own line. Expands long object assignments and returned objects at a configurable limit, defaulting to 100 columns.
- `sm-no-saccade-style/separator-spacing`
  Spaces parameter and argument commas and `for` header semicolons, preserving comments and line breaks.
- `sm-no-saccade-style/declaration-spacing`
  Adds a blank line before declarations, including their documentation and decorators.
- `sm-no-saccade-style/max-line-length`
  Wraps code at safe token boundaries and reports lines over the configurable 100-column limit.
- `sm-no-saccade-style/max-unseparated-lines`
  Warns on more than 10 consecutive code lines without semantic separation. It has no automatic fix.

- `sm-no-saccade-style/leading-comma-lists`
  Moves commas to the left rail for multiline arrays and objects.
- `sm-no-saccade-style/final-comma-line`
  Forbids trailing commas in the recommended preset. The rule itself still supports `allow`, `require`, and `forbid`.
- `sm-no-saccade-style/leading-operators`
  Moves continuation operators to the beginning of the next line.
- `sm-no-saccade-style/allman-tabs`
  Enforces Allman braces for declarations and block forms, but keeps inline functions, inline class expressions, and inline object methods on the same line. Attached clauses of multiline statements start on the next line after the closing brace, without a blank line.
- `sm-no-saccade-style/no-double-closing-gap`
  Forbids extra blank lines between delimiter-only lines: no blank lines for `open -> open` or `close -> close`, and at most one blank line for `close -> open`.
- `sm-no-saccade-style/no-space-control-paren`
  Enforces tight control parens like `if(x)`.
- `sm-no-saccade-style/no-trailing-whitespace`
  Strips trailing spaces and tabs unless they are part of template-string content.
- `@stylistic/eol-last`
  Requires one final trailing newline at end of file.
