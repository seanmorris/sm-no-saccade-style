<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Tests;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Fixer\Semicolon\MultilineWhitespaceBeforeSemicolonsFixer;
use PhpCsFixer\Fixer\Semicolon\SemicolonAfterInstructionFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use SeanMorris\NoSaccadeStyle\ConfigFactory;
use SeanMorris\NoSaccadeStyle\Fixer\AllmanTabsFixer;
use SeanMorris\NoSaccadeStyle\Fixer\FinalCommaLineFixer;
use SeanMorris\NoSaccadeStyle\Fixer\LeadingCommaListsFixer;
use SeanMorris\NoSaccadeStyle\Fixer\LeadingOperatorsFixer;
use SeanMorris\NoSaccadeStyle\Fixer\NoDoubleClosingGapFixer;
use SeanMorris\NoSaccadeStyle\Fixer\NoSpaceControlParenFixer;
use SeanMorris\NoSaccadeStyle\Fixer\NoTrailingWhitespaceFixer;
use SeanMorris\NoSaccadeStyle\Fixers;

final class FixerTest extends TestCase
{
	public function testAllmanTabsFixer(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
if($ready)
{
	work();
}

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
if($ready) {
    work();
}

PHP
			)
		);
	}

	public function testAllmanTabsFixerAllowsSingleLineClassBodies(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
class FakeHttpKrest extends Krest { protected static $Http; }

class InlineFakeHttpKrest extends Krest { protected static $Http; }

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
class FakeHttpKrest extends Krest
{ protected static $Http; }

class InlineFakeHttpKrest extends Krest { protected static $Http; }

PHP
			)
		);
	}

	public function testAllmanTabsFixerAllowsSingleLineFunctionsMethodsAndClasses(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
function add(){ return; };

class Calculator { public function add(){ return; } };

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
function add(){ return; };

class Calculator { public function add(){ return; } };

PHP
			)
		);
	}

	public function testAllmanTabsFixerAllowsSingleLineMethodInMultilineClass(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
class Calculator
{
	public function add(){ return; }
}

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
class Calculator
{
	public function add(){ return; }
}

PHP
			)
		);
	}

	public function testAllmanTabsFixerPreservesSingleSpaceClassPropertyEquals(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
class X
{
	public $short = 1;
	public $longerName = 2;
}

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
class X
{
	public $short = 1;
	public $longerName = 2;
}

PHP
			)
		);
	}

	public function testAllmanTabsFixerAlignsClassPropertyEqualsWhenAnyInitializerIsPadded(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
class X
{
	public $short      = 1;
	public $longerName = 2;
	public $mid        = 3;
}

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
class X
{
	public $short  = 1;
	public $longerName = 2;
	public $mid = 3;
}

PHP
			)
		);
	}

	public function testAllmanTabsFixerEnsuresSpaceAroundClassPropertyEquals(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
class X
{
	public $short = 1;
	public $longerName = 2;

	public function run($value = 1)
	{
		$local = 2;
	}
}

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
class X
{
	public $short=1;
	public $longerName =2;

	public function run($value = 1)
	{
		$local = 2;
	}
}

PHP
			)
		);
	}

	public function testAllmanTabsFixerPreservesDynamicMemberAccessBraces(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
if($ready)
{
	$literal = $response->{'X-Test-Method'};
	$variable = $this->{ $prop };
	$nullable = $response?->{'X-Test-Method'};
	$static = Handler::{ $method }();
}

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
if($ready) {
    $literal = $response->{'X-Test-Method'};
    $variable = $this->{ $prop };
    $nullable = $response?->{'X-Test-Method'};
    $static = Handler::{ $method }();
}

PHP
			)
		);
	}

	public function testAllmanTabsFixerIsIdempotentForDynamicMemberAccess(): void
	{
		$once = $this->apply(
			new AllmanTabsFixer()
			, <<<'PHP'
<?php
if(!isset($r->{ '@type' }))
{
	$this->{ $prop } = $blob->{ $prop };
}

PHP
		);

		$this->assertSame($once, $this->apply(new AllmanTabsFixer(), $once));
	}

	public function testAllmanTabsFixerRejoinsSplitDynamicMemberAccessBraces(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$literal = $response->{'X-Test-Method'};
$variable = $this->{ $prop };
$nullable = $response?->{'X-Test-Method'};
$static = Handler::{ $method }();

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
$literal = $response->
	{'X-Test-Method'};
$variable = $this->
	{ $prop };
$nullable = $response?->
	{'X-Test-Method'};
$static = Handler::
	{ $method }();

PHP
			)
		);
	}

	public function testAllmanTabsFixerDoesNotSplitNestedCallClosersAfterDeeperContent(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$response = Http::post(
	$endpoint
	, json_encode([
		'ksql' => $string
	]
));

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
$response = Http::post(
    $endpoint
    , json_encode([
        'ksql' => $string
    ]
));

PHP
			)
		);
	}

	public function testAllmanTabsFixerRejoinsSplitNestedCallClosersAfterListCloser(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$response = Http::post(
	$endpoint
	, json_encode([
		'ksql' => $string
	]
));

PHP
			, $this->apply(
				new AllmanTabsFixer()
				, <<<'PHP'
<?php
$response = Http::post(
	$endpoint
	, json_encode([
		'ksql' => $string
	]
	)
);

PHP
			)
		);
	}

	public function testAllmanTabsFixerPreservesFlexibleHeredocAndNowdocIndentation(): void
	{
		$once = $this->apply(
			new AllmanTabsFixer()
			, <<<'PHP'
<?php
function strings($name) {
    $heredoc = <<<TXT
        hello {$name}
        TXT;
    $nowdoc = <<<'TXT'
        hello {$name}
        TXT;
    return [$heredoc, $nowdoc];
}

PHP
		);

		$this->assertSame(
			<<<'PHP'
<?php
function strings($name)
{
	$heredoc = <<<TXT
        hello {$name}
        TXT;
	$nowdoc = <<<'TXT'
        hello {$name}
        TXT;
	return [$heredoc, $nowdoc];
}

PHP
			, $once
		);
		$this->assertSame($once, $this->apply(new AllmanTabsFixer(), $once));
	}

	public function testRecommendedCustomFixersAreIdempotentForDynamicMemberAccess(): void
	{
		$once = $this->applyMany(
			$this->recommendedCustomFixers()
			, <<<'PHP'
<?php
if(!isset($r->
	{ '@type' })) {
    call($this->
        { $prop });
}

PHP
		);

		$this->assertSame(
			<<<'PHP'
<?php
if(!isset($r->{ '@type' }))
{
	call($this->{ $prop });
}

PHP
			, $once
		);

		$this->assertSame($once, $this->applyMany($this->recommendedCustomFixers(), $once));
	}

	public function testRecommendedCustomFixersRemoveTrailingCommaFromWaitServicesList(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$services = [
	'ksql-server'    => 'http://ksql-server:8088/info'
	, 'krest-server' => 'http://krest-server:8082/topics'
];

PHP
			, $this->applyMany(
				$this->recommendedCustomFixers()
				, <<<'PHP'
<?php
$services = [
	'ksql-server'  => 'http://ksql-server:8088/info',
	'krest-server' => 'http://krest-server:8082/topics',
];

PHP
			)
		);
	}

	public function testRecommendedCustomFixersUseLeadingCommasInVerticalCallArguments(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
fwrite(STDERR, sprintf(
	"Timed out waiting for: %s\n"
	, implode(', ', array_keys($services))
));

PHP
			, $this->applyMany(
				$this->recommendedCustomFixers()
				, <<<'PHP'
<?php
fwrite(STDERR, sprintf(
	"Timed out waiting for: %s\n",
	implode(', ', array_keys($services))
));

PHP
			)
		);
	}

	public function testFinalCommaLineForbidMode(): void
	{
		$fixer = new FinalCommaLineFixer();
		$fixer->configure(['mode' => 'forbid']);

		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a'
	, 'b'
];

PHP
			, $this->apply(
				$fixer
				, <<<'PHP'
<?php
$value = [
	'a'
	, 'b'
	,
];

PHP
			)
		);
	}

	public function testFinalCommaLineAllowMode(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a'
	, 'b'
	,
];

PHP
			, $this->apply(
				new FinalCommaLineFixer()
				, <<<'PHP'
<?php
$value = [
	'a'
	, 'b'
	,
];

PHP
			)
		);
	}

	public function testFinalCommaLineRequireMode(): void
	{
		$fixer = new FinalCommaLineFixer();
		$fixer->configure(['mode' => 'require']);

		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a'
	, 'b'
	,
];

PHP
			, $this->apply(
				$fixer
				, <<<'PHP'
<?php
$value = [
	'a'
	, 'b'
];

PHP
			)
		);
	}

	public function testFinalCommaLineForbidModePreservesCommentOnCommaLine(): void
	{
		$fixer = new FinalCommaLineFixer();
		$fixer->configure(['mode' => 'forbid']);

		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a'
	 // keep final-item context
];

PHP
			, $this->apply(
				$fixer
				, <<<'PHP'
<?php
$value = [
	'a'
	, // keep final-item context
];

PHP
			)
		);
	}

	public function testFinalCommaLineRequireModePreservesCommentBeforeFinalComma(): void
	{
		$fixer = new FinalCommaLineFixer();
		$fixer->configure(['mode' => 'require']);

		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a' /* keep final-item context */
	,
];

PHP
			, $this->apply(
				$fixer
				, <<<'PHP'
<?php
$value = [
	'a' /* keep final-item context */,
];

PHP
			)
		);
	}

	public function testLeadingCommaListsFixer(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a'
	, 'b'
];

PHP
			, $this->apply(
				new LeadingCommaListsFixer()
				, <<<'PHP'
<?php
$value = [
	'a',
	'b'
];

PHP
			)
		);
	}

	public function testLeadingCommaListsFixerPreservesCommentsAroundCommas(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a' /* keep before comma */
	, 'b', /* keep after comma */
	'c'
];

PHP
			, $this->apply(
				new LeadingCommaListsFixer()
				, <<<'PHP'
<?php
$value = [
	'a' /* keep before comma */,
	'b', /* keep after comma */
	'c'
];

PHP
			)
		);
	}

	public function testLeadingCommaListsFixerPreservesSingleSpaceDoubleArrows(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a' => 1
	, 'longer' => 2
];

PHP
			, $this->apply(
				new LeadingCommaListsFixer()
				, <<<'PHP'
<?php
$value = [
	'a' => 1
	, 'longer' => 2
];

PHP
			)
		);
	}

	public function testLeadingCommaListsFixerAlignsDoubleArrowsWhenAnyArrowIsPadded(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$value = [
	'a'        => 1
	, 'longer' => 2
	, 'mid'    => 3
];

PHP
			, $this->apply(
				new LeadingCommaListsFixer()
				, <<<'PHP'
<?php
$value = [
	'a'  => 1
	, 'longer' => 2
	, 'mid' => 3
];

PHP
			)
		);
	}

	public function testLeadingCommaListsFixerEnsuresSpaceAfterDoubleArrows(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$value = ['a' => 1];
foreach($items as $key => $value){}
$mapper = fn($value) => $value;
$kept = ['x' =>  1];

PHP
			, $this->apply(
				new LeadingCommaListsFixer()
				, <<<'PHP'
<?php
$value = ['a' =>1];
foreach($items as $key =>$value){}
$mapper = fn($value) =>$value;
$kept = ['x' =>  1];

PHP
			)
		);
	}

	public function testLeadingCommaListsFixerPreservesCommentAfterDoubleArrow(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$value = ['key' => /* keep value context */1];

PHP
			, $this->apply(
				new LeadingCommaListsFixer()
				, <<<'PHP'
<?php
$value = ['key' =>/* keep value context */1];

PHP
			)
		);
	}

	public function testLeadingCommaListsFixerRecognizesExpressionAndDestructuringContexts(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
[
	$first
	, [
		$second
		, $third
	]
] = $row;

foreach($rows as $key => [
	$id
	, $name
]) {}

$value = $ready
	? [
		'yes'
		, true
	]
	: [
		'no'
		, false
	];

$merged = [
	...[
		'a'
		, 'b'
	]
	, 'c'
];

PHP
			, $this->apply(
				new LeadingCommaListsFixer()
				, <<<'PHP'
<?php
[
	$first,
	[
		$second,
		$third
	]
] = $row;

foreach($rows as $key => [
	$id,
	$name
]) {}

$value = $ready
	? [
		'yes',
		true
	]
	: [
		'no',
		false
	];

$merged = [
	...[
		'a',
		'b'
	],
	'c'
];

PHP
			)
		);
	}

	public function testFinalCommaLineRequireModeDoesNotTreatArrayOffsetAsList(): void
	{
		$fixer = new FinalCommaLineFixer();
		$fixer->configure(['mode' => 'require']);
		$code = <<<'PHP'
<?php
$value = $rows[
	$key
];
$character = 'value'[
	0
];

PHP;

		$this->assertSame($code, $this->apply($fixer, $code));
	}

	public function testLeadingOperatorsFixer(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$ready = $loaded
	&& $valid;

PHP
			, $this->apply(
				new LeadingOperatorsFixer()
				, <<<'PHP'
<?php
$ready = $loaded &&
	$valid;

PHP
			)
		);
	}

	public function testLeadingOperatorsFixerPreservesCommentBeforeOperator(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$ready = $loaded /* keep condition context */
	&& $valid;

PHP
			, $this->apply(
				new LeadingOperatorsFixer()
				, <<<'PHP'
<?php
$ready = $loaded /* keep condition context */ &&
	$valid;

PHP
			)
		);
	}

	public function testRecommendedCustomFixersPreserveCommentsAndRemainIdempotent(): void
	{
		$input = <<<'PHP'
<?php
$value = [
	'a' /* before comma */,
	'b' =>/* after arrow */1
	, // after final comma
];
$ready = $loaded /* before operator */ &&
	$valid;

PHP;
		$once = $this->applyMany($this->recommendedCustomFixers(), $input);

		$this->assertSame($this->commentTexts($input), $this->commentTexts($once));
		$this->assertSame($once, $this->applyMany($this->recommendedCustomFixers(), $once));
	}

	public function testNoDoubleClosingGapFixer(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
call(
	wrap(
		$value
	)
);

PHP
			, $this->apply(
				new NoDoubleClosingGapFixer()
				, <<<'PHP'
<?php
call(
	wrap(
		$value
	)

);

PHP
			)
		);
	}

	public function testNoSpaceControlParenFixer(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
if($ready) {
	work();
}

PHP
			, $this->apply(
				new NoSpaceControlParenFixer()
				, <<<'PHP'
<?php
if ($ready) {
	work();
}

PHP
			)
		);
	}

	public function testNoTrailingWhitespaceFixer(): void
	{
		$this->assertSame(
			"<?php\n\$value = \"kept   \ninside\";\n"
			, $this->apply(
				new NoTrailingWhitespaceFixer()
				, "<?php\n\$value = \"kept   \ninside\";   \n"
			)
		);
	}

	public function testRecommendedConfigRegistersPublicRules(): void
	{
		$config = ConfigFactory::recommended([]);

		$this->assertArrayHasKey(Fixers::LEADING_COMMA_LISTS, $config->getRules());
		$this->assertArrayHasKey(Fixers::FINAL_COMMA_LINE, $config->getRules());
		$this->assertArrayHasKey(Fixers::LEADING_OPERATORS, $config->getRules());
		$this->assertArrayHasKey('multiline_whitespace_before_semicolons', $config->getRules());
		$this->assertArrayHasKey('semicolon_after_instruction', $config->getRules());
		$this->assertCount(7, $config->getCustomFixers());
	}

	public function testCustomFixersExposeMetadata(): void
	{
		$expectedNames = [
			Fixers::LEADING_COMMA_LISTS
			, Fixers::FINAL_COMMA_LINE
			, Fixers::LEADING_OPERATORS
			, Fixers::ALLMAN_TABS
			, Fixers::NO_DOUBLE_CLOSING_GAP
			, Fixers::NO_SPACE_CONTROL_PAREN
			, Fixers::NO_TRAILING_WHITESPACE
		];

		foreach(Fixers::all() as $index => $fixer)
		{
			$this->assertSame($expectedNames[$index], $fixer->getName());
			$this->assertNotSame('', $fixer->getDefinition()->getSummary());
			$this->assertNotEmpty($fixer->getDefinition()->getCodeSamples());
		}
	}

	public function testRecommendedSemicolonPresenceAndPlacementRules(): void
	{
		$this->assertSame(
			<<<'PHP'
<?php
$one = 1;
echo $one; ?>
PHP
			, $this->applyMany(
				[
					new SemicolonAfterInstructionFixer()
					, new MultilineWhitespaceBeforeSemicolonsFixer()
				]
				, <<<'PHP'
<?php
$one = 1
;
echo $one ?>
PHP
			)
		);
	}

	private function apply(FixerInterface $fixer, string $code): string
	{
		$tokens = Tokens::fromCode($code);
		$fixer->fix(new \SplFileInfo(__FILE__), $tokens);

		return $tokens->generateCode();
	}

	private function applyMany(array $fixers, string $code): string
	{
		$tokens = Tokens::fromCode($code);

		foreach($fixers as $fixer)
		{
			$fixer->fix(new \SplFileInfo(__FILE__), $tokens);
		}

		return $tokens->generateCode();
	}

	private function commentTexts(string $code): array
	{
		$comments = [];

		foreach(\PhpToken::tokenize($code, TOKEN_PARSE) as $token)
		{
			if($token->id === T_COMMENT || $token->id === T_DOC_COMMENT)
			{
				$comments[] = $token->text;
			}
		}

		return $comments;
	}

	private function recommendedCustomFixers(): array
	{
		$fixers = Fixers::all();

		foreach($fixers as $fixer)
		{
			if($fixer instanceof FinalCommaLineFixer)
			{
				$fixer->configure(['mode' => 'forbid']);
			}
		}

		return $fixers;
	}
}
