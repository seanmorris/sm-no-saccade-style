# seanmorris/sm-no-saccade-style

PHP-CS-Fixer companion package for the no-saccade style.

## Install

```sh
composer require --dev seanmorris/sm-no-saccade-style
```

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

## Advanced Usage

Register the fixers in your own config:

```php
<?php

$config = new PhpCsFixer\Config();

$config->registerCustomFixers(SeanMorris\NoSaccadeStyle\Fixers::all());
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

return $config;
```
