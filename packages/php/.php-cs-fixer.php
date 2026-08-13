<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
	->in([
		__DIR__ . '/src'
		, __DIR__ . '/tests'
	])
;

return SeanMorris\NoSaccadeStyle\ConfigFactory::recommended($finder);
