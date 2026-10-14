<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Internal;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use SeanMorris\NoSaccadeStyle\Diagnostics;

/** Lay out PHP maps and their containing expressions without rewriting values. */
final class ArrayLayout
{

	private static function visit(string $code, callable $visit): void
	{
		ReadableLayout::walk(
			(new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? []
			, $visit
		);
	}

	private static function positions(array $tokens): array
	{
		$result = [];

		foreach($tokens as $index => $token) $result[$token->pos] = $index;

		return $result;
	}

	private static function gap(
		string $code
		, int $start
		, int $end
		, string $text
		, array &$edits
	): void {
		$actual = substr($code, $start, $end - $start);

		if(trim($actual) !== '') return;

		if(str_starts_with($text, "\n"))
		$text = str_repeat("\n", max(1, substr_count($actual, "\n"))) . substr($text, 1);

		if($actual !== $text) $edits[$start] = [$end, $text];
	}

	/** Expand keyed arrays before line wrapping can split their short values. */
	public static function maps(string $code, array $tokens, array $pairs, int $max): string
	{
		$positions = self::positions($tokens);
		$edits = [];
		$covered = -1;

		self::visit(
			$code
			, static function(
				Node $node
			) use ($code, $tokens, $pairs, $positions, $max, &$edits, &$covered): void {
				if(!$node instanceof Expr\Array_ && !$node instanceof Expr\List_) return;

				if($node->getStartFilePos() < $covered || !$node->items) return;

				if(!array_filter($node->items, static fn($item) => $item?->key !== null)) return;

				$close = $positions[$node->getEndFilePos()] ?? null;
				$open = $pairs[$close] ?? null;

				if($open === null) return;

				$lineStart = strrpos(substr($code, 0, $node->getStartFilePos()), "\n");
				$lineStart = $lineStart === false ? 0 : $lineStart + 1;
				$lineEnd = strpos($code, "\n", $node->getStartFilePos());

				$line = substr(
					$code
					, $lineStart
					, ($lineEnd === false ? strlen($code) : $lineEnd) - $lineStart
				);

				if($node->getStartLine() === $node->getEndLine() && Diagnostics::columns(
					$line
				) <= $max
				)
				return;

				$before = count($edits);
				$indent = ReadableLayout::indent($code, $tokens[$open]->pos);
				$first = $open + 1;

				while($tokens[$first]->id === T_WHITESPACE) $first++;

				self::gap(
					$code
					, $tokens[$open]->pos + 1
					, $tokens[$first]->pos
					, "\n{$indent}\t"
					, $edits
				);

				$items = array_values(array_filter($node->items));

				foreach($items as $index => $item)
				{
					$start = $positions[$item->getStartFilePos()];
					$end = $positions[$item->getEndFilePos()] ?? $start;

					while(isset($tokens[$end]) && $tokens[$end]->pos <= $item->getEndFilePos())
					$end++;

					if($index)
					{
						$left = $items[$index - 1]->getEndFilePos() + 1;
						$comma = $start - 1;

						while($tokens[$comma]->pos >= $left && $tokens[$comma]->id !== ord(','))
						$comma--;

						if($tokens[$comma]->id === ord(','))
						{
							$between = substr($code, $left, $item->getStartFilePos() - $left);

							// Keep inter-entry comments attached.
							if(preg_match('/^\s*,\s*$/', $between))
							{
								self::gap(
									$code
									, $left
									, $tokens[$comma]->pos
									, "\n{$indent}\t"
									, $edits
								);

								self::gap(
									$code
									, $tokens[$comma]->pos + 1
									, $item->getStartFilePos()
									, ' '
									, $edits
								);
							}
							else
							{
								$next = $comma + 1;

								while($tokens[$next]->id === T_WHITESPACE) $next++;

								if(str_starts_with($tokens[$next]->text, '/*'))
								{
									self::gap(
										$code
										, $tokens[$comma]->pos + 1
										, $tokens[$next]->pos
										, "\n{$indent}\t"
										, $edits
									);
								}
								elseif($next === $start)
								{
									$previous = $comma - 1;

									while($tokens[$previous]->id === T_WHITESPACE) $previous--;

									$commentEnd = $tokens[$previous]->pos + strlen(
										$tokens[$previous]->text
									);

									self::gap(
										$code
										, $commentEnd
										, $tokens[$comma]->pos
										, "\n{$indent}\t"
										, $edits
									);

									self::gap(
										$code
										, $tokens[$comma]->pos + 1
										, $item->getStartFilePos()
										, ' '
										, $edits
									);
								}
							}
						}
					}

					$flat = ReadableLayout::inline($tokens, $start, $end);

					$actual = substr(
						$code
						, $item->getStartFilePos()
						, $item->getEndFilePos() + 1 - $item->getStartFilePos()
					);

					if($flat === null || !str_contains($actual, "\n")) continue;

					if(Diagnostics::columns($indent . "\t, " . $flat) > $max) continue;

					$edits[$item->getStartFilePos()] = [$item->getEndFilePos() + 1, $flat];
				}

				$last = $close - 1;

				while($tokens[$last]->id === T_WHITESPACE) $last--;

				self::gap(
					$code
					, $tokens[$last]->pos + strlen($tokens[$last]->text)
					, $tokens[$close]->pos
					, "\n{$indent}"
					, $edits
				);

				if(count($edits) > $before) $covered = $node->getEndFilePos() + 1;
			}
		);

		return ReadableLayout::apply($code, $edits);
	}

	/** Keep a sole expanded array attached to its call when no comments intervene. */
	public static function calls(string $code, array $tokens, array $pairs, int $max): string
	{
		$positions = self::positions($tokens);
		$edits = [];

		self::visit(
			$code
			, static function(
				Node $node
			) use ($code, $tokens, $pairs, $positions, $max, &$edits): void {
				if(!$node instanceof Expr\CallLike && !$node instanceof Expr\New_) return;

				$args = $node->getRawArgs();

				if(count(
					$args
				) !== 1 || !$args[0] instanceof Node\Arg || $args[0]->unpack || $args[0]->name
				)
				return;

				$array = $args[0]->value;

				if(!$array instanceof Expr\Array_ || $array->getStartLine() === $array
					->getEndLine()
				)
				return;

				$close = $positions[$node->getEndFilePos()] ?? null;
				$open = $pairs[$close] ?? null;

				if($open === null || $tokens[$open]->text !== '(') return;

				$before = substr(
					$code
					, $tokens[$open]->pos + 1
					, $array->getStartFilePos() - $tokens[$open]->pos - 1
				);

				$after = substr(
					$code
					, $array->getEndFilePos() + 1
					, $tokens[$close]->pos - $array->getEndFilePos() - 1
				);

				if(trim($before) !== '' || trim($after) !== '') return;

				$lineStart = strrpos(substr($code, 0, $tokens[$open]->pos), "\n");
				$lineStart = $lineStart === false ? 0 : $lineStart + 1;
				$head = substr($code, $lineStart, $tokens[$open]->pos + 1 - $lineStart);

				$opening = $array->getAttribute('kind') === Expr\Array_::KIND_LONG ? 'array(' : '[';

				if(Diagnostics::columns($head . $opening) > $max) return;

				self::gap($code, $tokens[$open]->pos + 1, $array->getStartFilePos(), '', $edits);
				self::gap($code, $array->getEndFilePos() + 1, $tokens[$close]->pos, '', $edits);
			}
		);

		return ReadableLayout::apply($code, $edits);
	}

	/** Finish an array-based foreach header on the array's closing rail. */
	public static function foreachTails(string $code, array $tokens, array $pairs, int $max): string
	{
		$positions = self::positions($tokens);
		$opens = array_flip($pairs);
		$edits = [];

		self::visit(
			$code
			, static function(
				Node $node
			) use ($code, $tokens, $positions, $opens, $max, &$edits): void {
				if(!$node instanceof Stmt\Foreach_ || !$node->expr instanceof Expr\Array_) return;

				$array = $node->expr;

				if($array->getStartLine() === $array->getEndLine()) return;

				$open = $positions[$node->getStartFilePos()] + 1;

				while($tokens[$open]->id === T_WHITESPACE) $open++;
				$close = $opens[$open] ?? null;
				$start = $positions[$array->getEndFilePos()] ?? null;

				if($close === null || $start === null) return;

				$indent = ReadableLayout::indent($code, $node->getStartFilePos());
				$tail = ReadableLayout::inline($tokens, $start, $close + 1);

				if($tail === null || Diagnostics::columns($indent . $tail) > $max) return;

				$edits[$tokens[$start]->pos] = [$tokens[$close]->pos + 1, $tail];
				$brace = $close + 1;

				while(isset($tokens[$brace]) && $tokens[$brace]->id === T_WHITESPACE) $brace++;

				if(isset($tokens[$brace]) && $tokens[$brace]->text === '{')
				{
					self::gap(
						$code
						, $tokens[$close]->pos + 1
						, $tokens[$brace]->pos
						, "\n{$indent}"
						, $edits
					);
				}
			}
		);

		return ReadableLayout::apply($code, $edits);
	}
}
