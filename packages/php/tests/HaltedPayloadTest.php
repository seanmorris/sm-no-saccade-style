<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Tests;

use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet\RuleSet;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SeanMorris\NoSaccadeStyle\ConfigFactory;
use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class HaltedPayloadTest extends TestCase
{

	public static function payloads(): iterable
	{
		yield 'immediate text' => ["abc\n"];
		yield 'no final newline' => ['payload'];
		yield 'significant whitespace' => ["    indented\ntrailing   \n\n"];
		yield 'CRLF' => ["\r\n    indented\t\r\n"];
		yield 'binary' => ["\x00\xff\x80\n    bytes\t  \n"];
		yield 'PHP-looking data' => ["<?php __halt_compiler(); { [ ?>\n"];
		yield 'delimiter only' => ['{'];
		yield 'long data' => [str_repeat('  payload  ', 30)];
	}

	#[DataProvider('payloads')]
	public function testRecommendedFixersPreservePayloadAndRuntimeOffset(string $payload): void
	{
		$prefix = <<<'PHP'
<?php
if(false){ forbidden(); }
$stream = fopen(__FILE__, 'rb');
fseek($stream, __COMPILER_HALT_OFFSET__);
echo stream_get_contents($stream);
__halt_compiler();
PHP;

		$input = $prefix . $payload;
		$output = $this->format($input);
		self::assertSame($payload, $this->payload($output));
		self::assertSame($output, $this->format($output));
		self::assertStringContainsString("if(false)\n{\n\tforbidden();\n}", $output);

		$file = tempnam(sys_get_temp_dir(), 'no-saccade-payload-');
		self::assertNotFalse($file);

		try
		{
			file_put_contents($file, $output);

			$process = proc_open([PHP_BINARY, $file], [
				1 => ['pipe', 'w']
				, 2 => ['pipe', 'w']
			], $pipes
			);

			self::assertIsResource($process);
			$actual = stream_get_contents($pipes[1]);
			$error = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			self::assertSame(0, proc_close($process), $error);

			self::assertSame(
				$payload
				, $actual
				, 'The compiler offset must still locate the exact payload.'
			);
		}
		finally
		{
			unlink($file);
		}
	}

	public function testIndividualWhitespacePassesPreserveOpaqueData(): void
	{
		$payload = "    header\n\x00\xff\n    row\t  \n";
		$input = '<?php __halt_compiler();' . $payload;
		self::assertSame($input, SourceTransformer::withoutTrailingWhitespace($input));
		$output = SourceTransformer::withAllmanTabs($input);
		self::assertSame($payload, $this->payload($output));
		self::assertSame($output, SourceTransformer::withAllmanTabs($output));
	}

	public function testOrdinaryInlineHtmlRetainsItsWhitespace(): void
	{
		$input = "<?php if(false){ forbidden(); } ?>    data  \n    <pre>    text  </pre>  \n";
		$output = $this->format($input);
		self::assertSame($this->payload($input), $this->payload($output));
		self::assertSame($output, $this->format($output));
	}

	private function payload(string $code): string
	{
		foreach(\PhpToken::tokenize($code, TOKEN_PARSE) as $token)
		if($token->id === T_INLINE_HTML) return $token->text;

		self::fail('Expected an opaque data token.');
	}

	private function format(string $code): string
	{
		$config = ConfigFactory::recommended([]);

		$factory = (new FixerFactory())->registerBuiltInFixers()
			->registerCustomFixers($config->getCustomFixers())
			->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\n"))
			->useRuleSet(new RuleSet($config->getRules()));

		$tokens = Tokens::fromCode($code);

		foreach($factory->getFixers() as $fixer)
		$fixer->fix(new \SplFileInfo(__FILE__), $tokens);

		return $tokens->generateCode();
	}
}
