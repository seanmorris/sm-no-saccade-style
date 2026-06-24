<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;

abstract class AbstractNoSaccadeFixer extends AbstractFixer
{
	final public function getName(): string
	{
		return 'SeanMorris/' . static::ruleName();
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			static::summary()
			, [
				new CodeSample("<?php\n\$value = [\n\t'a',\n\t'b'\n];\n")
			]
		);
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return true;
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$tokens->setCode($this->transform($tokens->generateCode()));
	}

	abstract protected static function ruleName(): string;

	abstract protected static function summary(): string;

	abstract protected function transform(string $code): string;
}
