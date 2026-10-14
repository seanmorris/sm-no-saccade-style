<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class DeclarationSpacingFixer extends AbstractNoSaccadeFixer
{

	public function getPriority(): int
	{
		return 1;
	}

	protected static function ruleName(): string
	{
		return 'no_saccade_declaration_spacing';
	}

	protected static function summary(): string
	{
		return 'Requires a blank line before declarations and their documentation or attributes.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withDeclarationSpacing($code);
	}
}
