<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class LeadingOperatorsFixer extends AbstractNoSaccadeFixer
{
	protected static function ruleName(): string
	{
		return 'no_saccade_leading_operators';
	}

	protected static function summary(): string
	{
		return 'Moves multiline continuation operators to the beginning of continued lines.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withLeadingOperators($code);
	}
}
