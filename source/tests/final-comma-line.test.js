import { RuleTester } from 'eslint';

import rule from '../rules/final-comma-line.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022
		, sourceType: 'module'
	}

});

ruleTester.run('sm-no-saccade-style/final-comma-line', rule, {
	valid: [
		{
			code: `const x = [
	'a'
	, 'b'
];`
		}
		, {
			code: `const x = [
	'a'
	, 'b'
];`
			, options: [{ mode: 'allow' }]
		}
		, {
			code: `const x = {
	a: 1
	, b: 2
};`
			, options: [{ mode: 'allow' }]
		}
		, {
			code: `const x = [
	'a'
	, 'b'
];`
			, options: [{ mode: 'forbid' }]
		}
	]
	, invalid: [
		{
			code: `const x = [
	'a'
	, 'b',
];`
			, output: `const x = [
	'a'
	, 'b'
	,
];`
			, errors: [{ messageId: 'expectedFinalCommaLine' }]
		}
		, {
			code: `const x = [
	'a'
	, 'b'
];`
			, output: `const x = [
	'a'
	, 'b'
	,
];`
			, options: [{ mode: 'require' }]
			, errors: [{ messageId: 'expectedFinalCommaLine' }]
		}
		, {
			code: `const x = [
	'a'
	, 'b'
	,
];`
			, output: `const x = [
	'a'
	, 'b'
];`
			, options: [{ mode: 'forbid' }]
			, errors: [{ messageId: 'unexpectedFinalCommaLine' }]
		}
	]

});
