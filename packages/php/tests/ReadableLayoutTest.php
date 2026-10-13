<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Tests;

use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet\RuleSet;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use SeanMorris\NoSaccadeStyle\ConfigFactory;
use SeanMorris\NoSaccadeStyle\Diagnostics;

final class ReadableLayoutTest extends TestCase
{

	public function testCompletedBlocksSeparateTheNextStatement(): void
	{
		foreach([
			"foreach(\$items as \$item)\n{\n\tstep(\$item);\n}"
			, "for(\$i = 0; \$i < 3; \$i++)\n{\n\tstep(\$i);\n}"
			, "while(\$ready)\n{\n\tstep();\n}"
			, "do\n{\n\tstep();\n}\nwhile(\$ready);"
			, "switch(\$value)\n{\n\tdefault:\n\t\tbreak;\n}"
			, "try\n{\n\tstep();\n}\ncatch(\\Throwable \$e)\n{\n\trecover();\n}"
			, "function work()\n{\n\tstep();\n}"
			, "class Worker\n{\n\tfunction work()\n\t{\n\t\tstep();\n\t}\n}"
			, "\$work = function() {\n\tstep();\n};"
			, "consume(function() {\n\tstep();\n});"
			, "declare(ticks=1)\n{\n\tstep();\n}"
			, 'function short(){ return 1; }'
		] as $block
		){
			$output = $this->format("<?php\n$block\nafter();\n");
			self::assertStringEndsWith("\n\nafter();\n", $output, $block);
		}
	}

	public function testNestedClosingRunsArePreservedAndSeparateTheNextStatement(): void
	{
		foreach(['}}', '} }', '}}}'] as $closes)
		{
			$extra = $closes === '}}}' ? "\t\twhile(\$ready)\n\t\t{\n" : '';
			$indent = $closes === '}}}' ? "\t\t" : "\t";
			$input = "<?php\nforeach(\$items as \$item)\n{\n\tif(\$item)\n\t{\n"
				. $extra . $indent . "\tstep(\$item);\n" . $indent . "$closes after();\n";
			$output = $this->format($input);
			self::assertStringContainsString($indent . "$closes\n\nafter();\n", $output);
		}

		$output = $this->format(<<<'PHP'
<?php
function work()
{
	while($ready)
	{
		if(check())
		{
			step();
		}}
}
after();

PHP
		);
		self::assertStringContainsString("\t\t}}\n}\n\nafter();", $output);
	}

	public function testClosingRunsKeepConnectedClausesAttached(): void
	{
		$output = $this->format(<<<'PHP'
<?php
if($ready)
{
	while($pending)
	{
		step();
	}} else
{
	recover();
}
try
{
	while($pending)
	{
		step();
	}}
catch(\Throwable $error)
{
	recover();
}
finally
{
	cleanup();
}
do
{
	while($pending)
	{
		step();
	}}
while($ready);
after();

PHP
		);
		self::assertStringContainsString("\t}} else", $output);
		self::assertStringContainsString("\t}}\ncatch", $output);
		self::assertStringContainsString("}\nfinally", $output);
		self::assertStringContainsString("\t}}\nwhile(\$ready);\n\nafter();", $output);
	}

	public function testInlineTrailingCommentsStillSeparateTheNextStatement(): void
	{
		foreach(['/* Done. */', '/* Done. */ /* Also done. */'
			, "/* Done.\n\nStill done. */"
		] as $comment
		){
			$input = "<?php\nfunction work()\n{\n\twhile(\$ready)\n\t{\n\t\tstep();\n\t} "
				. $comment . " after();\n}\n";
			$output = $this->format($input);
			self::assertStringContainsString("} $comment\n\n\tafter();", $output);
		}
	}

	public function testBlockSpacingKeepsCommentsAttachedAndSeparatesClassMembers(): void
	{
		$output = $this->format(<<<'PHP'
<?php
while($ready)
{
	step();
} // Done iterating.
// Describe the next statement.
after();
class Worker
{
	function work()
	{
		step();
	}
	public $value = 1;
}

PHP
		);
		self::assertStringContainsString("} // Done iterating.\n\n// Describe", $output);
		self::assertStringContainsString("Describe the next statement.\nafter();", $output);
		self::assertStringContainsString("\t}\n\n\tpublic \$value", $output);
	}

	public function testBlockSpacingDoesNotChangeInlineHtml(): void
	{
		$input = "<?php\nwhile(\$ready)\n{\n\tstep();\n} ?>Rendered output";
		$output = $this->format($input);
		self::assertStringEndsWith('?>Rendered output', $output);
	}

	public function testExpressionBracesDoNotAddBlockSpacing(): void
	{
		$input = <<<'PHP'
<?php
$value = $object->{'name'};
after();
$value = "}} next();";
after();
$value = match($key) { default => 1 };
after();

PHP;
		self::assertSame($input, $this->format($input));
	}

	public function testCreateRegression(): void
	{
		$output = $this->format(<<<'PHP'
<?php
class World
{
	public function create(object ...$components): int
	{ $id = $this->next++;
	$this->entities[$id] = [];
	foreach($components as $c)
	{ $this->add($id,$c); } return $id; }
}

PHP
		);

		self::assertSame(<<<'PHP'
<?php

class World
{

	public function create(object ...$components): int
	{
		$id = $this->next++;
		$this->entities[$id] = [];

		foreach($components as $c)
		{
			$this->add($id, $c);
		}

		return $id;
	}
}

PHP, $output
		);
	}

	public function testNestedControlRegression(): void
	{
		$output = $this->format(<<<'PHP'
<?php
foreach($this->entities[$id] as $class => $component)
{ if($component instanceof $type)
{
	unset($this->entities[$id][$class]);
	foreach($this->types($component) as $indexed)
	{ unset($this->index[$indexed][$id][$class]);
	if(empty($this->index[$indexed][$id]))
	{ unset($this->index[$indexed][$id]); } }
	$this->emit('removed',$id,$component);
} }

PHP
		);
		self::assertStringContainsString("\n{\n\tif(\$component instanceof \$type)\n\t{", $output);
		self::assertStringNotContainsString('{ unset', $output);
		self::assertStringContainsString("} }\n\n\t\t\$this->emit", $output);
	}

	public function testOptionalBracesExpandAndMandatoryShortBodiesRemain(): void
	{
		self::assertSame(
			"<?php\nif(\$ready)\n{\n\trun();\n}\n"
			, $this->format("<?php\nif(\$ready){ run(); }\n")
		);

		self::assertSame(
			"<?php\ntry{ run(); } catch(\\Exception \$e){ fail(); }\n"
			, $this->format("<?php\ntry{ run(); } catch(\\Exception \$e){ fail(); }\n")
		);
	}

	public function testLongAndMultilineHeadsExpand(): void
	{
		$output = $this->format("<?php\nfunction work(){ " . str_repeat('work(); ', 14) . "}\n");
		self::assertStringContainsString("function work()\n{\n\twork();\n", $output);
		self::assertStringNotContainsString('; work', $output);
		$output = $this->format("<?php\nfunction work(\n\t\$value\n){ return \$value; }\n");
		self::assertStringContainsString("){\n\treturn \$value;\n}", $output);
	}

	public function testSpacingPrecedesDocumentationAndAttributes(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$value = 1;
/** Describes the class. */
#[Example]
final class Example
{
/** Describes the method. */
#[Example]
public function run() { return 1; }
public function next() { return 2; }
}

PHP
		);

		self::assertStringContainsString(
			"\$value = 1;\n\n/** Describes the class. */\n#[Example]"
			, $output
		);
		self::assertStringContainsString("{\n\n\t/** Describes the method. */", $output);
		self::assertStringContainsString("\n\n\tpublic function next()", $output);
	}

	public function testDiagnosticsBoundaries(): void
	{
		self::assertSame([], Diagnostics::analyze("<?php\n" . str_repeat("work();\n", 10)));
		$messages = Diagnostics::analyze("<?php\n" . str_repeat("work();\n", 11));
		self::assertCount(1, $messages);
		self::assertSame('warning', $messages[0]['severity']);
		self::assertStringContainsString('semantic', $messages[0]['message']);
		self::assertSame([], Diagnostics::analyze("<?php\ncall(" . str_repeat('a', 93) . ");\n"));
		$messages = Diagnostics::analyze("<?php\ncall(" . str_repeat('a', 94) . ");\n");
		self::assertSame('max-line-length', $messages[0]['rule']);
	}

	public function testMultilineTryAndTokenPreservation(): void
	{
		$output = $this->format("<?php\ntry{ run(); }\ncatch(\\Exception \$e){ fail(); }\n");
		self::assertStringContainsString("catch(\\Exception \$e)\n{\n\tfail();\n}", $output);

		$this->format(<<<'PHP'
<?php
$value = "Hello {$user->name}";
$literal = <<<'TEXT'
  { this is not PHP; }
    literal spacing
TEXT;
if($value){ /* keep this */ echo $object->{'name'}; }
for($i = 0; $i < 10; $i++){ if($i){ work(); } }

PHP
		);
	}

	public function testGroupedRowsDoNotDuplicateTheirPrefix(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$config = [
    'schema' => ['type' => 'object', 'properties' => ['max' => ['type' => 'integer', 'minimum' => 1]],
        'additionalProperties' => false]
];

PHP
		);
		self::assertSame(1, substr_count($output, "'schema'"));
	}

	public function testInterpolatedStringPunctuationIsNeverWrapped(): void
	{
		$this->format(<<<'PHP'
<?php
if($ready){ $message = "prefix that makes this a long string $file:{$message['line']}: message contents $data[0];"; }
$message = "$left;$right";
$callback = static fn($item) => $item;
/** Keeps the assigned closure's documentation. */
$callback = static function($item) { return $item; };

PHP
		);
	}

	public function testDiagnosticUnicodeTabsAndProtectedText(): void
	{
		self::assertSame(6, Diagnostics::columns("\té🙂"));
		self::assertSame(5, Diagnostics::columns("a\tb"));
		$literal = "<?php\n\$text = '" . str_repeat('x', 150) . "';\n";
		self::assertSame([], Diagnostics::analyze($literal));
		self::assertNotEmpty(Diagnostics::analyze($literal, ignoreLiterals: false));

		self::assertSame(
			[]
			, Diagnostics::analyze("<?php\n/*\n" . str_repeat("comment\n", 15) . "*/\n")
		);

		self::assertSame(
			[]
			, Diagnostics::analyze(
				"<?php\n" . str_repeat("work();\n", 6) . "\n" . str_repeat("work();\n", 6)
			)
		);
	}

	public function testWarningCliSucceedsWithoutRewriting(): void
	{
		$file = tempnam(sys_get_temp_dir(), 'saccade-');
		$code = "<?php\n" . str_repeat("work();\n", 11);
		file_put_contents($file, $code);

		try
		{
			$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(
				__DIR__ . '/../bin/sm-no-saccade-style'
			)
				. ' ' . escapeshellarg($file);
			exec($command, $output, $status);
			self::assertSame(0, $status);
			self::assertCount(1, $output);
			self::assertStringStartsWith($file . ':2: warning ', $output[0]);

			self::assertStringContainsString(
				'warning [max-unseparated-lines]'
				, implode("\n", $output)
			);
			self::assertSame($code, file_get_contents($file));
			file_put_contents($file, "<?php\ncall(" . str_repeat('a', 94) . ");\n");
			$output = [];
			exec($command, $output, $status);
			self::assertSame(1, $status);
			self::assertStringContainsString('error [max-line-length]', implode("\n", $output));
		}
		finally
		{
			unlink($file);
		}
	}

	public function testArgumentSpacingInDeclarationsAndCalls(): void
	{
		$output = $this->format(<<<'PHP'
<?php
function combine($first,$second = 2,...$rest){ return $first; }
class Worker {
public function run($first,$second){ return combine($first,$second); }
}
$callback = fn($first,$second) => combine($first,$second);
$closure = function($first,$second) use ($left,$right){ return combine($first,$second); };
$worker = new Worker(1,2);
$worker?->run(1,/* keep the argument comment */2);
Worker::run(first: 1,second: combine(2,3));
combine('literal,;','unchanged');
combine($first,   $second);

PHP
		);
		self::assertStringContainsString('combine($first, $second = 2, ...$rest)', $output);
		self::assertStringContainsString('run($first, $second)', $output);

		self::assertStringContainsString(
			'fn($first, $second) => combine($first, $second)'
			, $output
		);
		self::assertStringContainsString('use ($left, $right)', $output);
		self::assertStringContainsString('new Worker(1, 2)', $output);
		self::assertStringContainsString('run(1, /* keep the argument comment */2)', $output);
		self::assertStringContainsString('Worker::run(first: 1, second: combine(2, 3))', $output);
		self::assertStringContainsString("combine('literal,;', 'unchanged')", $output);
		self::assertStringNotContainsString(',  ', $output);
	}

	public function testIndentedLiteralsThatCannotFitAreExempt(): void
	{
		$source = "<?php\n\t\t\t'" . str_repeat('x', 90) . "';\n";
		self::assertSame([], Diagnostics::analyze($source));
		$messages = Diagnostics::analyze($source, ignoreLiterals: false);
		self::assertSame('max-line-length', $messages[0]['rule']);
	}

	public function testForHeaderSpacingIncludesEmptyClausesAndPreservesNewlines(): void
	{
		$output = $this->format(<<<'PHP'
<?php
for($i = 0;$i < 3;$i++){ useValue($i); }
for(;;){ break; }
for(;$ready;){ break; }
for($i = (function(){ tick(); return 0; })();$i < 3;$i++){ useValue($i); }
for(
$i = 0;
$i < 3;
$i++
){ useValue($i); }
for($i = 0;/* keep the loop comment */$i < 3;$i++){ useValue($i); }

PHP
		);
		self::assertStringContainsString('for($i = 0; $i < 3; $i++)', $output);
		self::assertStringContainsString('for(; ;)', $output);
		self::assertStringContainsString('for(; $ready;)', $output);
		self::assertStringContainsString('})(); $i < 3; $i++)', $output);
		self::assertStringContainsString("\$i = 0;\n\t\$i < 3;\n\t\$i++", $output);
		self::assertStringContainsString('0; /* keep the loop comment */$i < 3; $i++)', $output);
	}

	public function testArgumentSpacingPreservesLeadingCommas(): void
	{
		$input = <<<'PHP'
<?php

function combine(
	$first
	, $second
){
	return run($first
		, $second
	);
}

PHP;
		self::assertSame($input, $this->format($input));
	}

	public function testObjectSpawnerUsesStatementGroupsAndCompleteArguments(): void
	{
		$input = file_get_contents(__DIR__ . '/fixtures/spawn.before.txt');
		$expected = file_get_contents(__DIR__ . '/fixtures/spawn.after.txt');
		self::assertSame($expected, $this->format($input));
	}

	public function testNestedClosersReturnToTheirOwnOpeningIndent(): void
	{
		$input = <<<'PHP'
<?php
$result = outer(
	inner(
		[
			'value' => true]));

PHP;
		$expected = <<<'PHP'
<?php
$result = outer(
	inner(
		[
			'value' => true
		]
	)
);

PHP;
		self::assertSame($expected, $this->format($input));
	}

	public function testStatementGroupsPreserveCommentsBranchesAndLoopReturn(): void
	{
		$output = $this->format(<<<'PHP'
<?php
function work($items)
{
	$results = [];
	// Filter each item.
	foreach($items as $item)
	{
		$ready = $item !== null;
		/** Keep this with the guard. */
		if(!$ready)
		{
			continue;
		}
		elseif($item === false)
		{
			throw new \Exception('literal },);');
		}
		// Keep processing details.
		$results[] = $item;
	}
	return $results;
}

PHP
		);
		self::assertStringContainsString("\$results = [];\n\n\t// Filter", $output);
		self::assertStringContainsString("\$ready = \$item !== null;\n\n\t\t/** Keep", $output);
		self::assertStringContainsString("}\n\t\telseif", $output);
		self::assertStringContainsString("}\n\n\t\t// Keep processing", $output);
		self::assertStringContainsString("\t}\n\n\treturn \$results;", $output);
		$this->format("<?php\n\$result = outer(\n\tinner(\n\t\t['value' => true] // keep\n\t));\n");
	}

	public function testCallWrappingHandlesConstructorsAndNamedArguments(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$someLongResultName = new ExampleFactory(argument: transform($firstLongParameterName, $secondLongParameterName));

PHP
		);
		self::assertStringContainsString("new ExampleFactory(\n\targument: transform(", $output);
		self::assertStringContainsString("\$secondLongParameterName)\n);", $output);
	}

	public function testNestedCallWrappingConvergesWithinOneFormattingRun(): void
	{
		$this->format(<<<'PHP'
<?php
$tokens = static fn(string $code): array => array_values(array_map(static fn(\PhpToken $token): array => [$token->id, $token->text], array_filter(\PhpToken::tokenize($code, TOKEN_PARSE), static fn(\PhpToken $token): bool => $token->id !== T_WHITESPACE)));

PHP
		);
	}

	public function testWrappingCallsWithGroupedArrayRowsConverges(): void
	{
		$this->format(<<<'PHP'
<?php
json('loop.tsj', ['type' => 'tileset', 'name' => 'loop', 'tilewidth' => 32, 'tileheight' => 32, 'tilecount' => 64, 'columns' => 8, 'image' => 'loop.png', 'imagewidth' => 256, 'imageheight' => 256, 'tiles' => $loopTiles]);

PHP
		);
	}

	private function format(string $input): string
	{
		$config = ConfigFactory::recommended([]);

		$factory = (new FixerFactory())->registerBuiltInFixers()
			->registerCustomFixers($config->getCustomFixers())
			->setWhitespacesConfig(
				new \PhpCsFixer\WhitespacesFixerConfig(
					$config->getIndent()
					, $config->getLineEnding()
				)
			)
			->useRuleSet(new RuleSet($config->getRules()));

		$apply = static function(string $code) use ($factory): string {
			$tokens = Tokens::fromCode($code);

			foreach($factory->getFixers() as $fixer)
			{
				$fixer->fix(new \SplFileInfo(__FILE__), $tokens);
			}

			return $tokens->generateCode();
		};

		$output = $apply($input);
		self::assertSame($output, $apply($output), 'Formatting must be idempotent.');

		$tokens = static fn(string $code): array => array_values(
			array_map(
				static fn(\PhpToken $token): array => [$token->id, $token->text]
				, array_filter(
					\PhpToken::tokenize($code, TOKEN_PARSE)
					, static fn(\PhpToken $token): bool => $token->id !== T_WHITESPACE
				)
			)
		);

		self::assertSame(
			$tokens($input)
			, $tokens($output)
			, 'Executable tokens and comments must survive.'
		);
		return $output;
	}
}
