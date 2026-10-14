<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Internal;

final class SourceTransformer
{
	private const BINARY_OPERATOR_TEXTS = [
		'&&' => true
		, '||' => true
		, 'and' => true
		, 'or' => true
		, 'xor' => true
		, '+' => true
		, '-' => true
		, '*' => true
		, '/' => true
		, '%' => true
		, '.' => true
		, '??' => true
		, '?' => true
		, ':' => true
	];

	private const CLOSING_DELIMITERS = [
		')' => '('
		, ']' => '['
		, '}' => '{'
	];

	private const CONTROL_TOKEN_IDS = [
		T_CATCH => true
		, T_ELSEIF => true
		, T_FOR => true
		, T_FOREACH => true
		, T_IF => true
		, T_SWITCH => true
		, T_WHILE => true
	];

	private const OPENING_DELIMITERS = [
		'(' => ')'
		, '[' => ']'
		, '{' => '}'
	];

	private const STRING_TOKEN_IDS = [
		T_CONSTANT_ENCAPSED_STRING => true
		, T_ENCAPSED_AND_WHITESPACE => true
		, T_END_HEREDOC => true
		, T_START_HEREDOC => true
	];

	public static function withAllmanTabs(string $code, int $maxLineLength = 100): string
	{
		// Expanding an outer call can expose a nested call that now needs its own layout.
		for($pass = 0; $pass < 16; $pass++)
		{
			$before = $code;
			$code = ExpressionLayout::assignmentDocs($code);
			$code = self::withDynamicMemberBraceSpacing($code);
			$code = self::withBlockLayout($code, $maxLineLength);
			$code = self::withLineLength($code, $maxLineLength);
			$code = self::withLeadingCommaLists($code);
			$code = self::withBlockLayout($code, $maxLineLength);
			$code = self::withAllmanBraces($code, $maxLineLength);
			$code = self::withTabIndentation($code);
			$indentation = new \PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer();
			$indentation->setWhitespacesConfig(new \PhpCsFixer\WhitespacesFixerConfig("\t", "\n"));
			$tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
			$indentation->fix(new \SplFileInfo(__FILE__), $tokens);
			$code = $tokens->generateCode();
			$code = self::withClassPropertyAlignment($code);
			$code = self::withClosingRails($code);
			$tokens = self::tokens($code);

			$code = ArrayLayout::foreachTails(
				$code
				, $tokens
				, self::closingRailPairs($tokens)
				, $maxLineLength
			);

			$code = ExpressionLayout::stackedIndent($code);


			if($code === $before) break;
		}

		return $code;
	}

	private static function withLineLength(string $code, int $maxLineLength): string
	{
		$tokens = self::tokens($code);
		$code = ArrayLayout::calls($code, $tokens, self::closingRailPairs($tokens), $maxLineLength);
		$tokens = self::tokens($code);
		$code = ArrayLayout::maps($code, $tokens, self::closingRailPairs($tokens), $maxLineLength);
		$code = ExpressionLayout::shortMembers($code, $maxLineLength);
		$code = ExpressionLayout::splitControls($code, $maxLineLength);
		$tokens = self::tokens($code);

		$code = ReadableLayout::wrapCalls(
			$code
			, $tokens
			, self::closingRailPairs($tokens)
			, $maxLineLength
		);

		$tokens = self::tokens($code);

		$code = ExpressionLayout::parameters(
			$code
			, $tokens
			, self::closingRailPairs($tokens)
			, $maxLineLength
		);

		$code = ExpressionLayout::ternaries($code, $maxLineLength);
		$tokens = self::tokens($code);
		$interpolated = self::interpolationTokenMap($tokens);
		$byLine = [];

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index])) continue;

			if(in_array($token->text, [',', '&&', '||', '??', '.', '->', '?->'], true))
			{
				$byLine[$token->line][] = [$token->pos, $token->pos];
			}
			elseif(in_array($token->text, ['(', '[', '=', '=>', ';', ':'], true))
			{
				$next = $tokens[$index + 1] ?? null;

				if($next !== null && !in_array($next->text, [')', ']'], true))
				{
					$end = self::tokenEnd($token);
					$byLine[$token->line][] = [$end, $end];
				}
			}
		}

		$replacements = [];

		foreach($byLine as $line => $candidates)
		{
			$text = self::lineText($code, $line);

			if(\SeanMorris\NoSaccadeStyle\Diagnostics::columns($text) <= $maxLineLength) continue;

			$start = self::lineStartOffset($code, $line);
			$end = $start + strlen($text);
			$indent = self::lineIndent($code, $line) . "\t";
			$prefix = '';

			$concats = [];

			foreach($candidates as $candidate)
			if(($code[$candidate[0]] ?? '') === '.')
			{
				$concats[] = $candidate;
			}

			while(\SeanMorris\NoSaccadeStyle\Diagnostics::columns(
				$prefix . substr($code, $start, $end - $start)
			) > $maxLineLength
			){
				$chosen = null;

				foreach($concats ?: $candidates as [$position])
				{
					if($position <= $start || trim(substr($code, $start, $position - $start)) === ''
					) continue;

					$width = \SeanMorris\NoSaccadeStyle\Diagnostics::columns(
						$prefix . substr($code, $start, $position - $start)
					);

					if($width <= $maxLineLength) $chosen = $position;
				}

				if($chosen === null) break;

				$left = $chosen;
				$right = $chosen;

				while($left > $start && str_contains(" \t", $code[$left - 1])) $left--;

				while($right < $end && str_contains(" \t", $code[$right])) $right++;
				$replacements[] = [$left, $right, "\n" . $indent];
				$prefix = $indent;
				$start = $right;
			}
		}

		return self::applyReplacements($code, $replacements);
	}

	public static function withSeparatorSpacing(string $code): string
	{
		$tokens = self::tokens($code);
		$protected = self::interpolationTokenMap($tokens);
		$stack = [];
		$replacements = [];

		foreach($tokens as $index => $token)
		{
			if(isset($protected[$index])) continue;

			if(isset(self::OPENING_DELIMITERS[$token->text]) || $token->id === T_ATTRIBUTE)
			{
				$previous = self::previousSignificant($tokens, $index);
				$stack[] = [
					$token->id === T_ATTRIBUTE ? '[' : $token->text
					, $previous !== null && $tokens[$previous]->id === T_FOR
				];

				continue;
			}

			if(isset(self::CLOSING_DELIMITERS[$token->text]))
			{
				array_pop($stack);
				continue;
			}

			$parent = end($stack);

			if($parent === false || $parent[0] !== '(') continue;

			if($token->text !== ',' && !($token->text === ';' && $parent[1])) continue;

			$next = $index + 1;

			while(isset($tokens[$next]) && $tokens[$next]->id === T_WHITESPACE) $next++;

			if(!isset($tokens[$next]) || $tokens[$next]->text === ')') continue;

			$start = self::tokenEnd($token);
			$end = $tokens[$next]->pos;
			$gap = substr($code, $start, $end - $start);

			if($gap !== ' ' && !str_contains($gap, "\n") && !str_contains($gap, "\r"))
			{
				$replacements[] = [$start, $end, ' '];
			}
		}

		return self::applyReplacements($code, $replacements);
	}

	public static function withDeclarationSpacing(string $code): string
	{
		$tokens = self::tokens($code);
		$replacements = [];
		$declarations = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION, T_FN];
		$modifiers = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY];

		foreach($tokens as $index => $token)
		{
			if(!in_array($token->id, $declarations, true)) continue;

			$previous = self::previousSignificant($tokens, $index);

			if($previous !== null && in_array(
				$tokens[$previous]->id
				, [T_NEW, T_DOUBLE_COLON, T_USE]
				, true
			)) continue;

			$anchor = $index;

			if($token->id === T_FUNCTION || $token->id === T_FN)
			{
				$next = self::nextSignificant($tokens, $index);

				if($next !== null && $tokens[$next]->text === '&')
				{
					$next = self::nextSignificant($tokens, $next);
				}

				if($next === null) continue;

				if($tokens[$next]->text === '(')
				{
					$assignment = self::previousSignificant($tokens, $index);

					if($assignment !== null && $tokens[$assignment]->id === T_STATIC)
					{
						$assignment = self::previousSignificant($tokens, $assignment);
					}

					if($assignment === null || $tokens[$assignment]->text !== '=') continue;

					$variable = self::previousSignificant($tokens, $assignment);

					if($variable === null || $tokens[$variable]->id !== T_VARIABLE) continue;

					$anchor = $variable;
				}
			}

			$start = $anchor;
			$cursor = $anchor - 1;

			while($cursor >= 0)
			{
				$current = $tokens[$cursor];

				if($current->id === T_WHITESPACE)
				{
					$cursor--;
					continue;
				}

				if($current->id === T_DOC_COMMENT || in_array($current->id, $modifiers, true))
				{
					$start = $cursor--;
					continue;
				}

				if($current->text === ']')
				{
					$depth = 1;
					$attribute = $cursor - 1;

					for(; $attribute >= 0; $attribute--)
					{
						$text = $tokens[$attribute]->text;

						if($text === ']') $depth++;

						if(($text === '[' || $tokens[$attribute]->id === T_ATTRIBUTE)
							&& --$depth === 0
						) break;
					}

					if($attribute >= 0 && $tokens[$attribute]->id === T_ATTRIBUTE)
					{
						$start = $attribute;
						$cursor = $attribute - 1;
						continue;
					}
				}

				break;
			}

			if($cursor < 0) continue;

			$left = $tokens[$cursor];
			$first = $tokens[$start];
			$between = substr($code, self::tokenEnd($left), $first->pos - self::tokenEnd($left));
			$newlines = $left->id === T_OPEN_TAG ? 1 : 2;

			if(substr_count($between, "\n") >= $newlines) continue;

			$indent = self::lineIndent($code, $first->line);
			$replacements[] = [
				self::tokenEnd($left), $first->pos
				, str_repeat("\n", $newlines)
				. $indent
			];
		}

		return self::applyReplacements($code, $replacements);
	}

	private static function compactBraces(string $code, array $tokens, int $maxLineLength): array
	{
		$pairs = self::closingRailPairs($tokens);
		$compact = [];

		foreach($pairs as $close => $open)
		{
			if($tokens[$open]->text !== '{') continue;

			$head = $open - 1;
			$mandatory = false;

			while($head >= 0)
			{
				$token = $tokens[$head];

				if(in_array(
					$token->id
					, [
						T_FUNCTION
						, T_CLASS
						, T_INTERFACE
						, T_TRAIT
						, T_ENUM
						, T_TRY
						, T_CATCH
						, T_FINALLY
						, T_SWITCH, T_MATCH
					]
					, true
				)){
					$mandatory = true;
					break;
				}

				if(in_array(
					$token->id
					, [T_IF, T_ELSEIF, T_ELSE, T_FOR, T_FOREACH, T_WHILE, T_DO]
					, true
				)
					|| in_array($token->text, [';', '{', '}'], true)
				) break;

				$head--;
			}

			if(!$mandatory) continue;

			while(in_array($tokens[$head]->id, [T_CATCH, T_FINALLY], true))
			{
				$priorClose = self::previousSignificant($tokens, $head);
				$priorOpen = $pairs[$priorClose] ?? null;

				if($priorOpen === null) break;

				$head = $priorOpen - 1;

				while($head > 0 && !in_array(
					$tokens[$head]->id
					, [T_TRY, T_CATCH, T_FINALLY]
					, true
				)) $head--;
			}

			if($tokens[$head]->line !== $tokens[$close]->line) continue;

			if(\SeanMorris\NoSaccadeStyle\Diagnostics::columns(
				self::lineText($code, $tokens[$head]->line)
			) > $maxLineLength
			) continue;

			// A try body is compact only when its complete catch/finally chain is on the same line.
			if(in_array($tokens[$head]->id, [T_TRY, T_CATCH, T_FINALLY], true))
			{
				$line = $tokens[$head]->line;
				$cursor = self::nextSignificant($tokens, $close);
				$allOnLine = true;

				while($cursor !== null && in_array(
					$tokens[$cursor]->id
					, [T_CATCH, T_FINALLY]
					, true
				)){
					while(isset($tokens[$cursor]) && $tokens[$cursor]->text !== '{') $cursor++;
					$end = array_search($cursor, $pairs, true);

					if($end === false || $tokens[$end]->line !== $line)
					{
						$allOnLine = false;
						break;
					}

					$cursor = self::nextSignificant($tokens, $end);
				}

				if(!$allOnLine) continue;
			}

			$compact[$open] = $close;
		}

		return $compact;
	}

	private static function withBlockLayout(string $code, int $maxLineLength): string
	{
		$tokens = self::tokens($code);
		$protected = self::interpolationTokenMap($tokens);
		$pairs = self::closingRailPairs($tokens);
		$opens = array_flip($pairs);
		$compact = self::compactBraces($code, $tokens, $maxLineLength);
		$replacements = [];
		$stack = [];

		$separate = static function(
			int $left
			, int $right
		) use ($code, $tokens
			, &$replacements
		): void {
			$start = self::tokenEnd($tokens[$left]);
			$end = $tokens[$right]->pos;
			$gap = substr($code, $start, $end - $start);

			if(trim($gap) === '' && !str_contains($gap, "\n"))
			$replacements[$start] = [
				$start
				, $end
				, "\n"
			];
		};

		$visible = [];

		foreach($tokens as $index => $token)
		{
			if($token->id !== T_WHITESPACE) $visible[] = $index;
		}

		foreach($visible as $position => $index)
		{
			$token = $tokens[$index];

			if(isset($protected[$index])) continue;

			$previous = $visible[$position - 1] ?? null;
			$next = $visible[$position + 1] ?? null;

			if(isset($opens[$index]))
			{
				$stack[] = $index;

				if($token->text === '{' && !isset($compact[$index]) && $next !== null)
				$separate($index, $next);

				continue;
			}

			if(isset($pairs[$index]))
			{
				$open = $pairs[$index];
				array_pop($stack);

				if($token->text === '}' && !isset($compact[$open]) && $previous !== null
					&& $tokens[$previous]->text !== '}'
				) $separate($previous, $index);

				if($token->text === '}' && !isset($compact[end($stack)]) && $next !== null
					&& !in_array(
						$tokens[$next]->text
						, [';', ',', ')', ']', '}', '(', '->', '?->', '::']
						, true
					)
					&& !in_array(
						$tokens[$next]->id
						, [T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY, T_WHILE, T_COMMENT, T_DOC_COMMENT]
						, true
					)
				) $separate($index, $next);

				continue;
			}

			$parent = end($stack);

			if($token->text === ';' && $next !== null && ($parent === false || $tokens[$parent]
				->text === '{'
			)
				&& !isset($compact[$parent]) && !in_array(
					$tokens[$next]->id
					, [T_COMMENT, T_DOC_COMMENT, T_CLOSE_TAG]
					, true
				)
			) $separate($index, $next);
		}

		return self::applyReplacements($code, array_values($replacements));
	}

	public static function withFinalCommaLine(string $code, string $mode): string
	{
		$tokens = self::tokens($code);
		$spans = self::listSpans($tokens);
		$replacements = [];

		foreach($spans as $span)
		{
			if(self::tokenLine($tokens[$span['open']]) === self::tokenLine($tokens[$span['close']]))
			{
				continue;
			}

			$items = self::listItems($tokens, $span);

			if($items['items'] === [])
			{
				continue;
			}

			$lastItem = $items['items'][count($items['items']) - 1];
			$closer = $tokens[$span['close']];
			$finalComma = $items['finalComma'];

			if($finalComma !== null)
			{
				$comma = $tokens[$finalComma];
				$commaOwnLine = self::tokenLine($comma) < self::tokenLine($closer)
					&& self::isFirstNonWhitespaceOnLine($code, $comma->pos);

				if($mode === 'forbid')
				{
					if($commaOwnLine && trim(self::lineText($code, self::tokenLine($comma))) === ','
					){
						[$lineStart, $lineEnd] = self::lineRange($code, self::tokenLine($comma));
						$replacements[] = [$lineStart, $lineEnd, ''];
					}
					else
					{
						$replacements[] = [$comma->pos, self::tokenEnd($comma), ''];
					}

					continue;
				}

				if(!$commaOwnLine && !self::hasCommentBetween($tokens, $finalComma, $span['close']))
				{
					$itemIndent = self::lineIndent(
						$code
						, self::tokenLine($tokens[$lastItem['start']])
					);

					$closerIndent = self::lineIndent($code, self::tokenLine($closer));
					$comment = self::lastCommentBetween($tokens, $lastItem['end'], $finalComma);
					$start = $comment === null
						? self::tokenEnd($tokens[$lastItem['end']])
						: self::tokenEnd($tokens[$comment]);

					$replacements[] = [
						$start
						, $closer->pos
						, "\n{$itemIndent},\n{$closerIndent}"
					];
				}

				continue;
			}

			if($mode === 'require' && self::tokenLine($closer) > self::tokenEndLine(
				$tokens[$lastItem['end']]
			)){
				$itemIndent = self::lineIndent($code, self::tokenLine($tokens[$lastItem['start']]));
				$closerIndent = self::lineIndent($code, self::tokenLine($closer));
				$replacements[] = [$closer->pos, $closer->pos, "{$itemIndent},\n{$closerIndent}"];
			}
		}

		return self::applyReplacements($code, $replacements);
	}

	public static function withLeadingCommaLists(string $code): string
	{
		$tokens = self::tokens($code);
		$spans = self::listSpans($tokens);
		$replacements = [];

		foreach($spans as $span)
		{
			if(self::tokenLine($tokens[$span['open']]) === self::tokenLine($tokens[$span['close']]))
			{
				continue;
			}

			$items = self::listItems($tokens, $span)['items'];

			if(count($items) < 2)
			{
				continue;
			}

			$replacements = array_merge(
				$replacements
				, self::groupedRowReplacements($code, $tokens, $items)
			);

			for($index = 1; $index < count($items); $index += 1)
			{
				$item = $items[$index];
				$previousItem = $items[$index - 1];
				$commaIndex = $item['commaBefore'];

				if($commaIndex === null)
				{
					continue;
				}

				$comma = $tokens[$commaIndex];
				$itemAnchor = self::singleDocCommentBetween($tokens, $commaIndex, $item['start'])
					?? $item['start'];

				$itemToken = $tokens[$itemAnchor];

				if(self::isAllowedCompactPair($tokens, $previousItem, $item, $comma))
				{
					continue;
				}

				$commaOnItemLine = self::tokenLine($comma) === self::tokenLine($itemToken);
				$commaLeading = self::isFirstNonWhitespaceOnLine($code, $comma->pos);

				if(!$commaOnItemLine || !$commaLeading)
				{
					if(self::hasCommentBetween($tokens, $commaIndex, $itemAnchor))
					{
						continue;
					}

					$itemIndent = self::lineIndent($code, self::tokenLine($itemToken));
					$comment = self::lastCommentBetween($tokens, $previousItem['end'], $commaIndex);
					$start = $comment === null
						? self::tokenEnd($tokens[$previousItem['end']])
						: self::tokenEnd($tokens[$comment]);

					$replacements[] = [
						$start
						, $itemToken->pos
						, "\n{$itemIndent}, "
					];

					continue;
				}

				$spacing = substr(
					$code
					, self::tokenEnd($comma)
					, $itemToken->pos - self::tokenEnd($comma)
				);

				if($spacing !== ' ' && !self::hasCommentBetween($tokens, $commaIndex, $itemAnchor))
				{
					$replacements[] = [self::tokenEnd($comma), $itemToken->pos, ' '];
				}
			}
		}

		$code = self::applyReplacements($code, $replacements);
		$tokens = self::tokens($code);
		$spans = self::listSpans($tokens);
		$replacements = [];

		foreach($spans as $span)
		{
			if(self::tokenLine($tokens[$span['open']]) === self::tokenLine($tokens[$span['close']]))
			{
				continue;
			}

			$items = self::listItems($tokens, $span)['items'];

			if(count($items) < 2)
			{
				continue;
			}

			$replacements = array_merge(
				$replacements
				, self::doubleArrowAlignmentReplacements($code, $tokens, $items)
			);
		}

		$code = self::applyReplacements($code, $replacements);
		$tokens = self::tokens($code);

		return self::applyReplacements(
			$code
			, self::doubleArrowTrailingSpaceReplacements($code, $tokens)
		);
	}

	public static function withLeadingOperators(string $code): string
	{
		$tokens = self::tokens($code);
		$interpolated = self::interpolationTokenMap($tokens);
		$replacements = [];

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index]))
			{
				continue;
			}

			$operator = strtolower($token->text);

			if(!isset(self::BINARY_OPERATOR_TEXTS[$operator]))
			{
				continue;
			}

			if($operator === ':' && !self::isTernaryColon($tokens, $index))
			{
				continue;
			}

			$previous = self::previousSignificant($tokens, $index);
			$next = self::nextSignificant($tokens, $index);

			if($previous === null || $next === null)
			{
				continue;
			}

			if(self::tokenEndLine($tokens[$previous]) !== self::tokenLine($token))
			{
				continue;
			}

			if(self::tokenLine($tokens[$next]) <= self::tokenLine($token))
			{
				continue;
			}

			if(self::hasCommentBetween($tokens, $index, $next))
			{
				continue;
			}

			$indent = self::lineIndent($code, self::tokenLine($tokens[$next]));
			$comment = self::lastCommentBetween($tokens, $previous, $index);
			$start = $comment === null
				? self::tokenEnd($tokens[$previous])
				: self::tokenEnd($tokens[$comment]);

			$replacements[] = [
				$start
				, $tokens[$next]->pos
				, "\n{$indent}{$token->text} "
			];
		}

		return self::applyReplacements($code, $replacements);
	}

	public static function withoutControlParenSpaces(string $code): string
	{
		$tokens = self::tokens($code);
		$interpolated = self::interpolationTokenMap($tokens);
		$controlIds = self::CONTROL_TOKEN_IDS;

		if(defined('T_MATCH'))
		{
			$controlIds[T_MATCH] = true;
		}

		$replacements = [];

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index]))
			{
				continue;
			}

			if(!isset($controlIds[$token->id]))
			{
				continue;
			}

			$next = self::nextSignificant($tokens, $index);

			if($next === null || $tokens[$next]->text !== '(')
			{
				continue;
			}

			$between = substr(
				$code
				, self::tokenEnd($token)
				, $tokens[$next]->pos - self::tokenEnd($token)
			);

			if(!preg_match('/^[ \t]+$/', $between))
			{
				continue;
			}

			$replacements[] = [self::tokenEnd($token), $tokens[$next]->pos, ''];
		}

		return self::applyReplacements($code, $replacements);
	}

	public static function withoutDoubleClosingGap(string $code): string
	{
		$tokens = self::tokens($code);
		$significant = [];

		foreach($tokens as $index => $token)
		{
			if(!self::isTrivia($token))
			{
				$significant[] = $index;
			}
		}

		$replacements = [];

		for($index = 0; $index < count($significant) - 1; $index += 1)
		{
			$leftIndex = $significant[$index];
			$rightIndex = $significant[$index + 1];
			$left = $tokens[$leftIndex];
			$right = $tokens[$rightIndex];
			$closeClose = isset(self::CLOSING_DELIMITERS[$left->text], self::CLOSING_DELIMITERS[
				$right->text
			]);

			$openOpen = isset(self::OPENING_DELIMITERS[$left->text], self::OPENING_DELIMITERS[$right
				->text
			]);

			$closeOpen = isset(self::CLOSING_DELIMITERS[$left->text], self::OPENING_DELIMITERS[
				$right->text
			]);

			if(!$closeClose && !$openOpen && !$closeOpen)
			{
				continue;
			}

			$allowedGap = $closeOpen ? 2 : 1;

			if(self::tokenEndLine($left) + $allowedGap >= self::tokenLine($right))
			{
				continue;
			}

			if(self::hasCommentBetween($tokens, $leftIndex, $rightIndex))
			{
				continue;
			}

			$between = substr($code, self::tokenEnd($left), $right->pos - self::tokenEnd($left));

			if(trim($between) !== '')
			{
				continue;
			}

			$rightIndent = self::lineIndent($code, self::tokenLine($right));
			$replacement = $closeOpen ? "\n\n{$rightIndent}" : "\n{$rightIndent}";
			$replacements[] = [self::tokenEnd($left), $right->pos, $replacement];
		}

		return self::applyReplacements($code, $replacements);
	}

	public static function withoutTrailingWhitespace(string $code): string
	{
		$protectedRanges = [];

		foreach(self::tokens($code) as $token)
		{
			if(isset(self::STRING_TOKEN_IDS[$token->id]))
			{
				$protectedRanges[] = [$token->pos, self::tokenEnd($token)];
			}
		}

		preg_match_all('/[ \t]+(?=\n|$)/', $code, $matches, PREG_OFFSET_CAPTURE);
		$replacements = [];

		foreach($matches[0] as [$text, $start])
		{
			$end = $start + strlen($text);

			if(self::rangeInsideAny($start, $end, $protectedRanges))
			{
				continue;
			}

			$replacements[] = [$start, $end, ''];
		}

		return self::applyReplacements($code, $replacements);
	}

	private static function applyReplacements(string $code, array $replacements): string
	{
		if($replacements === [])
		{
			return $code;
		}

		$commentRanges = [];

		foreach(self::tokens($code) as $token)
		{
			if(self::isComment($token))
			{
				$commentRanges[] = [$token->pos, self::tokenEnd($token)];
			}
		}

		usort($replacements, static fn(array $left, array $right): int => $right[0] <=> $left[0]);

		$maxEnd = strlen($code);

		foreach($replacements as [$start, $end, $replacement])
		{
			if($end > $maxEnd
				|| $start > $end
				|| self::rangeOverlapsAny($start, $end, $commentRanges)
			){
				continue;
			}

			$code = substr($code, 0, $start) . $replacement . substr($code, $end);
			$maxEnd = $start;
		}

		return $code;
	}

	private static function closingRailPairs(array $tokens): array
	{
		$interpolated = self::interpolationTokenMap($tokens);
		$dynamicMemberBraces = self::dynamicMemberBraceMap($tokens);
		$stack = [];
		$pairs = [];

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index]) || isset($dynamicMemberBraces[$index]))
			{
				continue;
			}

			if(isset(self::OPENING_DELIMITERS[$token->text]))
			{
				$stack[] = $index;
				continue;
			}

			if(!isset(self::CLOSING_DELIMITERS[$token->text]))
			{
				continue;
			}

			for($stackIndex = count($stack) - 1; $stackIndex >= 0; $stackIndex -= 1)
			{
				$openingIndex = $stack[$stackIndex];

				if($tokens[$openingIndex]->text !== self::CLOSING_DELIMITERS[$token->text])
				{
					continue;
				}

				array_splice($stack, $stackIndex, 1);
				$pairs[$index] = $openingIndex;
				break;
			}
		}

		return $pairs;
	}

	private static function classBodySpans(array $tokens): array
	{
		$spans = [];

		foreach(self::closingRailPairs($tokens) as $close => $open)
		{
			if(self::isClassLikeBodyBrace($tokens, $open))
			{
				$spans[] = [
					'open' => $open
					, 'close' => $close
				];
			}
		}

		return $spans;
	}

	private static function classPropertyInitializerEntries(
		string $code
		, array $tokens
		, array $span
	): array {
		$entries = [];
		$statementStart = $span['open'] + 1;
		$depth = 0;

		for($index = $span['open'] + 1; $index < $span['close']; $index += 1)
		{
			$token = $tokens[$index];

			if(isset(self::OPENING_DELIMITERS[$token->text]))
			{
				$depth += 1;
				continue;
			}

			if(isset(self::CLOSING_DELIMITERS[$token->text]))
			{
				$depth -= 1;
				continue;
			}

			if($token->text !== ';' || $depth !== 0)
			{
				continue;
			}

			$entries = array_merge(
				$entries
				, self::classPropertyStatementInitializerEntries(
					$code
					, $tokens
					, $statementStart
					, $index
				)
			);

			$statementStart = $index + 1;
		}

		return $entries;
	}

	private static function classPropertyStatementInitializerEntries(
		string $code
		, array $tokens
		, int $start
		, int $end
	): array {
		$first = self::firstSignificantInRange($tokens, $start, $end);

		if($first === null)
		{
			return [];
		}

		$hasVariable = false;
		$equalsIndexes = [];
		$depth = 0;

		for($index = $first; $index <= $end; $index += 1)
		{
			$token = $tokens[$index];

			if(isset(self::OPENING_DELIMITERS[$token->text]))
			{
				$depth += 1;
				continue;
			}

			if(isset(self::CLOSING_DELIMITERS[$token->text]))
			{
				$depth -= 1;
				continue;
			}

			if($depth !== 0)
			{
				continue;
			}

			if($token->id === T_FUNCTION || $token->id === T_CONST)
			{
				return [];
			}

			if($token->id === T_VARIABLE)
			{
				$hasVariable = true;
				continue;
			}

			if($token->text === '=')
			{
				$equalsIndexes[] = $index;
			}
		}

		if(!$hasVariable || $equalsIndexes === [])
		{
			return [];
		}

		$entries = [];

		foreach($equalsIndexes as $equalsIndex)
		{
			$left = self::previousSignificant($tokens, $equalsIndex);
			$value = self::nextSignificant($tokens, $equalsIndex);

			if($left === null
				|| $value === null
				|| $left < $first
				|| $value > $end
				|| $tokens[$left]->id !== T_VARIABLE
			){
				continue;
			}

			$entries[] = [
				'anchor' => $first
				, 'left' => $left
				, 'operator' => $equalsIndex
				, 'value' => $value
				, 'before' => substr(
					$code
					, self::tokenEnd($tokens[$left])
					, $tokens[$equalsIndex]->pos - self::tokenEnd($tokens[$left])
				)
				, 'after' => substr(
					$code
					, self::tokenEnd($tokens[$equalsIndex])
					, $tokens[$value]->pos - self::tokenEnd($tokens[$equalsIndex])
				)
			];
		}

		return $entries;
	}

	private static function classPropertyInitializerReplacements(
		string $code
		, array $tokens
		, array $entries
	): array {
		$alignmentEntries = [];
		$targetWidth = 0;
		$shouldAlign = false;

		foreach($entries as $entry)
		{
			if(self::tokenEndLine($tokens[$entry['left']]) !== self::tokenLine(
				$tokens[$entry['operator']]
			)
				|| !preg_match('/^[ \t]*$/', $entry['before'])
				|| self::hasCommentBetween($tokens, $entry['left'], $entry['operator'])
			){
				continue;
			}

			$keyWidth = self::tokenEnd($tokens[$entry['left']]) - $tokens[$entry['anchor']]->pos;
			$targetWidth = max($targetWidth, $keyWidth + 1);
			$shouldAlign = $shouldAlign || strlen($entry['before']) > 1;
			$entry['keyWidth'] = $keyWidth;
			$alignmentEntries[] = $entry;
		}

		$replacements = [];

		foreach($alignmentEntries as $entry)
		{
			$replacement = $shouldAlign
				? str_repeat(' ', max(1, $targetWidth - $entry['keyWidth']))
				: ' ';

			if($entry['before'] !== $replacement && ($shouldAlign || $entry['before'] === ''))
			{
				$replacements[] = [
					self::tokenEnd($tokens[$entry['left']])
					, $tokens[$entry['operator']]->pos
					, $replacement
				];
			}
		}

		foreach($entries as $entry)
		{
			if(self::tokenEndLine($tokens[$entry['operator']]) !== self::tokenLine(
				$tokens[$entry['value']]
			)
				|| !preg_match('/^[ \t]*$/', $entry['after'])
				|| str_contains($entry['after'], ' ')
				|| self::hasCommentBetween($tokens, $entry['operator'], $entry['value'])
			){
				continue;
			}

			$replacements[] = [
				self::tokenEnd($tokens[$entry['operator']])
				, $tokens[$entry['value']]->pos
				, ' '
			];
		}

		return $replacements;
	}

	private static function doubleArrowAlignmentReplacements(
		string $code
		, array $tokens
		, array $items
	): array {
		$entries = [];
		$targetWidth = 0;
		$shouldAlign = false;

		foreach($items as $item)
		{
			$arrowIndex = self::topLevelDoubleArrowInItem($tokens, $item);

			if($arrowIndex === null)
			{
				continue;
			}

			$keyEnd = self::previousSignificant($tokens, $arrowIndex);

			if($keyEnd === null || $keyEnd < $item['start'])
			{
				continue;
			}

			if(self::tokenEndLine($tokens[$keyEnd]) !== self::tokenLine($tokens[$arrowIndex]))
			{
				continue;
			}

			$spacing = substr(
				$code
				, self::tokenEnd($tokens[$keyEnd])
				, $tokens[$arrowIndex]->pos - self::tokenEnd($tokens[$keyEnd])
			);

			if(!preg_match('/^[ \t]+$/', $spacing))
			{
				continue;
			}

			$anchor = $item['start'];

			if($item['commaBefore'] !== null
				&& self::tokenLine($tokens[$item['commaBefore']]) === self::tokenLine(
					$tokens[$item['start']]
				)
				&& self::isFirstNonWhitespaceOnLine($code, $tokens[$item['commaBefore']]->pos)
			){
				$anchor = $item['commaBefore'];
			}

			$keyWidth = self::tokenEnd($tokens[$keyEnd]) - $tokens[$anchor]->pos;
			$targetWidth = max($targetWidth, $keyWidth + 1);
			$shouldAlign = $shouldAlign || $spacing !== ' ';
			$entries[] = [
				'keyEnd' => $keyEnd
				, 'arrow' => $arrowIndex
				, 'keyWidth' => $keyWidth
				, 'spacing' => $spacing
			];
		}

		if(!$shouldAlign || count($entries) < 2)
		{
			return [];
		}

		$replacements = [];

		foreach($entries as $entry)
		{
			$replacement = str_repeat(' ', max(1, $targetWidth - $entry['keyWidth']));

			if($replacement === $entry['spacing'])
			{
				continue;
			}

			$replacements[] = [
				self::tokenEnd($tokens[$entry['keyEnd']])
				, $tokens[$entry['arrow']]->pos
				, $replacement
			];
		}

		return $replacements;
	}

	private static function doubleArrowTrailingSpaceReplacements(string $code, array $tokens): array
	{
		$interpolated = self::interpolationTokenMap($tokens);
		$replacements = [];

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index]) || $token->id !== T_DOUBLE_ARROW)
			{
				continue;
			}

			$next = self::nextSignificant($tokens, $index);

			if($next === null || self::tokenLine($tokens[$next]) !== self::tokenLine($token))
			{
				continue;
			}

			$comment = self::firstCommentBetween($tokens, $index, $next);
			$spacingEnd = $comment === null ? $tokens[$next]->pos : $tokens[$comment]->pos;
			$between = substr($code, self::tokenEnd($token), $spacingEnd - self::tokenEnd($token));

			if(str_contains($between, ' '))
			{
				continue;
			}

			if(!preg_match('/^[\t]*$/', $between))
			{
				continue;
			}

			$replacements[] = [self::tokenEnd($token), $spacingEnd, ' '];
		}

		return $replacements;
	}

	private static function groupedRowReplacements(string $code, array $tokens, array $items): array
	{
		$byLine = [];

		foreach($items as $item)
		{
			$line = self::tokenLine($tokens[$item['start']]);
			$byLine[$line] ??= [];
			$byLine[$line][] = $item;
		}

		$replacements = [];

		foreach($byLine as $line => $lineItems)
		{
			if(count($lineItems) < 2 || strlen(self::lineText($code, $line)) <= 80)
			{
				continue;
			}

			$first = $lineItems[0];
			$last = $lineItems[count($lineItems) - 1];
			$start = $tokens[$first['start']]->pos;

			if($first['commaBefore'] !== null && self::tokenLine(
				$tokens[$first['commaBefore']]
			) === $line
			){
				$start = $tokens[$first['commaBefore']]->pos;
			}

			$end = self::tokenEnd($tokens[$last['end']]);
			$commentBetween = false;

			foreach($tokens as $token)
			{
				if($token->pos < $start || $token->pos >= $end)
				{
					continue;
				}

				if(self::isComment($token))
				{
					$commentBetween = true;
					break;
				}
			}

			if($commentBetween)
			{
				continue;
			}

			$indent = self::lineIndent($code, $line);
			$hasLeadingComma = $first['commaBefore'] !== null
				&& self::tokenLine($tokens[$first['commaBefore']]) === $line
				&& $tokens[$first['commaBefore']]->pos === $start;

			$parts = [];

			foreach($lineItems as $index => $item)
			{
				$text = substr(
					$code
					, $tokens[$item['start']]->pos
					, self::tokenEnd($tokens[$item['end']]) - $tokens[$item['start']]->pos
				);

				if($index === 0 && !$hasLeadingComma)
				{
					$parts[] = $text;
					continue;
				}

				$parts[] = ', ' . $text;
			}

			$replacements[] = [$start, $end, implode("\n{$indent}", $parts)];
		}

		return $replacements;
	}

	private static function dynamicMemberBraceMap(array $tokens): array
	{
		$map = [];

		foreach($tokens as $index => $token)
		{
			if($token->text !== '{')
			{
				continue;
			}

			$previous = self::previousSignificant($tokens, $index);

			if($previous === null || !self::isMemberOperator($tokens[$previous]))
			{
				continue;
			}

			$depth = 0;

			for($cursor = $index; $cursor < count($tokens); $cursor += 1)
			{
				if($tokens[$cursor]->text === '{')
				{
					$depth += 1;
					continue;
				}

				if($tokens[$cursor]->text !== '}')
				{
					continue;
				}

				$depth -= 1;

				if($depth !== 0)
				{
					continue;
				}

				$map[$index] = true;
				$map[$cursor] = true;
				break;
			}
		}

		return $map;
	}

	private static function hasCommentBetween(array $tokens, int $leftIndex, int $rightIndex): bool
	{
		$start = min($leftIndex, $rightIndex) + 1;
		$end = max($leftIndex, $rightIndex);

		for($index = $start; $index < $end; $index += 1)
		{
			if(self::isComment($tokens[$index]))
			{
				return true;
			}
		}

		return false;
	}

	private static function firstCommentBetween(
		array $tokens
		, int $leftIndex
		, int $rightIndex
	): ?int {
		$start = min($leftIndex, $rightIndex) + 1;
		$end = max($leftIndex, $rightIndex);

		for($index = $start; $index < $end; $index += 1)
		{
			if(self::isComment($tokens[$index]))
			{
				return $index;
			}
		}

		return null;
	}

	private static function lastCommentBetween(array $tokens, int $leftIndex, int $rightIndex): ?int
	{
		$start = min($leftIndex, $rightIndex) + 1;
		$end = max($leftIndex, $rightIndex);

		for($index = $end - 1; $index >= $start; $index -= 1)
		{
			if(self::isComment($tokens[$index]))
			{
				return $index;
			}
		}

		return null;
	}

	private static function singleDocCommentBetween(
		array $tokens
		, int $leftIndex
		, int $rightIndex
	): ?int {
		$start = min($leftIndex, $rightIndex) + 1;
		$end = max($leftIndex, $rightIndex);
		$comment = null;

		for($index = $start; $index < $end; $index += 1)
		{
			if(!self::isComment($tokens[$index]))
			{
				continue;
			}

			if($tokens[$index]->id !== T_DOC_COMMENT || $comment !== null)
			{
				return null;
			}

			$comment = $index;
		}

		return $comment;
	}

	private static function isAllowedCompactPair(
		array $tokens
		, array $leftItem
		, array $rightItem
		, \PhpToken $comma
	): bool {
		return self::tokenLine($tokens[$leftItem['start']]) === self::tokenEndLine(
			$tokens[$leftItem['end']]
		)
			&& self::tokenLine($tokens[$rightItem['start']]) === self::tokenEndLine(
				$tokens[$rightItem['end']]
			)
			&& self::tokenEndLine($tokens[$leftItem['end']]) === self::tokenLine(
				$tokens[$rightItem['start']]
			)
			&& self::tokenLine($comma) === self::tokenLine($tokens[$rightItem['start']]);
	}

	private static function isArrayBracket(array $tokens, int $index): bool
	{
		$previous = self::previousSignificant($tokens, $index);

		if($previous === null)
		{
			return true;
		}

		$token = $tokens[$previous];

		if(in_array($token->text, [')', ']', '}', '"'], true))
		{
			return false;
		}

		return !in_array(
			$token->id
			, [
				T_CLASS_C
				, T_CONSTANT_ENCAPSED_STRING
				, T_DIR
				, T_END_HEREDOC
				, T_FILE
				, T_FUNC_C
				, T_LINE
				, T_METHOD_C
				, T_NAME_FULLY_QUALIFIED
				, T_NAME_QUALIFIED
				, T_NAME_RELATIVE
				, T_NS_C
				, T_STRING
				, T_TRAIT_C
				, T_VARIABLE
			]
			, true
		);
	}

	private static function isComment(\PhpToken $token): bool
	{
		return $token->id === T_COMMENT || $token->id === T_DOC_COMMENT;
	}

	private static function isClassLikeBodyBrace(array $tokens, int $braceIndex): bool
	{
		$classLikeIds = [
			T_CLASS => true
			, T_INTERFACE => true
			, T_TRAIT => true
		];

		if(defined('T_ENUM'))
		{
			$classLikeIds[T_ENUM] = true;
		}

		for($index = $braceIndex - 1; $index >= 0; $index -= 1)
		{
			$token = $tokens[$index];

			if(self::isTrivia($token))
			{
				continue;
			}

			if($token->text === ';' || $token->text === '{' || $token->text === '}')
			{
				return false;
			}

			if(isset($classLikeIds[$token->id]))
			{
				return true;
			}
		}

		return false;
	}

	private static function isFirstNonWhitespaceOnLine(string $code, int $offset): bool
	{
		$start = self::lineStartOffsetForOffset($code, $offset);
		$before = substr($code, $start, $offset - $start);

		return trim($before, " \t") === '';
	}

	private static function isFunctionBodyBrace(array $tokens, int $braceIndex): bool
	{
		for($index = $braceIndex - 1; $index >= 0; $index -= 1)
		{
			$token = $tokens[$index];

			if($token->text === ';' || $token->text === '{' || $token->text === '}')
			{
				return false;
			}

			if($token->id === T_FUNCTION)
			{
				return true;
			}
		}

		return false;
	}

	private static function isInlineBrace(array $tokens, int $braceIndex): bool
	{
		for($index = $braceIndex - 1; $index >= 0; $index -= 1)
		{
			$token = $tokens[$index];

			if($token->text === ';' || $token->text === '{' || $token->text === '}')
			{
				return false;
			}

			if($token->id === T_FUNCTION)
			{
				$next = self::nextSignificant($tokens, $index);

				if($next !== null && $tokens[$next]->text === '&')
				{
					$next = self::nextSignificant($tokens, $next);
				}

				return $next !== null && $tokens[$next]->text === '(';
			}

			if($token->id === T_CLASS)
			{
				$previous = self::previousSignificant($tokens, $index);

				return $previous !== null && $tokens[$previous]->id === T_NEW;
			}
		}

		return false;
	}

	private static function isMemberOperator(\PhpToken $token): bool
	{
		return $token->id === T_DOUBLE_COLON
			|| $token->id === T_OBJECT_OPERATOR
			|| (defined('T_NULLSAFE_OBJECT_OPERATOR') && $token->id === T_NULLSAFE_OBJECT_OPERATOR);
	}

	private static function interpolationTokenMap(array $tokens): array
	{
		$map = [];
		$quote = null;
		$heredoc = false;
		$depth = 0;

		foreach($tokens as $index => $token)
		{
			if($token->id === T_START_HEREDOC) $heredoc = true;

			if($heredoc)
			{
				$map[$index] = true;

				if($token->id === T_END_HEREDOC) $heredoc = false;

				continue;
			}

			if($quote !== null)
			{
				$map[$index] = true;

				if($token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES
					|| $token->text === '{'
				) $depth++;
				elseif($token->text === '}' && $depth > 0) $depth--;
				elseif($token->text === $quote && $depth === 0) $quote = null;

				continue;
			}

			if($token->text === '"' || $token->text === '`')
			{
				$quote = $token->text;
				$map[$index] = true;
			}
			elseif(isset(self::STRING_TOKEN_IDS[$token->id])) $map[$index] = true;
		}

		return $map;
	}

	private static function isTernaryColon(array $tokens, int $colonIndex): bool
	{
		$depth = 0;

		for($index = $colonIndex - 1; $index >= 0; $index -= 1)
		{
			$token = $tokens[$index];

			if(self::isTrivia($token))
			{
				continue;
			}

			if(in_array($token->text, [')', ']', '}'], true))
			{
				$depth += 1;
				continue;
			}

			if(in_array($token->text, ['(', '[', '{'], true))
			{
				$depth -= 1;
				continue;
			}

			if($depth === 0 && $token->text === '?')
			{
				return true;
			}

			if($depth === 0 && in_array($token->text, [';', '{', '}'], true))
			{
				return false;
			}

			if($depth === 0 && in_array($token->id, [T_CASE, T_DEFAULT], true))
			{
				return false;
			}
		}

		return false;
	}

	private static function isVerticalCallParen(array $tokens, int $index): bool
	{
		$firstArgument = self::nextSignificant($tokens, $index);

		if($firstArgument === null || self::tokenLine($tokens[$firstArgument]) === self::tokenLine(
			$tokens[$index]
		)){
			return false;
		}

		$previous = self::previousSignificant($tokens, $index);

		if($previous === null)
		{
			return false;
		}

		if(in_array($tokens[$previous]->id, [T_STRING, T_VARIABLE], true))
		{
			$beforeName = self::previousSignificant($tokens, $previous);

			return $beforeName === null || $tokens[$beforeName]->id !== T_FUNCTION;
		}

		return in_array($tokens[$previous]->text, [')', ']'], true);
	}

	private static function isTrivia(\PhpToken $token): bool
	{
		return $token->id === T_WHITESPACE || self::isComment($token);
	}

	private static function lineIndent(string $code, int $line): string
	{
		preg_match('/^[ \t]*/', self::lineText($code, $line), $match);

		return $match[0] ?? '';
	}

	private static function lineRange(string $code, int $line): array
	{
		$start = self::lineStartOffset($code, $line);
		$nextStart = self::lineStartOffset($code, $line + 1);

		return [$start, $nextStart];
	}

	private static function lineStartOffset(string $code, int $line): int
	{
		if($line <= 1)
		{
			return 0;
		}

		$offset = 0;

		for($current = 1; $current < $line; $current += 1)
		{
			$next = strpos($code, "\n", $offset);

			if($next === false)
			{
				return strlen($code);
			}

			$offset = $next + 1;
		}

		return $offset;
	}

	private static function lineStartOffsetForOffset(string $code, int $offset): int
	{
		$start = strrpos(substr($code, 0, $offset), "\n");

		return $start === false ? 0 : $start + 1;
	}

	private static function lineText(string $code, int $line): string
	{
		$start = self::lineStartOffset($code, $line);
		$end = strpos($code, "\n", $start);

		if($end === false)
		{
			$end = strlen($code);
		}

		return substr($code, $start, $end - $start);
	}

	private static function listItems(array $tokens, array $span): array
	{
		$segments = [];
		$depth = 0;
		$start = $span['open'] + 1;

		for($index = $span['open'] + 1; $index < $span['close']; $index += 1)
		{
			$token = $tokens[$index];

			if(isset(self::OPENING_DELIMITERS[$token->text]))
			{
				$depth += 1;
				continue;
			}

			if(isset(self::CLOSING_DELIMITERS[$token->text]))
			{
				$depth -= 1;
				continue;
			}

			if($token->text === ',' && $depth === 0)
			{
				$segments[] = ['start' => $start, 'end' => $index - 1, 'comma' => $index];
				$start = $index + 1;
			}
		}

		$segments[] = ['start' => $start, 'end' => $span['close'] - 1, 'comma' => null];
		$items = [];
		$previousComma = null;
		$finalComma = null;

		foreach($segments as $segment)
		{
			$first = self::firstSignificantInRange($tokens, $segment['start'], $segment['end']);
			$last = self::lastSignificantInRange($tokens, $segment['start'], $segment['end']);

			if($first === null || $last === null)
			{
				if($previousComma !== null)
				{
					$finalComma = $previousComma;
				}

				continue;
			}

			$items[] = [
				'start' => $first
				, 'end' => $last
				, 'commaBefore' => $previousComma
				, 'commaAfter' => $segment['comma']
			];

			$previousComma = $segment['comma'];
			$finalComma = $segment['comma'];
		}

		return [
			'items' => $items
			, 'finalComma' => $finalComma !== null && ($segments[count($segments) - 1][
				'comma'
			] === null && self::firstSignificantInRange(
				$tokens
				, $segments[count($segments) - 1]['start']
				, $segments[count($segments) - 1]['end']
			) === null
			)
				? $finalComma
				: null
		];
	}

	private static function listSpans(array $tokens): array
	{
		$interpolated = self::interpolationTokenMap($tokens);
		$stack = [];
		$spans = [];
		$pendingLongList = false;

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index]))
			{
				continue;
			}

			if($token->id === T_ARRAY || $token->id === T_LIST)
			{
				$pendingLongList = true;
				continue;
			}

			if(self::isTrivia($token))
			{
				continue;
			}

			if($token->text === '(')
			{
				$stack[] = [
					'open' => $index
					, 'text' => '('
					, 'list' => $pendingLongList || self::isVerticalCallParen($tokens, $index)
				];

				$pendingLongList = false;
				continue;
			}

			$pendingLongList = false;

			if($token->text === '[')
			{
				$stack[] = [
					'open' => $index
					, 'text' => '['
					, 'list' => self::isArrayBracket($tokens, $index)
				];

				continue;
			}

			if($token->text === '{')
			{
				$stack[] = [
					'open' => $index
					, 'text' => '{'
					, 'list' => false
				];

				continue;
			}

			if(!isset(self::CLOSING_DELIMITERS[$token->text]))
			{
				continue;
			}

			for($stackIndex = count($stack) - 1; $stackIndex >= 0; $stackIndex -= 1)
			{
				$opening = $stack[$stackIndex];

				if($opening['text'] !== self::CLOSING_DELIMITERS[$token->text])
				{
					continue;
				}

				array_splice($stack, $stackIndex, 1);

				if($opening['list'])
				{
					$spans[] = [
						'open' => $opening['open']
						, 'close' => $index
					];
				}

				break;
			}
		}

		return $spans;
	}

	private static function firstSignificantInRange(array $tokens, int $start, int $end): ?int
	{
		for($index = max(0, $start); $index <= $end; $index += 1)
		{
			if(isset($tokens[$index]) && !self::isTrivia($tokens[$index]))
			{
				return $index;
			}
		}

		return null;
	}

	private static function lastSignificantInRange(array $tokens, int $start, int $end): ?int
	{
		for($index = $end; $index >= $start; $index -= 1)
		{
			if(isset($tokens[$index]) && !self::isTrivia($tokens[$index]))
			{
				return $index;
			}
		}

		return null;
	}

	private static function nextSignificant(array $tokens, int $index): ?int
	{
		for($cursor = $index + 1; $cursor < count($tokens); $cursor += 1)
		{
			if(!self::isTrivia($tokens[$cursor]))
			{
				return $cursor;
			}
		}

		return null;
	}

	private static function previousSignificant(array $tokens, int $index): ?int
	{
		for($cursor = $index - 1; $cursor >= 0; $cursor -= 1)
		{
			if(!self::isTrivia($tokens[$cursor]))
			{
				return $cursor;
			}
		}

		return null;
	}

	private static function rangeInsideAny(int $start, int $end, array $ranges): bool
	{
		foreach($ranges as [$rangeStart, $rangeEnd])
		{
			if($start >= $rangeStart && $end <= $rangeEnd)
			{
				return true;
			}
		}

		return false;
	}

	private static function rangeOverlapsAny(int $start, int $end, array $ranges): bool
	{
		foreach($ranges as [$rangeStart, $rangeEnd])
		{
			if(($start < $rangeEnd && $end > $rangeStart)
				|| ($start === $end && $start > $rangeStart && $start < $rangeEnd)
			){
				return true;
			}
		}

		return false;
	}

	private static function tokenEnd(\PhpToken $token): int
	{
		return $token->pos + strlen($token->text);
	}

	private static function tokenEndLine(\PhpToken $token): int
	{
		return $token->line + substr_count($token->text, "\n");
	}

	private static function tokenLine(\PhpToken $token): int
	{
		return $token->line;
	}

	private static function topLevelDoubleArrowInItem(array $tokens, array $item): ?int
	{
		$depth = 0;

		for($index = $item['start']; $index <= $item['end']; $index += 1)
		{
			if($tokens[$index]->id === T_DOUBLE_ARROW && $depth === 0)
			{
				return $index;
			}

			if(isset(self::OPENING_DELIMITERS[$tokens[$index]->text]))
			{
				$depth += 1;
				continue;
			}

			if(isset(self::CLOSING_DELIMITERS[$tokens[$index]->text]))
			{
				$depth -= 1;
			}
		}

		return null;
	}

	private static function tokens(string $code): array
	{
		return \PhpToken::tokenize($code, TOKEN_PARSE);
	}

	private static function withAllmanBraces(string $code, int $maxLineLength): string
	{
		$tokens = self::tokens($code);
		$interpolated = self::interpolationTokenMap($tokens);
		$dynamicMemberBraces = self::dynamicMemberBraceMap($tokens);
		$closingForOpening = array_flip(self::closingRailPairs($tokens));
		$replacements = [];
		$compact = self::compactBraces($code, $tokens, $maxLineLength);
		$typedHeads = [];
		$positions = [];

		foreach($tokens as $index => $token) $positions[$token->pos] = $index;
		$pairs = self::closingRailPairs($tokens);

		ReadableLayout::walk(
			(new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? []
			, static function(
				\PhpParser\Node $node
			) use ($tokens, $positions, $pairs
				, &$typedHeads
			): void {
				if(!$node instanceof \PhpParser\Node\FunctionLike || !$node->getReturnType())
				return;

				$close = $positions[$node->getEndFilePos()] ?? null;
				$open = $pairs[$close] ?? null;
				$start = $positions[$node->getStartFilePos()] ?? null;

				if($open === null || $tokens[$open]->text !== '{' || $start === null) return;

				while($start < $open && $tokens[$start]->id !== T_FUNCTION) $start++;
				$previous = self::previousSignificant($tokens, $open);

				if($previous !== null && $tokens[$previous]->line > $tokens[$start]->line)
				$typedHeads[$open] = true;
			}
		);

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index]) || isset($dynamicMemberBraces[$index]))
			{
				continue;
			}

			if($token->text !== '{')
			{
				continue;
			}

			$previous = self::previousSignificant($tokens, $index);
			$next = self::nextSignificant($tokens, $index);

			if($previous === null)
			{
				continue;
			}

			if(self::hasCommentBetween($tokens, $previous, $index))
			{
				continue;
			}

			$between = substr(
				$code
				, self::tokenEnd($tokens[$previous])
				, $token->pos - self::tokenEnd($tokens[$previous])
			);

			$matchingClose = $closingForOpening[$index] ?? null;

			if(isset($compact[$index]))
			{
				continue;
			}

			$inline = self::isInlineBrace($tokens, $index);
			$multilineHead = $tokens[$previous]->text === ')'
				&& self::tokenLine($tokens[$previous]) > self::headStartLine($tokens, $previous);

			if($inline || $multilineHead || isset($typedHeads[$index]))
			{
				$replacement = $inline || isset($typedHeads[$index]) ? ' ' : '';

				if($between !== $replacement)
				{
					$replacements[] = [
						self::tokenEnd($tokens[$previous]), $token->pos
						, $replacement
					];
				}

				continue;
			}

			$expected = "\n" . self::lineIndent($code, self::tokenLine($tokens[$previous]));

			if($between !== $expected)
			{
				$replacements[] = [self::tokenEnd($tokens[$previous]), $token->pos, $expected];
			}
		}

		return self::applyReplacements($code, $replacements);
	}

	private static function withClassPropertyAlignment(string $code): string
	{
		$tokens = self::tokens($code);
		$replacements = [];

		foreach(self::classBodySpans($tokens) as $span)
		{
			$entries = self::classPropertyInitializerEntries($code, $tokens, $span);

			if($entries === [])
			{
				continue;
			}

			$replacements = array_merge(
				$replacements
				, self::classPropertyInitializerReplacements($code, $tokens, $entries)
			);
		}

		return self::applyReplacements($code, $replacements);
	}

	private static function headStartLine(array $tokens, int $closingParenIndex): int
	{
		$depth = 0;

		for($index = $closingParenIndex; $index >= 0; $index -= 1)
		{
			$token = $tokens[$index];

			if($token->text === ')')
			{
				$depth += 1;
				continue;
			}

			if($token->text === '(')
			{
				$depth -= 1;

				if($depth === 0)
				{
					$keyword = self::previousSignificant($tokens, $index);

					return $keyword === null
						? self::tokenLine($token)
						: self::tokenLine($tokens[$keyword]);
				}
			}
		}

		return self::tokenLine($tokens[$closingParenIndex]);
	}

	private static function withClosingRails(string $code): string
	{
		$tokens = self::tokens($code);
		$pairs = self::closingRailPairs($tokens);
		$replacements = [];

		foreach($pairs as $close => $open)
		{
			$closing = $tokens[$close];
			$opening = $tokens[$open];

			if($opening->text === '{' || $opening->line === $closing->line) continue;

			$previous = $close - 1;

			while($previous >= 0 && $tokens[$previous]->id === T_WHITESPACE) $previous--;

			if($previous < 0) continue;

			$indent = self::lineIndent($code, $opening->line);
			$start = self::tokenEnd($tokens[$previous]);
			$gap = substr($code, $start, $closing->pos - $start);

			$prefix = substr(
				$code
				, self::lineStartOffsetForOffset($code, $closing->pos)
				, $closing->pos - self::lineStartOffsetForOffset($code, $closing->pos)
			);

			$sameRail = isset($pairs[$previous])
				&& self::lineIndent($code, $tokens[$pairs[$previous]]->line) === $indent;

			if($sameRail && !str_contains($gap, "\n")) continue;

			if(trim($prefix) === '' && $prefix === $indent) continue;

			$replacements[] = [$start, $closing->pos, "\n" . $indent];
		}

		return self::applyReplacements($code, $replacements);
	}

	private static function withDynamicMemberBraceSpacing(string $code): string
	{
		$tokens = self::tokens($code);
		$interpolated = self::interpolationTokenMap($tokens);
		$replacements = [];

		foreach($tokens as $index => $token)
		{
			if(isset($interpolated[$index]) || $token->text !== '{')
			{
				continue;
			}

			$previous = self::previousSignificant($tokens, $index);

			if($previous === null || !self::isMemberOperator($tokens[$previous]))
			{
				continue;
			}

			if(self::hasCommentBetween($tokens, $previous, $index))
			{
				continue;
			}

			if(self::tokenEnd($tokens[$previous]) === $token->pos)
			{
				continue;
			}

			$replacements[] = [self::tokenEnd($tokens[$previous]), $token->pos, ''];
		}

		return self::applyReplacements($code, $replacements);
	}

	private static function withTabIndentation(string $code): string
	{
		$protectedRanges = [];

		foreach(self::tokens($code) as $token)
		{
			if(isset(self::STRING_TOKEN_IDS[$token->id]))
			{
				$protectedRanges[] = [$token->pos, self::tokenEnd($token)];
			}
		}

		preg_match_all('/^[ \t]+/m', $code, $matches, PREG_OFFSET_CAPTURE);
		$replacements = [];

		foreach($matches[0] as [$indent, $start])
		{
			$end = $start + strlen($indent);

			if(self::rangeInsideAny($start, $end, $protectedRanges))
			{
				continue;
			}

			$columns = 0;

			for($index = 0; $index < strlen($indent); $index += 1)
			{
				$columns += $indent[$index] === "\t" ? 4 : 1;
			}

			$replacement = str_repeat("\t", intdiv($columns, 4)) . str_repeat(' ', $columns % 4);

			if($replacement !== $indent)
			{
				$replacements[] = [$start, $end, $replacement];
			}
		}

		return self::applyReplacements($code, $replacements);
	}
}
