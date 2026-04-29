import { RuleTester } from 'eslint';

import rule from '../rules/no-space-control-paren.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022
		, sourceType: 'module'
	}
});

ruleTester.run('sm-no-saccade-style/no-space-control-paren', rule, {
	valid: [
		`if(foo) {
	bar();
}`
		, `while(bar) {
	baz();
}`
	]
	, invalid: [
		{
			code: `if (foo) {
	bar();
}`
			, output: `if(foo) {
	bar();
}`
			, errors: [{ messageId: 'unexpectedSpace' }]
		}
	]
});
