<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet\RuleSet;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use SeanMorris\NoSaccadeStyle\ConfigFactory;

$config = ConfigFactory::recommended([]);

$factory = (new FixerFactory())->registerBuiltInFixers()
	->registerCustomFixers($config->getCustomFixers())
	->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\n"))
	->useRuleSet(new RuleSet($config->getRules()));

$payload = "\x00\xff\r\n    embedded data\t  \n";
$source = '<?php if(false){ work(); } __halt_compiler();' . $payload;
$tokens = Tokens::fromCode($source);

foreach($factory->getFixers() as $fixer) $fixer->fix(new SplFileInfo(__FILE__), $tokens);

$output = $tokens->generateCode();

if(!str_contains($output, "if(false)\n{\n\twork();\n}"))
throw new RuntimeException('The installed recommended preset must format PHP code.');

$data = null;

foreach(PhpToken::tokenize($output, TOKEN_PARSE) as $token)
if($token->id === T_INLINE_HTML) $data = $token->text;

if($data !== $payload) throw new RuntimeException('The installed package changed the payload.');

foreach($factory->getFixers() as $fixer) $fixer->fix(new SplFileInfo(__FILE__), $tokens);

if($tokens->generateCode() !== $output) throw new RuntimeException('Formatting did not converge.');

file_put_contents(__DIR__ . '/payload.php', $output);
