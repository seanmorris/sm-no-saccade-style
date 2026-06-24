<?php

declare(strict_types=1);

namespace SeanMorris\NoSaccadeStyle\Fixer;

use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOption;
use SeanMorris\NoSaccadeStyle\Internal\SourceTransformer;

final class FinalCommaLineFixer extends AbstractNoSaccadeFixer implements ConfigurableFixerInterface
{
	protected array $configuration = [
		'mode' => 'allow'
	];

	public function configure(array $configuration): void
	{
		$this->configuration = $this->getConfigurationDefinition()->resolve($configuration);
	}

	public function getConfigurationDefinition(): FixerConfigurationResolverInterface
	{
		return new FixerConfigurationResolver([
			new FixerOption(
				'mode'
				, 'Whether a final comma-only line is allowed, required, or forbidden.'
				, false
				, 'allow'
				, ['string']
				, ['allow', 'require', 'forbid']
			)
		]);
	}

	protected static function ruleName(): string
	{
		return 'no_saccade_final_comma_line';
	}

	protected static function summary(): string
	{
		return 'Controls final comma-only lines before closing array delimiters.';
	}

	protected function transform(string $code): string
	{
		return SourceTransformer::withFinalCommaLine($code, $this->configuration['mode']);
	}
}
