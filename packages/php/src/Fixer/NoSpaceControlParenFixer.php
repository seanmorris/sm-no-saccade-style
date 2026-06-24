<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class NoSpaceControlParenFixer extends AbstractNoSaccadeFixer
{
	protected static function ruleName(): string
	{
		return 'no_saccade_no_space_control_paren';
	}

	protected static function summary(): string
	{
		return 'Removes spaces between PHP control keywords and their opening parentheses.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withoutControlParenSpaces($code);
	}
}
