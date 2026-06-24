<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle;

use SeanMorris\NoSaccadeStyle\Fixer\AllmanTabsFixer;
use SeanMorris\NoSaccadeStyle\Fixer\FinalCommaLineFixer;
use SeanMorris\NoSaccadeStyle\Fixer\LeadingCommaListsFixer;
use SeanMorris\NoSaccadeStyle\Fixer\LeadingOperatorsFixer;
use SeanMorris\NoSaccadeStyle\Fixer\NoDoubleClosingGapFixer;
use SeanMorris\NoSaccadeStyle\Fixer\NoSpaceControlParenFixer;
use SeanMorris\NoSaccadeStyle\Fixer\NoTrailingWhitespaceFixer;

final class Fixers
{
	public const ALLMAN_TABS = 'SeanMorris/no_saccade_allman_tabs';
	public const FINAL_COMMA_LINE = 'SeanMorris/no_saccade_final_comma_line';
	public const LEADING_COMMA_LISTS = 'SeanMorris/no_saccade_leading_comma_lists';
	public const LEADING_OPERATORS = 'SeanMorris/no_saccade_leading_operators';
	public const NO_DOUBLE_CLOSING_GAP = 'SeanMorris/no_saccade_no_double_closing_gap';
	public const NO_SPACE_CONTROL_PAREN = 'SeanMorris/no_saccade_no_space_control_paren';
	public const NO_TRAILING_WHITESPACE = 'SeanMorris/no_saccade_no_trailing_whitespace';

	public static function all(): array
	{
		return [
			new LeadingCommaListsFixer()
			, new FinalCommaLineFixer()
			, new LeadingOperatorsFixer()
			, new AllmanTabsFixer()
			, new NoDoubleClosingGapFixer()
			, new NoSpaceControlParenFixer()
			, new NoTrailingWhitespaceFixer()
		];
	}
}
