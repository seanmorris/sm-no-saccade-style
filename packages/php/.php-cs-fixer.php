<?php

require_once __DIR__ . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
	->in([
		__DIR__ . '/src'
		, __DIR__ . '/tests'
	])
;

return SeanMorris\NoSaccadeStyle\ConfigFactory::recommended($finder);
