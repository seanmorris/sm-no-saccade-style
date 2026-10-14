<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class SeparatorSpacingFixer extends AbstractNoSaccadeFixer
{

	public function getPriority(): int
	{
		return 2;
	}

	protected static function ruleName(): string
	{
		return 'no_saccade_separator_spacing';
	}

	protected static function summary(): string
	{
		return 'Spaces argument commas and for-header semicolons without changing line breaks.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withSeparatorSpacing($code);
	}
}
