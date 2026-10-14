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

final class ArrayLayoutTest extends TestCase
{

	public function testExpandedIndexedArraysStartBelowTheOpeningBracket(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$points[] = [$sprite->x + $x * $cos - $y * $sin
    , $sprite->y + $x * $sin + $y * $cos
];
PHP
		);

		self::assertStringContainsString("[\n\t\$sprite->x + \$x * \$cos - \$y * \$sin\n", $output);

		self::assertStringContainsString(
			"\n\t, \$sprite->y + \$x * \$sin + \$y * \$cos\n];"
			, $output
		);
	}

	public function testFirstMultilineConstructorGetsItsOwnArrayItemIndent(): void
	{
		$output = $this->format(<<<'PHP'
<?php
return new CardSequence([new Card(
    'loading'
    , 'Loading the demo'
    , 'Discovering title assets...'
    , duration: 1
    , fadeIn: .25
    , fadeOut: .25
    , skippable: false
    , ready: $ready
)
    , $title
]);
PHP
		);

		self::assertStringContainsString("new CardSequence([\n\tnew Card(\n\t\t'loading'", $output);
		self::assertStringContainsString("\n\t)\n\t, \$title\n]);", $output);
	}

	public function testGroupedIndexedRowsKeepTheirGroupsBelowTheOpener(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$matrix = [1, 0, 0
    , 0, 1, 0
    , 0, 0, 1];
PHP
		);

		self::assertStringContainsString("[\n\t1, 0, 0\n\t, 0, 1, 0\n\t, 0, 0, 1\n];", $output);
	}

	public function testNestedIndexedArraysHaveIndependentOpeningAndClosingRails(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$rows = [[$first
    , $second]
    , [$third
        , $fourth]
];
PHP
		);

		self::assertStringContainsString("[\n\t[\n\t\t\$first\n\t\t, \$second\n\t]", $output);
		self::assertStringContainsString("\n\t, [\n\t\t\$third\n\t\t, \$fourth\n\t]\n];", $output);
	}

	public function testIndexedLegacyArraysAndDestructuringUseTheSameBoundaries(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$items = array($first
    , $second);
list($left
    , $right) = $items;
[, $x
    , $y] = $items;
PHP
		);

		self::assertStringContainsString("array(\n\t\$first\n\t, \$second\n);", $output);
		self::assertStringContainsString("list(\n\t\$left\n\t, \$right\n) =", $output);
		self::assertStringContainsString("[\n\t, \$x\n\t, \$y\n] =", $output);
	}

	public function testIndexedArrayCommentsAndLiteralContentsStayIntact(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$items = [/* Explain the first value. */ $first
    , $second // Explain the second value.
];
$text = [<<<'TEXT'
    Literal spaces, commas, and brackets: [ ]
TEXT
    , "a\nb"
];
$slot = $items[
    $offset
];
PHP
		);

		self::assertStringContainsString("[\n\t/* Explain the first value. */ \$first", $output);

		self::assertStringContainsString(
			"\n\t, \$second // Explain the second value.\n];"
			, $output
		);
	}

	public function testShortInlineArraysStayInlineAndExplicitExpandedArraysStayExpanded(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$inline = [$first, $second];
$expanded = [$first
    , $second];
PHP
		);

		self::assertStringContainsString('$inline = [$first, $second];', $output);
		self::assertStringContainsString("\$expanded = [\n\t\$first\n\t, \$second\n];", $output);
	}

	public function testLongInlineIndexedArraysConvergeOnAnExpandedOpening(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$values = [$firstLongIdentifierForTheCollisionPoint, $secondLongIdentifierForTheCollisionPoint, $thirdLongIdentifierForTheCollisionPoint];
PHP
		);

		self::assertStringContainsString("[\n\t\$firstLongIdentifierForTheCollisionPoint", $output);
	}

	public function testExpandedMapsPutEveryEntryOnItsOwnLine(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$items = ['label' => 'Play', 'input' => 'output'
    , 'callback' => [$this, 'play']
];
PHP
		);

		self::assertStringContainsString(
			"[\n\t'label' => 'Play'\n\t, 'input' => 'output'"
			, $output
		);

		self::assertStringContainsString("\n\t, 'callback' => [\$this, 'play']\n];", $output);
	}

	public function testMapValuesCompactAfterTheirContainingEntriesExpand(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$state = ['phase' => $this->phase, 'card' => $this->cards->current()?->id
    , 'state' => $this
        ->cards->state
    , 'time' => $this->game
        ->engine->time
];
PHP
		);

		self::assertStringContainsString("\n\t, 'state' => \$this->cards->state\n", $output);
		self::assertStringContainsString("\n\t, 'time' => \$this->game->engine->time\n", $output);
	}

	public function testSoleArrayArgumentsKeepTheirCallDelimitersTogether(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$state = json_encode(
    ['phase' => $this->phase, 'time' => $this->time
        , 'progress' => $this->backdrop->progress()
    ]
);
PHP
		);

		self::assertStringContainsString("json_encode([\n\t'phase'", $output);
		self::assertStringContainsString("\n]);", $output);
	}

	public function testForeachArrayTailClosesOnItsOwnRailBeforeAnAllmanBlock(): void
	{
		$output = $this->format(<<<'PHP'
<?php
foreach(['platformer' => 'Platformer', 'world' => 'Streaming world'
    , 'loop' => 'Loop-de-loop', 'breakables' => 'Breakable blocks'
] as $id => $label
){
    register($id, $label);
}
PHP
		);

		self::assertStringContainsString("foreach([\n\t'platformer'", $output);
		self::assertStringContainsString("\n] as \$id => \$label)\n{\n\tregister", $output);
	}

	public function testShortMemberAccessStaysTogetherInsideATernaryCondition(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$status = ($this->error ?: $this->game->error)
    ?: ($this
        ->phase === 'menu'
        ? 'Choose Play, or adjust Level, Movement, and Settings.'
        : 'Start / Enter selects. Arrow keys or the D-pad move. Escape / B goes back.'
    );
PHP
		);

		self::assertStringContainsString("?: (\$this->phase === 'menu'", $output);
	}

	public function testNestedMapsExpandWithoutFlatteningCallbacksOrNumericRows(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$items = [
    'play' => ['label' => 'Play', 'input' => 'output'
        , 'callback' => function() {
            first();
            second();
        }
    ]
    , 'matrix' => [1, 0, 0
        , 0, 1, 0
        , 0, 0, 1
    ]
];
PHP
		);

		self::assertStringContainsString("'play' => [\n\t\t'label'", $output);
		self::assertStringContainsString("\n\t\t, 'input'", $output);
		self::assertStringContainsString("first();\n\t\t\tsecond();", $output);
		self::assertStringContainsString("0, 1, 0", $output);
	}

	public function testCommentsLiteralsAndArrayOffsetsArePreserved(): void
	{
		$this->format(<<<'PHP'
<?php
$items = ['first' => 1, // Explain the first entry.
    /* Explain the second entry. */ 'second' => $source['key'],
    'literal' => <<<'TEXT'
    Literal spaces, commas, => and brackets: [ ]
TEXT
];
$result = encode(
    // Explain why this array is passed.
    ['first' => 1
        , 'second' => 2
    ]
);
$slot = $items[
    $offset
];
PHP
		);
	}

	public function testLongInlineMapExpandsBeforeBreakingShortValues(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$intro = ['phase' => $this->phase, 'elapsed' => $this->cards->elapsed, 'time' => $this->time, 'progress' => $this->backdrop->progress()];
PHP
		);

		self::assertStringContainsString("[\n\t'phase'", $output);

		self::assertStringContainsString(
			"\n\t, 'progress' => \$this->backdrop->progress()"
			, $output
		);
	}

	public function testLegacyArraysAndKeyedDestructuringUseCompleteEntries(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$items = array('label' => 'Play', 'input' => 'output'
    , 'visible' => true
);
['label' => $label, 'input' => $input
    , 'visible' => $visible
] = $items;
PHP
		);

		self::assertStringContainsString("array(\n\t'label'", $output);
		self::assertStringContainsString("\n\t, 'input' => \$input\n", $output);
	}

	public function testCallableSyntaxAndMultipleArgumentsKeepTheirOwnBoundaries(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$encode = json_encode(...);
$object = new Example(
    ['label' => 'Play'
        , 'visible' => true
    ]
);
$encoded = json_encode(
    ['label' => 'Play'
        , 'visible' => true
    ]
    , JSON_THROW_ON_ERROR
);
PHP
		);

		self::assertStringContainsString('json_encode(...);', $output);
		self::assertStringContainsString("new Example([\n", $output);
		self::assertStringContainsString("json_encode(\n\t[\n", $output);
		self::assertStringContainsString("\n\t, JSON_THROW_ON_ERROR\n);", $output);
	}

	public function testInlineEntryCommentsStayWithTheirEntriesWhenTheMapExpands(): void
	{
		$output = $this->format(<<<'PHP'
<?php
$items = ['first' => 1, /* Explain the second entry. */ 'second' => 2
    , 'third' => 3
];
$other = ['first' => 1 /* Explain the first entry. */, 'second' => 2
    , 'third' => 3
];
PHP
		);

		self::assertStringContainsString(
			"'first' => 1,\n\t/* Explain the second entry. */ 'second' => 2"
			, $output
		);

		self::assertStringContainsString(
			"1 /* Explain the first entry. */\n\t, 'second' => 2"
			, $output
		);
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
				}
				elseif($token->id !== T_WHITESPACE) $executable[] = [$token->id, $token->text];
			}

			return [$executable, $comments];
		};

		self::assertSame(
			$program($input)
			, $program($output)
			, 'Preserve code, comments and literal contents.'
		);

		self::assertSame(
			[]
			, array_values(
				array_filter(
					Diagnostics::analyze($output, 120)
					, static fn(array $row): bool => $row['severity'] === 'error'
				)
			)
		);

		return $output;
	}
}
