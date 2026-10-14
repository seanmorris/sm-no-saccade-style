<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;

final class AllmanTabsFixer extends AbstractNoSaccadeFixer implements ConfigurableFixerInterface
{
	use ConfigurableFixerTrait;

	public function getPriority(): int
	{
		return -5;
	}

	protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
	{
		return new FixerConfigurationResolver(
			[(new FixerOptionBuilder(
				'max_line_length'
				, 'Maximum columns for a complete compact statement.'
			))->setAllowedTypes(['int'])
				->setAllowedValues([static fn(int $value): bool => $value >= 40])->setDefault(100)
				->getOption()
			]
		);
	}

	protected static function ruleName(): string
	{
		return 'no_saccade_allman_tabs';
	}

	protected static function summary(): string
	{
		return 'Enforces Allman braces, tab indentation, and closing delimiter rails.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withAllmanTabs($code, $this->configuration['max_line_length']);
	}
}
