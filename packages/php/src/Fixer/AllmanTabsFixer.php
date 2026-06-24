<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class AllmanTabsFixer extends AbstractNoSaccadeFixer
{
	protected static function ruleName(): string
	{
		return 'no_saccade_allman_tabs';
	}

	protected static function summary(): string
	{
		return 'Enforces Allman braces, tab indentation, and closing delimiter rails.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withAllmanTabs($code);
	}
}
