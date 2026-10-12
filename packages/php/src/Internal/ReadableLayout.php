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

	private static function walk(array $nodes, callable $visit): void
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

	private static function indent(string $code, int $position): string
	{
		$start = strrpos(substr($code, 0, $position), "\n");
		preg_match('/^[\t ]*/', substr($code, $start === false ? 0 : $start + 1), $match);
		return $match[0];
	}

	private static function apply(string $code, array $replacements): string
	{
		krsort($replacements);

		foreach($replacements as $start => [$end, $text])
		{
			$code = substr_replace($code, $text, $start, $end - $start);
		}
		return $code;
	}

	/** Flatten whitespace in an argument without touching comments, blocks, or literal contents. */
	private static function inline(array $tokens, int $start, int $end): ?string
	{
		$text = '';

		for($index = $start; $index < $end; $index++)
		{
			$token = $tokens[$index];

			if($token->id === T_COMMENT || $token->id === T_DOC_COMMENT
				|| $token->id === T_START_HEREDOC || in_array(
					$token->text
					, ['{', '}']
					, true
				)
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

			if($tokens[$first]->line > $tokens[$open]->line && trim(
				$closingPrefix
			) === ''
			) continue;

			$arguments = [];
			$start = $open + 1;

			for($index = $start; $index < $close; $index++)
			{
				if(isset($opens[$index]))
				{
					$index = $opens[$index];
					continue;
				}

				if($tokens[$index]->id !== ord(',')) continue;

				$arguments[] = self::inline($tokens, $start, $index);
				$start = $index + 1;
			}
			$arguments[] = self::inline($tokens, $start, $close);

			if(in_array(null, $arguments, true) || in_array('', $arguments, true)) continue;

			$lineStart = strrpos(substr($code, 0, $tokens[$open]->pos), "\n");
			$lineStart = $lineStart === false ? 0 : $lineStart + 1;
			$head = substr($code, $lineStart, $tokens[$open]->pos + 1 - $lineStart);
			$lineEnd = strpos($code, "\n", $tokens[$close]->pos);

			$tail = substr(
				$code
				, $tokens[$close]->pos
				, ($lineEnd === false ? strlen($code) : $lineEnd) - $tokens[$close]->pos
			);

			if(Diagnostics::columns($head . implode(', ', $arguments) . $tail) <= $max) continue;

			$indent = self::indent($code, $tokens[$open]->pos);
			$body = "\n{$indent}\t" . implode("\n{$indent}\t, ", $arguments) . "\n{$indent}";
			$replacements[$tokens[$open]->pos + 1] = [$tokens[$close]->pos, $body];
			$covered = $tokens[$close]->pos;
		}
		return self::apply($code, $replacements);
	}

	public static function separateStatements(string $code): string
	{
		$ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];
		$groups = [$ast];

		self::walk($ast, static function(Node $node) use (&$groups): void {
			if(isset($node->stmts) && $node->getStartLine() !== $node->getEndLine())
				$groups[] = $node->stmts;
		});
		$replacements = [];

		foreach($groups as $statements)
		{
			for($index = 1; $index < count($statements); $index++)
			{
				$previous = $statements[$index - 1];
				$current = $statements[$index];
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

				if(!$control && !$multilineCall && !$previous instanceof Stmt\If_) continue;

				$start = $current->getStartFilePos();

				foreach($current->getComments() as $comment)
				{
					if($comment->getStartLine() > $previous->getEndLine())
						$start = min($start, $comment->getStartFilePos());
				}
				$left = $previous->getEndFilePos() + 1;
				$gap = substr($code, $left, $start - $left);

				if(preg_match('/\n[\t ]*\n/', $gap)) continue;

				if(trim($gap) !== '')
				{
					$left = strrpos(substr($code, 0, $start), "\n") + 1;

					if($left <= $previous->getEndFilePos()) continue;

					$replacements[$left] = [$left, "\n"];
					continue;
				}

				$replacements[$left] = [$start, "\n\n" . self::indent($code, $start)];
			}
		}
		return self::apply($code, $replacements);
	}
}
