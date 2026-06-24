<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class NoTrailingWhitespaceFixer extends AbstractNoSaccadeFixer
{
	protected static function ruleName(): string
	{
		return 'no_saccade_no_trailing_whitespace';
	}

	protected static function summary(): string
	{
		return 'Removes trailing whitespace outside string literals.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withoutTrailingWhitespace($code);
	}
}
