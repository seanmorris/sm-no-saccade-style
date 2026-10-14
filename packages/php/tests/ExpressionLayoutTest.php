<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Tests;

use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet\RuleSet;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use PHPUnit\Framework\TestCase;
use SeanMorris\NoSaccadeStyle\ConfigFactory;
use SeanMorris\NoSaccadeStyle\Diagnostics;
use SeanMorris\NoSaccadeStyle\Fixers;

final class ExpressionLayoutTest extends TestCase
{

	public function testConcatenationSpacingPreservesLiteralContents(): void
	{
		$output = $this->format("<?php\n\$label = 'a.b'.\$name.'.c';\n");
		self::assertStringContainsString("'a.b' . \$name . '.c'", $output);
	}

	public function testTernaryBranchesShareTheirContinuationRail(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$target = $fallback ? $document->querySelector('textarea[aria-label="SDL text input"]')
    : $document->querySelector('canvas')->editContext;
PHP
		);

		self::assertStringContainsString("\$fallback\n\t? \$document", $output);
		self::assertStringContainsString("\n\t: \$document", $output);
	}

	public function testShortHybridTernaryCompactsWithoutChangingGrouping(): void
	{
		$output = $this->format("<?php\n\$name = 'mode ' . (\$fallback ? 'text'\n: 'canvas');\n");
		self::assertStringContainsString("(\$fallback ? 'text' : 'canvas')", $output);
	}

	public function testOuterLoopSplitsBeforeCallbackParameters(): void
	{
		$output = $this->format(<<<'PHP'
<?php
foreach([false, true] as $fallback) test('fullscreen IME with ' . ($fallback ? 'textarea' : 'EditContext'), function($page) {
    start($page);
    finish($page);
});
PHP
		);

		self::assertStringContainsString("as \$fallback)\ntest(", $output);
		self::assertStringContainsString("function(\$page) {\n\tstart", $output);
	}

	public function testStackedControlsKeepTheirBodiesAndLiteralsIntact(): void
	{
		$output = $this->format(<<<'PHP'
<?php
foreach($items as $item)
if($item)
run(function() {
    $text = <<<'TEXT'
  Literal indentation.
TEXT;
    print($text);
});
else
skip();
PHP
		);

		self::assertStringContainsString("as \$item)\nif(\$item)\nrun(", $output);
		self::assertStringContainsString("\nelse\nskip();", $output);
	}

	public function testTypedMultilineFunctionHeadKeepsItsBrace(): void
	{
		$output = $this->format(<<<'PHP'
<?php
function configure(Engine $engine, Terrain $terrain,
    TileMap $map
): BreakableBlocks
{
    prepare();
    return makeBlocks();
}
PHP
		);

		self::assertStringContainsString(
			"function configure(\n\tEngine \$engine\n\t, Terrain \$terrain\n\t, TileMap \$map\n): BreakableBlocks {"
			, $output
		);

		self::assertStringContainsString("prepare();\n\n\treturn", $output);
	}

	public function testReturnTypeCountsTowardTheFunctionHeaderWidth(): void
	{
		$output = $this->format(<<<'PHP'
<?php
function configureEveryBreakableInTheCurrentWorld(Engine $engine, Terrain $terrain, TileMap $map): BreakableBlockCollection
{
    return makeBlocks();
}
PHP
		);

		self::assertStringContainsString("CurrentWorld(\n\tEngine \$engine", $output);
		self::assertStringContainsString("\n): BreakableBlockCollection {", $output);
	}

	public function testClosureDocumentationMovesBeforeTheAssignment(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$ids = [];
$reset =
    /**
     * Reset the blocks.
     * @return void Completes the reset.
     */
    static function() use (&$ids): void {
        $ids = [];
        finish();
    };
PHP
		);

		self::assertStringContainsString("\$ids = [];\n\n/**", $output);

		self::assertStringContainsString(
			"*/\n\$reset = static function() use (&\$ids): void {"
			, $output
		);

		self::assertStringContainsString("\n\tfinish();\n};", $output);
	}

	public function testCompletedMultilineCallsSeparateSubsequentWork(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$sprite = new Sprite(
    $gid->id
    , $object['x'] + $map->x + $layer->offsetX
    , $object['y'] + $map->y + $layer->offsetY
    , 0
    , 1
);
$rect = tile($sprite->gid);
PHP
		);

		self::assertStringContainsString(");\n\n\$rect", $output);
	}

	public function testShortCallsCompactButCommentedArgumentsStaySeparate(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$engine->systems->add(
    $prefix.'particles'
    , [new ParticleSystem, 'update']
    , after: [$prefix.'breakables']
);
run(
    $first // Preserve this argument's explanation.
    , $second
);
PHP
		);

		self::assertStringContainsString(
			"add(\$prefix . 'particles', [new ParticleSystem, 'update'], after: [\$prefix . 'breakables']);"
			, $output
		);

		self::assertStringContainsString(
			"\$first // Preserve this argument's explanation.\n\t, \$second"
			, $output
		);
	}

	public function testTernaryCommentsAndNullableTypesRemainDistinct(): void
	{
		$this->format(<<<'PHP'
<?php
function choose(?Example $value): ?Example
{
    $result = $value ? /* A branch comment. */ choose($value)
        : null;
    return $result;
}
$short = $value ?: fallback();
$nested = $a ? ($b ? one() : two()) : ($c ? three() : four());
$match = match($value) { 1 => 'a:b?', default => 'c' };
PHP
		);
	}

	public function testDocumentedCallbackExtractionIsOnlyASuggestion(): void
	{
		$code = <<<'PHP'
<?php
register(
    /** Process a step. @return void Completes the step. */
    function() {
        first();
        second();
    }
);
PHP;

		$warnings = array_values(
			array_filter(Diagnostics::analyze($code), static fn($row) => $row[
				'rule'
			] === 'documented-callback'
			)
		);

		self::assertCount(1, $warnings);
		self::assertSame('warning', $warnings[0]['severity']);
		$this->format($code);
	}

	private function format(string $input): string
	{
		$config = ConfigFactory::recommended([]);
		$rules = $config->getRules();
		$rules[Fixers::ALLMAN_TABS] = ['max_line_length' => 120];

		$factory = (new FixerFactory())->registerBuiltInFixers()
			->registerCustomFixers($config->getCustomFixers())
			->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\n"))
			->useRuleSet(new RuleSet($rules));

		$apply = static function(string $code) use ($factory): string {
			$tokens = Tokens::fromCode($code);

			foreach($factory->getFixers() as $fixer)
			$fixer->fix(new \SplFileInfo(__FILE__), $tokens);

			return $tokens->generateCode();
		};

		$output = $apply($input);
		self::assertSame($output, $apply($output), 'Formatting must converge.');

		$program = static function(string $code): array {
			$executable = $comments = [];

			foreach(\PhpToken::tokenize($code, TOKEN_PARSE) as $token)
			{
				if(in_array($token->id, [T_COMMENT, T_DOC_COMMENT], true))
				{
					$comments[] = preg_replace('/^\h+/m', '', $token->text);
				} elseif($token->id !== T_WHITESPACE)
				{
					$executable[] = [$token->id, $token->text];
				}
			}

			return [$executable, $comments];
		};

		self::assertSame(
			$program($input)
			, $program($output)
			, 'Preserve code, comments and literal contents.'
		);

		return $output;
	}
}
