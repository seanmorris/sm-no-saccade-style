<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class NoDoubleClosingGapFixer extends AbstractNoSaccadeFixer
{
	protected static function ruleName(): string
	{
		return 'no_saccade_no_double_closing_gap';
	}

	protected static function summary(): string
	{
		return 'Removes extra blank lines between adjacent structural delimiters.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withoutDoubleClosingGap($code);
	}
}
