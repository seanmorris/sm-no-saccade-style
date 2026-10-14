<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
	->in([
		__DIR__ . '/src'
		, __DIR__ . '/tests'
	])
	->append([__DIR__ . '/bin/sm-no-saccade-style'])
;

return SeanMorris\NoSaccadeStyle\ConfigFactory::recommended($finder)->setUsingCache(false);
