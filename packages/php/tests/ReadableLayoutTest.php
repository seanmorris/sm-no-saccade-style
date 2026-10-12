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

PHP);
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
			$this->add($id,$c);
		}
		return $id;
	}
}

PHP, $output);
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

PHP);
		self::assertStringContainsString("\n{\n\tif(\$component instanceof \$type)\n\t{", $output);
		self::assertStringNotContainsString('{ unset', $output);
		self::assertStringNotContainsString('} }', $output);
	}

	public function testOptionalBracesExpandAndMandatoryShortBodiesRemain(): void
	{
		self::assertSame("<?php\nif(\$ready)\n{\n\trun();\n}\n",
			$this->format("<?php\nif(\$ready){ run(); }\n"));
		self::assertSame("<?php\ntry{ run(); } catch(\\Exception \$e){ fail(); }\n",
			$this->format("<?php\ntry{ run(); } catch(\\Exception \$e){ fail(); }\n"));
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

PHP);
		self::assertStringContainsString("\$value = 1;\n\n/** Describes the class. */\n#[Example]"
			, $output);
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

PHP);
	}

	public function testGroupedRowsDoNotDuplicateTheirPrefix(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$config = [
    'schema' => ['type' => 'object', 'properties' => ['max' => ['type' => 'integer', 'minimum' => 1]],
        'additionalProperties' => false]
];

PHP);
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

PHP);
	}

	public function testDiagnosticUnicodeTabsAndProtectedText(): void
	{
		self::assertSame(6, Diagnostics::columns("\té🙂"));
		self::assertSame(5, Diagnostics::columns("a\tb"));
		$literal = "<?php\n\$text = '" . str_repeat('x', 150) . "';\n";
		self::assertSame([], Diagnostics::analyze($literal));
		self::assertNotEmpty(Diagnostics::analyze($literal, ignoreLiterals: false));
		self::assertSame([], Diagnostics::analyze("<?php\n/*\n" . str_repeat("comment\n", 15)
			. "*/\n"));
		self::assertSame([], Diagnostics::analyze("<?php\n" . str_repeat("work();\n", 6)
			. "\n" . str_repeat("work();\n", 6)));
	}

	public function testWarningCliSucceedsWithoutRewriting(): void
	{
		$file = tempnam(sys_get_temp_dir(), 'saccade-');
		$code = "<?php\n" . str_repeat("work();\n", 11);
		file_put_contents($file, $code);
		try
		{
			$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__
				. '/../bin/sm-no-saccade-style')
				. ' ' . escapeshellarg($file);
			exec($command, $output, $status);
			self::assertSame(0, $status);
			self::assertCount(1, $output);
			self::assertStringStartsWith($file . ':2: warning ', $output[0]);
			self::assertStringContainsString('warning [max-unseparated-lines]', implode("\n"
				, $output));
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

	private function format(string $input): string
	{
		$config = ConfigFactory::recommended([]);
		$factory = (new FixerFactory())->registerBuiltInFixers()
			->registerCustomFixers($config->getCustomFixers())
			->setWhitespacesConfig(new \PhpCsFixer\WhitespacesFixerConfig($config->getIndent()
				, $config->getLineEnding()))
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

		$tokens = static fn(string $code): array => array_values(array_map(
			static fn(\PhpToken $token): array => [$token->id, $token->text]
			, array_filter(\PhpToken::tokenize($code, TOKEN_PARSE),
				static fn(\PhpToken $token): bool => $token->id !== T_WHITESPACE)
		));
		self::assertSame($tokens($input), $tokens($output)
			, 'Executable tokens and comments must survive.');
		return $output;
	}
}
