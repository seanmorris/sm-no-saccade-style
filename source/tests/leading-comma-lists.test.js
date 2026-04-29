import { RuleTester } from 'eslint';

import rule from '../rules/leading-comma-lists.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022
		, sourceType: 'module'
	}
});

ruleTester.run('sm-no-saccade-style/leading-comma-lists', rule, {
	valid: [
		`const x = [
	'a'
	, 'b'
];`
		, `const x = {
	a: 1
	, b: 2
};`
		, `const x = ['a', 'b'];`
		, `const [a, ...rest] = source;`
		, `const {
	a
	// keep comment
	, b
} = source;`
	]
	, invalid: [
		{
			code: `const x = [
	'a',
	'b'
];`
			, output: `const x = [
	'a'
	, 'b'
];`
			, errors: [
				{ messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
			]
		}
		, {
			code: `const x = {
	a: 1,
	b: 2
};`
			, output: `const x = {
	a: 1
	, b: 2
};`
			, errors: [
				{ messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
			]
		}
		, {
			code: `const {
	a,
	b
} = source;`
			, output: `const {
	a
	, b
} = source;`
			, errors: [
				{ messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
			]
		}
		, {
			code: `const x = [
	'a',
	// keep comment
	'b'
];`
			, output: null
			, errors: [
				{ messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
			]
		}
	]
});
