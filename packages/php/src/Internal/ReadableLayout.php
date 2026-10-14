<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Internal;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use SeanMorris\NoSaccadeStyle\Diagnostics;

final class ReadableLayout
{

	public static function walk(array $nodes, callable $visit): void
	{
		foreach($nodes as $node)
		{
			if(!$node instanceof Node || $node instanceof Node\Scalar) continue;

			$visit($node);

			foreach($node->getSubNodeNames() as $name)
			{
				$value = $node->$name;
				self::walk(is_array($value) ? $value : [$value], $visit);
			}
		}
	}

	private static function isCall(Node $node): bool
	{
		return $node instanceof Expr\CallLike || $node instanceof Expr\New_;
	}

	public static function indent(string $code, int $position): string
	{
		$start = strrpos(substr($code, 0, $position), "\n");
		preg_match('/^[\t ]*/', substr($code, $start === false ? 0 : $start + 1), $match);

		return $match[0];
	}

	public static function apply(string $code, array $replacements): string
	{
		krsort($replacements);

		foreach($replacements as $start => [$end, $text])
		{
			$code = substr_replace($code, $text, $start, $end - $start);
		}

		return $code;
	}

	/** Flatten whitespace in an argument without touching comments, blocks, or literal contents. */
	public static function inline(array $tokens, int $start, int $end): ?string
	{
		$text = '';

		for($index = $start; $index < $end; $index++)
		{
			$token = $tokens[$index];

			if($token->id === ord('['))
			{
				$depth = 1;

				for($close = $index + 1; $close < $end; $close++)
				{
					if($tokens[$close]->id === ord('[')) $depth++;

					if($tokens[$close]->id === ord(']') && --$depth === 0) break;
				}

				if(isset($tokens[$close]) && $tokens[$close]->line !== $token->line) return null;
			}

			if($token->id === T_COMMENT || $token->id === T_DOC_COMMENT
				|| $token->id === T_START_HEREDOC || in_array($token->text, ['{', '}'], true)
			) return null;

			if($token->id !== T_WHITESPACE)
			{
				if(str_contains($token->text, "\n")) return null;

				$text .= $token->text;
				continue;
			}

			if(!str_contains($token->text, "\n"))
			{
				$text .= $token->text;
				continue;
			}

			$before = $tokens[$index - 1]->text;
			$after = $tokens[$index + 1]->text;
			$tight = in_array($before, ['(', '[', '->', '?->', '::'], true)
				|| in_array($after, [')', ']', ',', '->', '?->', '::'], true);

			$text .= $tight ? '' : ' ';
		}

		return trim($text);
	}

	public static function wrapCalls(string $code, array $tokens, array $pairs, int $max): string
	{
		$ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];
		$positions = [];

		foreach($tokens as $index => $token) $positions[$token->pos] = $index;
		$opens = array_flip($pairs);
		$calls = [];

		self::walk($ast, static function(Node $node) use (&$calls): void {
			if(self::isCall($node)) $calls[] = $node;
		});

		usort(
			$calls
			, static fn(Node $left, Node $right): int => $left->getStartFilePos() <=> $right
				->getStartFilePos() ?: $right->getEndFilePos() <=> $left->getEndFilePos()
		);

		$replacements = [];
		$covered = -1;

		foreach($calls as $call)
		{
			$close = $positions[$call->getEndFilePos()] ?? null;
			$open = $pairs[$close] ?? null;

			if($open === null || $tokens[$open]->text !== '(' || $tokens[$open]->pos < $covered)
			continue;

			$first = $open + 1;

			while($tokens[$first]->id === T_WHITESPACE) $first++;

			if($first === $close) continue;

			$closingPrefix = substr(
				$code
				, strrpos(substr($code, 0, $tokens[$close]->pos), "\n") + 1
				, $tokens[$close]->pos - (strrpos(substr($code, 0, $tokens[$close]->pos), "\n") + 1)
			);

			$arguments = [];
			$commas = [];
			$start = $open + 1;

			for($index = $start; $index < $close; $index++)
			{
				if(isset($opens[$index]))
				{
					$index = $opens[$index];
					continue;
				}

				if($tokens[$index]->id !== ord(',')) continue;

				$commas[] = $index;
				$arguments[] = self::inline($tokens, $start, $index);
				$start = $index + 1;
			}

			$arguments[] = self::inline($tokens, $start, $close);

			if(in_array('', $arguments, true)) continue;

			$lineStart = strrpos(substr($code, 0, $tokens[$open]->pos), "\n");
			$lineStart = $lineStart === false ? 0 : $lineStart + 1;
			$head = substr($code, $lineStart, $tokens[$open]->pos + 1 - $lineStart);
			$lineEnd = strpos($code, "\n", $tokens[$close]->pos);

			$tail = substr(
				$code
				, $tokens[$close]->pos
				, ($lineEnd === false ? strlen($code) : $lineEnd) - $tokens[$close]->pos
			);

			$indent = self::indent($code, $tokens[$open]->pos);
			$compact = !in_array(null, $arguments, true);

			if($compact && Diagnostics::columns($head . implode(', ', $arguments) . $tail) <= $max)
			{
				$body = implode(', ', $arguments);

				if(substr(
					$code
					, $tokens[$open]->pos + 1
					, $tokens[$close]->pos - $tokens[$open]->pos - 1
				) === $body
				)
				continue;

				$replacements[$tokens[$open]->pos + 1] = [$tokens[$close]->pos, $body];
				$covered = $tokens[$close]->pos;
				continue;
			}

			if($compact)
			{
				if($tokens[$first]->line > $tokens[$open]->line && trim($closingPrefix) === '')
				{
					ExpressionLayout::listGaps(
						$code
						, $tokens
						, $open
						, $close
						, $commas
						, $indent
						, $replacements
					);

					continue;
				}

				$body = "\n{$indent}\t" . implode("\n{$indent}\t, ", $arguments) . "\n{$indent}";

				if(substr(
					$code
					, $tokens[$open]->pos + 1
					, $tokens[$close]->pos - $tokens[$open]->pos - 1
				) === $body
				)
				continue;

				$replacements[$tokens[$open]->pos + 1] = [$tokens[$close]->pos, $body];
				$covered = $tokens[$close]->pos;
				continue;
			}

			// Preserve callback bodies and comments; only move argument boundaries.
			$expanded = $tokens[$first]->line > $tokens[$open]->line;

			foreach($commas as $comma)
			{
				$before = $comma - 1;
				$after = $comma + 1;

				while($tokens[$before]->id === T_WHITESPACE) $before--;

				while($tokens[$after]->id === T_WHITESPACE) $after++;
				$expanded = $expanded || $tokens[$before]->line !== $tokens[$comma]->line
					|| $tokens[$after]->line !== $tokens[$comma]->line;
			}

			$firstLine = strtok(substr($code, $lineStart), "\n");

			if(!$expanded && Diagnostics::columns($firstLine) <= $max) continue;

			ExpressionLayout::listGaps(
				$code
				, $tokens
				, $open
				, $close
				, $commas
				, $indent
				, $replacements
			);
		}

		return self::apply($code, $replacements);
	}

	/**
	 * Recognize a final block through expression delimiters without mistaking data braces for code.
	 *
	 * @param Node $node Complete statement or class member whose final token is being inspected.
	 * @param list<\PhpToken> $tokens Lexed source including whitespace and comments.
	 * @param array<int,int> $positions Token indexes keyed by starting and ending byte offsets.
	 * @param array<int,true> $blockEnds Byte offsets of closing braces belonging to code blocks.
	 * @return bool Whether the statement ends with a block or a braced do/while construct.
	 */
	private static function endsWithBlock(
		Node $node
		, array $tokens
		, array $positions
		, array $blockEnds
	): bool {
		$trivia = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

		if($node instanceof Stmt\Do_)
		{
			$index = $positions[$node->getStartFilePos()] + 1;

			while(in_array($tokens[$index]->id, $trivia, true)) $index++;

			return $tokens[$index]->text === '{';
		}

		$index = $positions[$node->getEndFilePos()] ?? -1;

		while($index >= 0 && $tokens[$index]->pos >= $node->getStartFilePos())
		{
			$token = $tokens[$index--];

			if(in_array($token->id, $trivia, true)
				|| in_array($token->text, [';', ')', ']'], true)
			) continue;

			return isset($blockEnds[$token->pos]);
		}

		return false;
	}

	public static function separateStatements(string $code): string
	{
		$ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];
		$groups = [$ast];
		$blockEnds = $positions = [];
		$tokens = \PhpToken::tokenize($code, TOKEN_PARSE);

		foreach($tokens as $index => $token)
		{
			$positions[$token->pos] = $positions[$token->pos + strlen($token->text) - 1] = $index;
		}

		self::walk($ast, static function(Node $node) use (&$groups, &$blockEnds, $code): void {
			if(isset($node->stmts) && $node->getStartLine() !== $node->getEndLine())
			$groups[] = $node->stmts;

			if((isset($node->stmts) || $node instanceof Stmt\Switch_)
				&& $code[$node->getEndFilePos()] === '}'
			) $blockEnds[$node->getEndFilePos()] = true;
		});

		$replacements = [];

		foreach($groups as $statements)
		{
			for($index = 1; $index < count($statements); $index++)
			{
				$previous = $statements[$index - 1];
				$current = $statements[$index];

				if($current instanceof Stmt\InlineHTML) continue;

				$control = $current instanceof Stmt\If_ || $current instanceof Stmt\For_
					|| $current instanceof Stmt\Foreach_ || $current instanceof Stmt\While_
					|| $current instanceof Stmt\Do_ || $current instanceof Stmt\Switch_
					|| $current instanceof Stmt\TryCatch;

				$multilineCall = false;

				if($current instanceof Stmt\Expression || $current instanceof Stmt\Return_)
				{
					self::walk([$current], static function(Node $node) use (&$multilineCall): void {
						if(self::isCall($node) && $node->getStartLine() !== $node->getEndLine())
						$multilineCall = true;
					});
				}

				$completedExpression = $previous instanceof Stmt\Expression
					&& $previous->getStartLine() !== $previous->getEndLine();

				$return = $current instanceof Stmt\Return_ && $current->getStartLine() > $previous
					->getEndLine();

				if(!$control && !$multilineCall && !$completedExpression && !$return
					&& !$previous instanceof Stmt\If_
					&& !self::endsWithBlock($previous, $tokens, $positions, $blockEnds)
				) continue;

				$start = $current->getStartFilePos();

				foreach($current->getComments() as $comment)
				{
					if($comment->getStartLine() > $previous->getEndLine())
					$start = min($start, $comment->getStartFilePos());
				}

				$left = $previous->getEndFilePos() + 1;
				$before = ($positions[$start] ?? 0) - 1;

				while($before >= 0 && $tokens[$before]->id === T_WHITESPACE) $before--;

				if($before >= 0 && $tokens[$before]->pos >= $left)
				$left = $tokens[$before]->pos + strlen($tokens[$before]->text);

				$gap = substr($code, $left, $start - $left);

				if(preg_match('/\n[\t ]*\n/', $gap)) continue;

				$line = strrpos(substr($code, 0, $start), "\n");

				$prefix = substr(
					$code
					, $line === false ? 0 : $line + 1
					, $start - ($line === false ? 0 : $line + 1)
				);

				$indent = self::indent(
					$code
					, trim($prefix) === '' ? $start : $previous->getStartFilePos()
				);

				$replacements[$left] = [$start, "\n\n" . $indent];
			}
		}

		return self::apply($code, $replacements);
	}
}
