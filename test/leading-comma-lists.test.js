import { RuleTester } from 'eslint';

import rule from '../source/rules/leading-comma-lists.js';

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
		, `const x = [
	...rest
];`
		, `const x = [
	a
	, ...(cond ? list : [])
	, b
];`
		, `const x = [
	'a', b
];`
		, `const x = [
	0.0, 0.0
	, 1.0, 0.0
	, 0.0, 1.0
	, 0.0, 1.0
	, 1.0, 0.0
	, 1.0, 1.0
];`
		, `const x = [
	a
	, b, {
		c: 1
	}
];`
		, `const x = [
	a
	, b, [
		c
	]
];`
		, `const x = [
	a
	, b, () => {
		return c;
	}
];`
		, `const x = {
	duration: 0, weakMagnitude: 0, strongMagnitude: 0
};`
		, `const [
	a
	, ...rest
] = source;`
		, `const x = {
	...rest
};`
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
		, {
			code: `const x = [
	a,
	...(cond ? list : []),
	b
];`
			, output: `const x = [
	a
	, ...(cond ? list : [])
	, b
];`
			, errors: [
				{ messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
			]
		}
		, {
			code: `const x = [
	reallyLongIdentifierAlpha, reallyLongIdentifierBeta, reallyLongIdentifierGammaPlus
	, reallyLongIdentifierDelta, reallyLongIdentifierEpsilon, reallyLongIdentifierZeta
];`
			, output: `const x = [
	reallyLongIdentifierAlpha
	, reallyLongIdentifierBeta
	, reallyLongIdentifierGammaPlus
	, reallyLongIdentifierDelta
	, reallyLongIdentifierEpsilon
	, reallyLongIdentifierZeta
];`
			, errors: [
				{ messageId: 'groupedRowTooWide' }
				, { messageId: 'groupedRowTooWide' }
			]
		}
		, {
			code: `const x = {
	reallyLongIdentifierAlpha: 1, reallyLongIdentifierBeta: 2, reallyLongIdentifierGammaPlus: 3
};`
			, output: `const x = {
	reallyLongIdentifierAlpha: 1
	, reallyLongIdentifierBeta: 2
	, reallyLongIdentifierGammaPlus: 3
};`
			, errors: [
				{ messageId: 'groupedRowTooWide' }
			]
		}
	]
});
