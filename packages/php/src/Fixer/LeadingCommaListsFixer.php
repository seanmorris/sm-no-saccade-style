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
		return 'Puts multiline list commas at the beginning of continued lines.';
	}

	protected function transform(string $code): string
	{
		for($pass = 0; $pass < 16; $pass++)
		{
			$before = $code;
			$code = SourceTransformer::withLeadingCommaLists($code);

			if($code === $before) break;
		}
		return $code;
	}
}
