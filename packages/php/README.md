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
vendor/bin/php-cs-fixer check --diff
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
	SeanMorris\NoSaccadeStyle\Fixers::LEADING_COMMA_LISTS => true
	, SeanMorris\NoSaccadeStyle\Fixers::FINAL_COMMA_LINE => ['mode' => 'forbid']
	, SeanMorris\NoSaccadeStyle\Fixers::LEADING_OPERATORS => true
	, SeanMorris\NoSaccadeStyle\Fixers::ALLMAN_TABS => true
	, SeanMorris\NoSaccadeStyle\Fixers::NO_DOUBLE_CLOSING_GAP => true
	, SeanMorris\NoSaccadeStyle\Fixers::NO_SPACE_CONTROL_PAREN => true
	, SeanMorris\NoSaccadeStyle\Fixers::NO_TRAILING_WHITESPACE => true
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

## Style Guide

This section describes how to lay out PHP so it already matches the formatter and lint rules this package enforces.

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

Short grouped rows are allowed when they stay compact.

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

Ternary markers also lead the continued line.

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

Multiline control heads keep `{` on the same line as the closing `)`.

```php
<?php

if($primaryTarget
	&& !isset($visited[$primaryTarget])
	&& !$queue
){
	visit($primaryTarget);
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

Empty inline bodies are allowed for compact declarations.

```php
<?php

final class NullLogger
{
	public function info(): void {}
}

if($disabled){}
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

Do not add extra blank lines between adjacent delimiter-only lines.

```php
<?php

$config = [
	'range' => [
		$start
		, $end
	]
];
```

Do not stack closing delimiters from different indentation rails on the same line when they belong to different openings.

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

- `SeanMorris/no_saccade_leading_comma_lists`
  Moves commas to the left rail for multiline arrays, `array()` and `list()` forms, vertical calls, and destructuring patterns.
- `SeanMorris/no_saccade_final_comma_line`
  Controls final comma-only lines before multiline array closers. The recommended preset uses `forbid`; the fixer also supports `allow` and `require`.
- `SeanMorris/no_saccade_leading_operators`
  Moves continuation operators to the beginning of the next line.
- `SeanMorris/no_saccade_allman_tabs`
  Enforces Allman braces, tab indentation, closing delimiter rails, and compact nested call closers.
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
