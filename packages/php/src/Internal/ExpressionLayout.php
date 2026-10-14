<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Internal;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use SeanMorris\NoSaccadeStyle\Diagnostics;

/** Structural expression layout shared by the PHP formatting passes. */
final class ExpressionLayout
{

	private static function visit(string $code, callable $visit): void
	{
		ReadableLayout::walk(
			(new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? []
			, $visit
		);
	}

	private static function gap(
		string $code
		, int $start
		, int $end
		, string $text
		, array &$edits
	): void {
		$actual = substr($code, $start, $end - $start);

		if($actual !== $text && trim($actual) === '') $edits[$start] = [$end, $text];
	}

	public static function listGaps(
		string $code
		, array $tokens
		, int $open
		, int $close
		, array $commas
		, string $indent
		, array &$edits
	): void {
		$first = $open + 1;

		while($tokens[$first]->id === T_WHITESPACE) $first++;
		self::gap($code, $tokens[$open]->pos + 1, $tokens[$first]->pos, "\n{$indent}\t", $edits);

		foreach($commas as $comma)
		{
			$before = $comma - 1;
			$after = $comma + 1;

			while($tokens[$before]->id === T_WHITESPACE) $before--;

			while($tokens[$after]->id === T_WHITESPACE) $after++;

			self::gap(
				$code
				, $tokens[$before]->pos + strlen($tokens[$before]->text)
				, $tokens[$comma]->pos
				, "\n{$indent}\t"
				, $edits
			);

			self::gap($code, $tokens[$comma]->pos + 1, $tokens[$after]->pos, ' ', $edits);
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
	}

	/** Normalize a partially expanded function parameter list without moving its contents. */
	public static function parameters(string $code, array $tokens, array $pairs, int $max): string
	{
		$positions = [];

		foreach($tokens as $index => $token) $positions[$token->pos] = $index;
		$opens = array_flip($pairs);
		$edits = [];

		self::visit(
			$code
			, static function(
				Node $node
			) use ($code, $tokens, $positions, $opens, $max, &$edits): void {
				if(!$node instanceof Node\FunctionLike || !$node->getParams()) return;

				$start = $positions[$node->getStartFilePos()] ?? null;

				if($start === null) return;

				while(isset($tokens[$start]) && !in_array(
					$tokens[$start]->id
					, [T_FUNCTION, T_FN]
					, true
				))
				$start++;

				while(isset($tokens[$start]) && $tokens[$start]->text !== '(') $start++;
				$close = $opens[$start] ?? null;

				if($close === null) return;

				$lineStart = strrpos(substr($code, 0, $tokens[$start]->pos), "\n");
				$lineStart = $lineStart === false ? 0 : $lineStart + 1;
				$head = substr($code, $lineStart, $tokens[$close]->pos + 1 - $lineStart);
				$returnType = $node->getReturnType();
				$end = $returnType ? $returnType->getEndFilePos() + 1 : $tokens[$close]->pos + 1;
				$signature = substr($code, $lineStart, $end - $lineStart);
				$signature = preg_replace('/\s*\n\s*/', ' ', $signature);

				if($returnType && isset($positions[$returnType->getStartFilePos()]))
				{
					$type = $positions[$returnType->getStartFilePos()];
					$colon = $type - 1;

					while($tokens[$colon]->id === T_WHITESPACE) $colon--;

					if($tokens[$colon]->text === ':')
					{
						self::gap(
							$code
							, $tokens[$colon]->pos + 1
							, $tokens[$type]->pos
							, ' '
							, $edits
						);
					}
				}

				if(!str_contains($head, "\n") && Diagnostics::columns($signature) <= $max) return;

				$commas = [];

				for($index = $start + 1; $index < $close; $index++)
				{
					if(isset($opens[$index]))
					{
						$index = $opens[$index];
						continue;
					}

					if($tokens[$index]->id === ord(',')) $commas[] = $index;
				}

				self::listGaps(
					$code
					, $tokens
					, $start
					, $close
					, $commas
					, ReadableLayout::indent($code, $tokens[$start]->pos)
					, $edits
				);
			}
		);

		return ReadableLayout::apply($code, $edits);
	}

	/** Attach a closure's documentation to its complete standalone assignment. */
	public static function assignmentDocs(string $code): string
	{
		$edits = [];

		self::visit($code, static function(Node $node) use ($code, &$edits): void {
			if(!$node instanceof Stmt\Expression || !$node->expr instanceof Expr\Assign) return;

			$assign = $node->expr;
			$value = $assign->expr;

			if(!$value instanceof Expr\Closure && !$value instanceof Expr\ArrowFunction) return;

			$doc = $value->getDocComment();

			if(!$doc || $doc->getStartFilePos() < $assign->getStartFilePos()) return;

			$before = substr(
				$code
				, $assign->var->getEndFilePos() + 1
				, $doc->getStartFilePos() - $assign->var->getEndFilePos() - 1
			);

			$after = substr(
				$code
				, $doc->getEndFilePos() + 1
				, $value->getStartFilePos() - $doc->getEndFilePos() - 1
			);

			if(trim($before) !== '=' || trim($after) !== '') return;

			$indent = ReadableLayout::indent($code, $assign->getStartFilePos());
			$oldIndent = ReadableLayout::indent($code, $doc->getStartFilePos());
			$text = str_replace("\n" . $oldIndent, "\n" . $indent, $doc->getText());

			$left = substr(
				$code
				, $assign->getStartFilePos()
				, $assign->var->getEndFilePos() + 1 - $assign->getStartFilePos()
			);

			$edits[$assign->getStartFilePos()] = [$value->getStartFilePos()
			, $text . "\n" . $indent
				. $left . ' = '
			];
		});

		return ReadableLayout::apply($code, $edits);
	}

	/** Find unbraced control bodies without changing braces or dangling-else ownership. */
	private static function controls(string $code, callable $visit): void
	{
		$tokens = \PhpToken::tokenize($code, TOKEN_PARSE);
		$positions = [];

		foreach($tokens as $index => $token) $positions[$token->pos] = $index;

		self::visit($code, static function(Node $node) use ($tokens, $positions, $visit): void {
			if(!$node instanceof Stmt\If_ && !$node instanceof Stmt\ElseIf_
				&& !$node instanceof Stmt\Else_
				&& !$node instanceof Stmt\For_ && !$node instanceof Stmt\Foreach_
					&& !$node instanceof Stmt\While_
				&& !$node instanceof Stmt\Do_
			) return;

			if(count($node->stmts) !== 1) return;

			$body = $node->stmts[0];

			if($node instanceof Stmt\Else_ && $body instanceof Stmt\If_) return;

			$index = ($positions[$body->getStartFilePos()] ?? 0) - 1;

			while($index >= 0 && in_array(
				$tokens[$index]->id
				, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]
				, true
			))
			$index--;

			if($index < 0 || ($tokens[$index]->text !== ')' && !in_array(
				$tokens[$index]->id
				, [T_ELSE, T_DO]
				, true
			)))
			return;

			$visit($node, $body, $tokens[$index]);
		});
	}

	public static function splitControls(string $code, int $max): string
	{
		$lines = explode("\n", $code);
		$edits = [];

		self::controls(
			$code
			, static function(
				Node $node
				, Node $body
				, \PhpToken $head
			) use ($code, $lines, $max
				, &$edits
			): void {
				if($head->line !== $body->getStartLine() || ($body->getStartLine() === $body
					->getEndLine()
					&& Diagnostics::columns($lines[$head->line - 1]) <= $max
				)
				)
				return;

				self::gap(
					$code
					, $head->pos + strlen($head->text)
					, $body->getStartFilePos()
					, "\n" . ReadableLayout::indent($code, $node->getStartFilePos())
					, $edits
				);
			}
		);

		return ReadableLayout::apply($code, $edits);
	}

	/** Restore stacked indentation after PHP-CS-Fixer indents every unbraced body. */
	public static function stackedIndent(string $code): string
	{
		$dedent = [];

		self::controls(
			$code
			, static function(Node $node, Node $body, \PhpToken $head) use ($code, &$dedent): void {
				if($head->line === $body->getStartLine()) return;

				$header = ReadableLayout::indent($code, $node->getStartFilePos());
				$indent = ReadableLayout::indent($code, $body->getStartFilePos());
				$difference = strlen($indent) - strlen($header);

				if($difference <= 0) return;

				for($line = $body->getStartLine(); $line <= $body->getEndLine(); $line++)
				$dedent[$line] = ($dedent[$line] ?? 0) + $difference;
			}
		);

		$edits = [];

		// Only whitespace tokens may change: heredocs, strings and comment payloads are untouched.
		foreach(\PhpToken::tokenize($code, TOKEN_PARSE) as $token)
		{
			if($token->id !== T_WHITESPACE) continue;

			preg_match_all('/\n([\t ]*)/', $token->text, $matches, PREG_OFFSET_CAPTURE);

			foreach($matches[1] as $index => [$indent, $offset])
			{
				$line = $token->line + $index + 1;
				$count = min(strlen($indent), $dedent[$line] ?? 0);

				if($count) $edits[$token->pos + $offset] = [$token->pos + $offset + $count, ''];
			}
		}

		return ReadableLayout::apply($code, $edits);
	}

	/** Keep both ternary branches inline or place both markers on matching continuation lines. */
	public static function ternaries(string $code, int $max): string
	{
		$tokens = \PhpToken::tokenize($code, TOKEN_PARSE);
		$positions = [];

		foreach($tokens as $index => $token) $positions[$token->pos] = $index;
		$direct = [];

		self::visit($code, static function(Node $node) use (&$direct): void {
			if(($node instanceof Expr\Assign || $node instanceof Stmt\Return_) && $node
				->expr instanceof Expr\Ternary
			)
			$direct[$node->expr->getStartFilePos()] = true;
		});

		$edits = [];
		$covered = -1;

		self::visit(
			$code
			, static function(
				Node $node
			) use ($code, $tokens, $positions, $direct, $max, &$edits
				, &$covered
			): void {
				if(!$node instanceof Expr\Ternary || !$node->if || $node
					->getStartFilePos() < $covered
				)
				return;

				$start = $positions[$node->getStartFilePos()] ?? null;

				if($start === null) return;

				$end = $start;

				while(isset($tokens[$end]) && $tokens[$end]->pos <= $node->getEndFilePos()) $end++;
				$question = $colon = null;

				for($index = $start; $index < $end; $index++)
				{
					if($tokens[$index]->pos > $node->cond->getEndFilePos() && $tokens[$index]
						->pos < $node->if->getStartFilePos() && $tokens[$index]->text === '?'
					)
					$question = $index;

					if($tokens[$index]->pos > $node->if->getEndFilePos() && $tokens[$index]
						->pos < $node->else->getStartFilePos() && $tokens[$index]->text === ':'
					)
					$colon = $index;
				}

				if($question === null || $colon === null) return;

				$indent = ReadableLayout::indent($code, $node->getStartFilePos());
				$flat = ReadableLayout::inline($tokens, $start, $end);
				$lineStart = strrpos(substr($code, 0, $node->getStartFilePos()), "\n");
				$lineStart = $lineStart === false ? 0 : $lineStart + 1;
				$prefix = substr($code, $lineStart, $node->getStartFilePos() - $lineStart);
				$lineEnd = strpos($code, "\n", $node->getEndFilePos() + 1);

				$suffix = substr(
					$code
					, $node->getEndFilePos() + 1
					, ($lineEnd === false ? strlen($code) : $lineEnd) - $node->getEndFilePos() - 1
				);

				$both = $tokens[$question]->line > $node->cond->getEndLine() && $tokens[$colon]
					->line > $node->if->getEndLine();

				$multiline = $node->getStartLine() !== $node->getEndLine();

				if($multiline && !$both && $flat !== null && Diagnostics::columns(
					$prefix . $flat . $suffix
				) <= $max
				){
					$edits[$node->getStartFilePos()] = [$node->getEndFilePos() + 1, $flat];
					$covered = $node->getEndFilePos() + 1;

					return;
				}

				$width = Diagnostics::columns(
					(isset($direct[$node->getStartFilePos()])
						? $prefix
						: $indent
					) . ($flat ?? '') . $suffix
				);

				if(!$multiline && $width <= $max) return;

				foreach([$question, $colon] as $operator)
				{
					$before = $operator - 1;
					$after = $operator + 1;

					while($tokens[$before]->id === T_WHITESPACE) $before--;

					while($tokens[$after]->id === T_WHITESPACE) $after++;

					self::gap(
						$code
						, $tokens[$before]->pos + strlen($tokens[$before]->text)
						, $tokens[$operator]->pos
						, "\n{$indent}\t"
						, $edits
					);

					self::gap(
						$code
						, $tokens[$operator]->pos + 1
						, $tokens[$after]->pos
						, ' '
						, $edits
					);
				}
			}
		);

		return ReadableLayout::apply($code, $edits);
	}

	/** Rejoin a short member access without flattening its surrounding expression. */
	public static function shortMembers(string $code, int $max): string
	{
		$tokens = \PhpToken::tokenize($code, TOKEN_PARSE);
		$positions = [];

		foreach($tokens as $index => $token) $positions[$token->pos] = $index;
		$edits = [];
		$covered = -1;

		self::visit(
			$code
			, static function(
				Node $node
			) use ($code, $tokens, $positions, $max, &$edits, &$covered): void {
				if(!$node instanceof Expr\PropertyFetch
					&& !$node instanceof Expr\NullsafePropertyFetch
					&& !$node instanceof Expr\StaticPropertyFetch
						&& !$node instanceof Expr\ClassConstFetch
				)
				return;

				if($node->getStartLine() === $node->getEndLine() || $node
					->getStartFilePos() < $covered
				)
				return;

				$start = $positions[$node->getStartFilePos()];
				$end = $start;

				while(isset($tokens[$end]) && $tokens[$end]->pos <= $node->getEndFilePos()) $end++;
				$flat = ReadableLayout::inline($tokens, $start, $end);

				if($flat === null) return;

				$lineStart = strrpos(substr($code, 0, $node->getStartFilePos()), "\n");
				$lineStart = $lineStart === false ? 0 : $lineStart + 1;
				$lineEnd = strpos($code, "\n", $node->getEndFilePos() + 1);
				$prefix = substr($code, $lineStart, $node->getStartFilePos() - $lineStart);

				$suffix = substr(
					$code
					, $node->getEndFilePos() + 1
					, ($lineEnd === false ? strlen($code) : $lineEnd) - $node->getEndFilePos() - 1
				);

				if(Diagnostics::columns($prefix . $flat . $suffix) > $max) return;

				$edits[$node->getStartFilePos()] = [$node->getEndFilePos() + 1, $flat];
				$covered = $node->getEndFilePos() + 1;
			}
		);

		return ReadableLayout::apply($code, $edits);
	}

	/** Suggest named local callbacks without performing a source refactoring. */
	public static function callbackDiagnostics(string $code): array
	{
		$messages = [];

		self::visit($code, static function(Node $node) use (&$messages): void {
			if(!$node instanceof Node\Arg || !$node->value instanceof Expr\Closure) return;

			$value = $node->value;

			if($value->getStartLine() === $value->getEndLine() || (!$value->getDocComment()
				&& !$node->getDocComment()
			))
			return;

			$messages[] = [
				'line' => $value->getStartLine()
				, 'severity' => 'warning'
				, 'rule' => 'documented-callback'
				, 'message' =>
				'Consider assigning this documented multiline callback to a named local before passing it.'
			];
		});

		return $messages;
	}
}
