<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\ReadableLayout;

final class StatementSpacingFixer extends AbstractNoSaccadeFixer
{

	public function getPriority(): int
	{
		return -10;
	}

	protected static function ruleName(): string
	{
		return 'no_saccade_statement_spacing';
	}

	protected static function summary(): string
	{
		return 'Separates completed blocks, controls and multiline calls from adjacent statements.';
	}

	protected function transform(string $code): string
	{
		return ReadableLayout::separateStatements($code);
	}
}
