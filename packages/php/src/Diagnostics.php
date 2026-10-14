<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle;

final class Diagnostics
{

	public static function columns(string $text, int $tabWidth = 4): int
	{
		$column = 0;

		foreach(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text) as $character)
		{
			$column += $character === "\t" ? $tabWidth - $column % $tabWidth : 1;
		}

		return $column;
	}

	public static function analyze(
		string $code,
		int $maxLineLength = 100,
		int $maxUnseparatedLines = 10,
		int $tabWidth = 4,
		bool $ignoreLiterals = true
	): array
	{
		if($maxLineLength < 40 || $maxUnseparatedLines < 1 || $tabWidth < 1)
		{
			throw new \InvalidArgumentException(
				'Line length must be at least 40; run length and tab width must be positive.'
			);
		}

		$lines = preg_split('/\r\n|\n|\r/', $code);
		$codeLines = [];
		$excluded = [];

		foreach(\PhpToken::tokenize($code, TOKEN_PARSE) as $token)
		{
			$end = $token->line + substr_count($token->text, "\n");

			if(in_array(
				$token->id
				, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG]
				, true
			)) continue;

			$literal = in_array(
				$token->id
				, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE]
				, true
			);
			preg_match('/^[\t ]*/', $lines[$token->line - 1], $indent);

			for($line = $token->line; $line <= $end; $line++)
			{
				if($token->id === T_INLINE_HTML)
				{
					$excluded[$line] = true;
					continue;
				}

				if(!$literal || $line === $token->line || $line === $end) $codeLines[$line] = true;

				if($ignoreLiterals && $literal && ($end !== $token->line || self::columns(
					$token->text
					, $tabWidth
				) + self::columns($indent[0], $tabWidth) > $maxLineLength
				)){
					$excluded[$line] = true;
				}
			}
		}

		$messages = [];
		$start = 0;
		$count = 0;

		$flush = static function() use (&$messages, &$count, &$start, $maxUnseparatedLines): void {
			if($count > $maxUnseparatedLines)
			{
				$messages[] = ['line' => $start
				, 'severity' => 'warning'
				, 'rule' => 'max-unseparated-lines'
					, 'message' =>
						"$count consecutive code lines without a blank line; "
						. "consider separating semantic blocks (limit $maxUnseparatedLines)."
				];
			}

			$count = 0;
		};

		foreach($lines as $index => $text)
		{
			$line = $index + 1;
			$width = self::columns($text, $tabWidth);

			if($width > $maxLineLength && !isset($excluded[$line]))
			{
				$messages[] = ['line' => $line
				, 'severity' => 'error'
				, 'rule' => 'max-line-length'
					, 'message' =>
						"Line is $width columns; limit is $maxLineLength. "
						. "Wrap at a logical boundary."
				];
			}

			if(trim($text) === '' || !isset($codeLines[$line]))
			{
				$flush();
				continue;
			}

			if($count === 0) $start = $line;

			$count++;
		}

		$flush();

		usort(
			$messages
			, static fn(array $left, array $right): int => $left['line'] <=> $right['line']
		);
		return $messages;
	}
}
