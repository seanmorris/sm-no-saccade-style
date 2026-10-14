# seanmorris/sm-no-saccade-style

[![Packagist version](https://img.shields.io/packagist/v/seanmorris/sm-no-saccade-style?style=for-the-badge)](https://packagist.org/packages/seanmorris/sm-no-saccade-style)
[![Packagist license](https://img.shields.io/packagist/l/seanmorris/sm-no-saccade-style?style=for-the-badge)](https://packagist.org/packages/seanmorris/sm-no-saccade-style)
[![CI](https://img.shields.io/github/actions/workflow/status/seanmorris/sm-no-saccade-style/ci.yml?branch=master&style=for-the-badge)](https://github.com/seanmorris/sm-no-saccade-style/actions/workflows/ci.yml)
[![Codecov](https://img.shields.io/codecov/c/github/seanmorris/sm-no-saccade-style?style=for-the-badge)](https://codecov.io/gh/seanmorris/sm-no-saccade-style)

PHP-CS-Fixer companion package for the no-saccade style.

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
- leading boolean, arithmetic, string, null-coalescing, and ternary continuation operators
- Allman braces for declarations and block forms
- same-line braces for multiline control heads and function heads
- no extra blank lines between adjacent delimiter-only lines
- at most one blank line between a closing delimiter line and the next opening delimiter line
- tab indentation
- tight control parentheses (`if($ready)`)
- no trailing whitespace outside strings
- final trailing newline
- explicit semicolons with no multiline whitespace before them

## Example

### Conventional

```php
<?php

$visibleMaps = array_map(
	function($map) use ($camera, $marginX, $marginY, $minDepth, $offsetX, $offsetY) {
		return [
			'key' => $map->id,
			'label' => $map->name,
			'bounds' => [$map->x + $camera->x, $map->y + $camera->y, $map->width + $marginX, $map->height + $marginY],
			'layers' => array_map(
				function($layer) use ($minDepth, $offsetX, $offsetY) {
					return [
						'name' => $layer->name,
						'opacity' => $layer->opacity,
						'tiles' => array_map(
							fn($tile) => [
								'id' => $tile->id,
								'src' => $tile->src,
								'position' => [$tile->x + $offsetX, $tile->y + $offsetY],
							],
							array_filter($layer->tiles, fn($tile) => $tile->index !== 0 && $tile->visible)
						),
					];
				},
				array_filter($map->layers, fn($layer) => $layer->visible && $layer->depth > $minDepth)
			),
		];
	},
	array_filter($maps, fn($map) => $map->visible && $map->ready || $map === $activeMap)
);

if ($motionParent
	&& !$world->motionGraph->getParent($motionParent)
	&& !isset($maps[$motionParent])
	&& ($state->changed || count($queue) && !$paused))
{
	$world->motionGraph->delete($this);
}
```

### sm-no-saccade-style

```php
<?php

$visibleMaps = array_map(
	function($map) use (
		$camera
		, $marginX
		, $marginY
		, $minDepth
		, $offsetX
		, $offsetY
	){
		return [
			'key' => $map->id
			, 'label' => $map->name
			, 'bounds' => [
				$map->x + $camera->x
				, $map->y + $camera->y
				, $map->width + $marginX
				, $map->height + $marginY
			]
			, 'layers' => array_map(
				function($layer) use (
					$minDepth
					, $offsetX
					, $offsetY
				){
					return [
						'name' => $layer->name
						, 'opacity' => $layer->opacity
						, 'tiles' => array_map(
							fn($tile) => [
								'id' => $tile->id
								, 'src' => $tile->src
								, 'position' => [
									$tile->x + $offsetX
									, $tile->y + $offsetY
								]
							]
							, array_filter(
								$layer->tiles
								, fn($tile) => $tile->index !== 0
									&& $tile->visible
							)
						)
					];
				}
				, array_filter(
					$map->layers
					, fn($layer) => $layer->visible
						&& $layer->depth > $minDepth
				)
			)
		];
	}
	, array_filter(
		$maps
		, fn($map) => $map->visible
			&& $map->ready
			|| $map === $activeMap
	)
);

if($motionParent
	&& !$world->motionGraph->getParent($motionParent)
	&& !isset($maps[$motionParent])
	&& (
		$state->changed
		|| count($queue)
		&& !$paused
	)
){
	$world->motionGraph->delete($this);
}
```

## Install

```sh
composer require --dev seanmorris/sm-no-saccade-style
```

This package requires PHP 8.2 or newer and PHP-CS-Fixer 3.

## Usage

Create `.php-cs-fixer.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . '/src')
;

return SeanMorris\NoSaccadeStyle\ConfigFactory::recommended($finder);
```

Run:

```sh
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/php-cs-fixer fix
```

The recommended config registers the custom no-saccade fixers, sets tab indentation, sets Unix line endings, forbids final comma-only lines, and enables companion PHP-CS-Fixer rules for semicolons and final newlines.

If you do not pass a finder, the recommended config scans PHP files under the current working directory and excludes `vendor`.

## Advanced Usage

Register the fixers in your own config:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . '/src')
;

$config = new PhpCsFixer\Config();

$config->registerCustomFixers(SeanMorris\NoSaccadeStyle\Fixers::all());
$config->setIndent("\t");
$config->setLineEnding("\n");
$config->setRules([
	SeanMorris\NoSaccadeStyle\Fixers::DECLARATION_SPACING => true
	, SeanMorris\NoSaccadeStyle\Fixers::LEADING_COMMA_LISTS => true
	, SeanMorris\NoSaccadeStyle\Fixers::FINAL_COMMA_LINE => ['mode' => 'forbid']
	, SeanMorris\NoSaccadeStyle\Fixers::LEADING_OPERATORS => true
	, SeanMorris\NoSaccadeStyle\Fixers::ALLMAN_TABS => true
	, SeanMorris\NoSaccadeStyle\Fixers::NO_DOUBLE_CLOSING_GAP => true
	, SeanMorris\NoSaccadeStyle\Fixers::NO_SPACE_CONTROL_PAREN => true
	, SeanMorris\NoSaccadeStyle\Fixers::NO_TRAILING_WHITESPACE => true
	, SeanMorris\NoSaccadeStyle\Fixers::SEPARATOR_SPACING => true
	, SeanMorris\NoSaccadeStyle\Fixers::STATEMENT_SPACING => true
	, 'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line']
	, 'semicolon_after_instruction' => true
	, 'single_blank_line_at_eof' => true
]);
$config->setFinder($finder);

return $config;
```

`FinalCommaLineFixer` accepts three modes:

- `allow`
  Leaves final comma-only lines alone.
- `require`
  Requires a final comma-only line before a multiline array closer.
- `forbid`
  Removes final comma-only lines. This is the recommended preset.

## Line length and separation

The recommended preset uses a 100-column limit and four-column tab stops. It wraps code at token boundaries without rewriting literal contents. Run both checks in CI:

```sh
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/sm-no-saccade-style src tests
```

PHP-CS-Fixer has no warning severity. The companion command reports remaining line-length errors and warns about runs of more than 10 nonblank code lines. It suggests semantic separation without inserting blank lines. Comment-only lines and multiline literal contents break a run. Warnings exit successfully; line-length errors exit with status 1 and invalid input exits with status 2.

The command accepts files or directories, recursively scans PHP files, and excludes `vendor` and `node_modules`:

```sh
vendor/bin/sm-no-saccade-style --max-line-length=100 --max-unseparated-lines=10 --tab-width=4 src
```

Multiline literals and long literal payloads are exempt by default. Add `--no-ignore-literals` to report their lengths too. For a different formatting limit, set `Fixers::ALLMAN_TABS` to `['max_line_length' => 120]` and pass the same value to the companion command.

Classes, named functions, and methods have a blank line before their declaration, including the first method in a class. Attached PHPDoc and attributes stay with the declaration; the blank line goes before them.

## Style Guide

This section describes how to lay out PHP so it already matches the formatter and lint rules this package enforces.

### Statement groups and long calls

Keep related assignments together. Insert a blank line before a following control statement or multiline call, and after a completed block. This includes a nested closing run such as `}}` and a callback ending in `});`. Do not insert a blank line before another closing brace or an attached `else`, `elseif` (PHP), `catch`, `finally`, or `do…while` clause. Leading comments stay with their statements, and trailing comments stay with their closing braces. Directly nested controls stay together.

Separate completed multiline expressions, including constructor calls, from the next statement. A return after preceding work also gets a blank line. Short comment-free calls compact when the complete call fits the configured limit; expanded arrays, comments, and literal contents stay intact.

Separate an outer unbraced control header from its body before wrapping the body's call or short subexpressions. Stacked control headers and bodies share an indentation level. Existing braces remain in place.

Wrap a long call at its outer argument list before splitting a nested call that fits. Each argument keeps its expression intact where possible, and the outer closing parenthesis returns to the opening line's indentation:

```php
$entities[$object['id']] = $registry->create(
	...($this->factories[$class])($object, $map)
);
```

The separate warning for runs longer than 10 lines still asks for human judgment when no syntactic boundary determines a useful break.

### Parameters, calls, and loop headers

Use one space after commas in function definitions and calls, and after semicolons in `for` headers. This also covers methods, closures, arrow functions, and constructor calls. Multiline headers keep their line breaks; no trailing spaces are added.

An expanded parameter list puts every parameter on its own line, including the first, with leading commas and an aligned closing parenthesis. Calls use either a complete inline list or consistent vertical argument boundaries; multiline callback bodies alone do not force every argument onto a separate line.

```php
function add($left, $right){ return $left + $right; }

for($i = 0; $i < $count; $i++)
{
	add($i, $count);
}
```

### Lists and records

Keep commas on the next line for multiline arrays, `array()` calls, `list()` destructuring, vertical function and method calls, and destructuring patterns.

```php
<?php

$point = [
	'x' => 10
	, 'y' => 20
	, 'label' => 'spawn'
];

$bounds = [
	$left
	, $top
	, $right
	, $bottom
];

[
	$id
	, $name
	, $enabled
] = $config;

$result = render(
	$template
	, $data
	, $options
);
```

Put exactly one space after a leading comma.

```php
<?php

$list = [
	$a
	, $b
	, $c
];
```

Expanded associative arrays put every entry on its own line, including the first. Keep each entry's short value intact; expand the map before splitting a member access or nested call. This also applies to keyed destructuring and `array()` syntax. Nested callbacks, comments and literal contents keep their structure.

A sole expanded array argument attaches to its call, including the closing delimiters. Calls with additional arguments keep separate argument lines:

```php
$state = json_encode([
	'phase' => $this->phase
	, 'time' => $this->game->engine->time
	, 'progress' => $this->backdrop->progress()
]);
```

Expanded indexed arrays also start their first item below the opening bracket and align the closing bracket with the opening line. This applies when the first item is itself a multiline constructor or callback. Short grouped rows stay grouped, short inline arrays stay inline, and explicitly expanded arrays stay expanded. The same boundaries apply to `array()` and unkeyed destructuring.

```php
<?php

$uv = [
	0.0, 0.0
	, 1.0, 0.0
	, 0.0, 1.0
];
```

When a grouped row gets too wide, split it so each item gets its own line.

```php
<?php

$values = [
	$reallyLongIdentifierAlpha
	, $reallyLongIdentifierBeta
	, $reallyLongIdentifierGamma
];
```

Do not use trailing commas in the recommended preset.

```php
<?php

$user = [
	'id' => 1
	, 'name' => 'Ada'
];
```

Array arrows keep at least one space after `=>`. If any arrow in a multiline list is padded for alignment, the list is aligned as a group.

```php
<?php

$services = [
	'ksql-server'    => 'http://ksql-server:8088/info'
	, 'krest-server' => 'http://krest-server:8082/topics'
];
```

### Continuation operators

Put spaces around the concatenation operator: `$prefix . $name`. The recommended preset enables PHP-CS-Fixer's `concat_space` rule with `spacing: one`.

Put multiline continuation operators at the beginning of the continued line.

```php
<?php

$ready = $cacheLoaded
	&& !$queue
	&& $currentUser
	&& $currentUser->enabled;

$total = $subtotal
	+ $shipping
	- $discount;

$label = $prefix
	. ': '
	. $name;

$value = $provided
	?? $fallback;
```

Keep short ternaries inline. When a ternary spans multiple lines, both `?` and `:` lead equally indented continuation lines. Partially wrapped ternaries compact when they fit, or expand both branches. Nullable types, named arguments, and the shorthand `?:` retain their meaning.

```php
<?php

$label = $isReady
	? 'ready'
	: 'pending';
```

### Braces and heads

Use Allman braces for declarations and normal block forms.

```php
<?php

function refresh(): void
{
	render();
}

if($visible)
{
	render();
}

switch($mode)
{
	case 'edit':
		return edit();
}
```

Multiline control heads with a separate closing `)` keep `{` on that closing line.

```php
<?php

if($primaryTarget
	&& !isset($visited[$primaryTarget])
	&& !$queue
){
	visit($primaryTarget);
}
```

For a `foreach` over an expanded array, keep the array closer, `as` binding, and closing parenthesis together when they fit. The body then starts with an Allman brace:

```php
foreach([
	'platformer' => 'Platformer'
	, 'world' => 'Streaming world'
] as $id => $label)
{
	register($id, $label);
}
```

Multiline function heads also keep `{` on the same line as the closing `)`.

```php
<?php

final class Scene
{
	public function __construct(
		private int $width
		, private int $height
	){
	}
}
```

Closures and arrow functions keep their opening brace or expression on the same line as the head.

For multiline function signatures with return types, the brace follows the complete closing signature: `): Result {`.

Place a closure's PHPDoc above its complete assignment, and keep `$callback = function...` together when the head fits. This avoids indenting the entire closure as an assignment continuation. Documentation between `=` and a standalone closure is moved above the assignment without changing its contents.

The companion linter warns about documented closures and arrow functions passed directly as arguments or embedded in a `??` fallback with `documented-callback`. It suggests assigning a descriptive local variable first, with the PHPDoc above that assignment. Named callbacks and undocumented inline callbacks do not trigger this warning. The linter does not extract callbacks automatically: choosing a name and moving a fallback can affect variable capture or evaluation timing.

```php
<?php

$loader = function() {
	return $cache->read();
};

$ids = array_map(
	fn($item) => $item->id
	, $items
);
```

Compact bodies are allowed only when the complete construct fits on one line within the limit and the language requires its braces. Multiline heads always have multiline bodies. Optional control braces expand to standard blocks, even for an empty body. Existing braces are preserved.

```php
<?php

function add(){ return; };

final class Counter { public int $value = 0; }

try{ work(); } catch(Exception $error){ report($error); }

if($disabled)
{
}
```

Dynamic member access braces stay inline.

```php
<?php

$literal = $response->{'X-Test-Method'};
$variable = $this->{ $prop };
$nullable = $response?->{'X-Test-Method'};
$static = Handler::{ $method }();
```

### Delimiter rails

Multiline `()`, `{}`, and `[]` close at the indentation level of the line where they opened. Use a separate closing line when needed. Do not add extra blank lines between adjacent delimiter-only lines.

```php
<?php

$config = [
	'range' => [
		$start
		, $end
	]
];
```

Do not stack closing delimiters from different indentation rails on the same line when they belong to different openings. Nested block braces are the exception: existing runs such as `}}` and `} }` stay together at the first closing brace's indentation. A closing run still needs a blank line before the next statement, unless another `}` or an attached clause follows. Object, array, and call delimiters keep their own indentation rails.

```php
<?php

$loadSlices = array_map(
	fn($layer) => array_map(
		fn($tile) => [
			'id' => $tile->id
		]
		, $layer->tiles
	)
	, $layers
);
```

### Indentation and whitespace

Indent with tabs. Use alignment only where smart tabs make the structure clearer.

```php
<?php

$shortName = 1;
$longerKey = 2;
```

Class property initializers keep at least one space before and after `=`. If any initialized property in a class body is padded before `=`, the initializers are aligned as a group.

```php
<?php

class Settings
{
	public $short      = 1;
	public $longerName = 2;
	public $mid        = 3;
}
```

Use tight control parens.

```php
<?php

if($ready)
{
	run();
}

while(count($queue))
{
	drain($queue);
}

foreach($items as $item)
{
	process($item);
}

match($mode)
{
	'edit' => edit()
	, default => view()
};
```

Avoid trailing spaces and tabs outside strings, keep one final newline at the end of each file, and keep semicolons on the same line as the instruction they terminate.

## Rules

- `SeanMorris/no_saccade_statement_spacing`
  Separates control flow and multiline call statements from preceding work, and completed blocks and multiline expressions from following statements. A return after preceding work gets a blank line. Comments stay attached; connected clauses, directly nested controls, and consecutive closing braces stay together.
- `SeanMorris/no_saccade_separator_spacing`
  Spaces parameter and argument commas and `for` header semicolons, preserving comments and line breaks.
- `SeanMorris/no_saccade_declaration_spacing`
  Adds a blank line before declarations, including their PHPDoc and attributes.

- `SeanMorris/no_saccade_leading_comma_lists`
  Moves commas to the left rail for multiline arrays, `array()` and `list()` forms, vertical calls, and destructuring patterns.
- `SeanMorris/no_saccade_final_comma_line`
  Controls final comma-only lines before multiline array closers. The recommended preset uses `forbid`; the fixer also supports `allow` and `require`.
- `SeanMorris/no_saccade_leading_operators`
  Moves continuation operators to the beginning of the next line.
- `SeanMorris/no_saccade_allman_tabs`
  Enforces Allman braces, tab indentation, aligned closing delimiters, and complete-argument call wrapping.
- `SeanMorris/no_saccade_no_double_closing_gap`
  Forbids extra blank lines between delimiter-only lines: no blank lines for `open -> open` or `close -> close`, and at most one blank line for `close -> open`.
- `SeanMorris/no_saccade_no_space_control_paren`
  Enforces tight control parens like `if($ready)`.
- `SeanMorris/no_saccade_no_trailing_whitespace`
  Strips trailing spaces and tabs unless they are part of string content.
- `multiline_whitespace_before_semicolons`
  Keeps semicolons attached to the instruction they terminate.
- `semicolon_after_instruction`
  Requires semicolons after PHP instructions.
- `single_blank_line_at_eof`
  Requires one final trailing newline at end of file.
