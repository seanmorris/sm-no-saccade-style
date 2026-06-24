<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class LeadingCommaListsFixer extends AbstractNoSaccadeFixer
{
	protected static function ruleName(): string
	{
		return 'no_saccade_leading_comma_lists';
	}

	protected static function summary(): string
	{
		return 'Moves multiline array and destructuring commas to the beginning of continued lines.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withLeadingCommaLists($code);
	}
}
