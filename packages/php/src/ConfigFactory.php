<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle;

use PhpCsFixer\Config;
use PhpCsFixer\ConfigInterface;
use PhpCsFixer\Finder;

final class ConfigFactory
{
	public static function recommended(?iterable $finder = null): ConfigInterface
	{
		$finder ??= Finder::create()
			->files()
			->name('*.php')
			->exclude('vendor')
			->in(getcwd());

		return (new Config('sm-no-saccade-style'))
			->registerCustomFixers(Fixers::all())
			->setIndent("\t")
			->setLineEnding("\n")
			->setRules([
				Fixers::LEADING_COMMA_LISTS => true
				, Fixers::FINAL_COMMA_LINE => ['mode' => 'forbid']
				, Fixers::LEADING_OPERATORS => true
				, Fixers::ALLMAN_TABS => true
				, Fixers::NO_DOUBLE_CLOSING_GAP => true
				, Fixers::NO_SPACE_CONTROL_PAREN => true
				, Fixers::NO_TRAILING_WHITESPACE => true
				, 'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line']
				, 'semicolon_after_instruction' => true
				, 'single_blank_line_at_eof' => true
			])
			->setFinder($finder);
	}
}
