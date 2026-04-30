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
		, `const x = [
	a
	, b
	, c
];`
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
	a
	,b
	,  c
];`
			, output: `const x = [
	a
	, b
	, c
];`
			, errors: [
				{ messageId: 'expectedSpaceAfterLeadingComma' }
				, { messageId: 'expectedSpaceAfterLeadingComma' }
			]
		}
		, {
			code: `const x = [
	a
	, /* keep comment */ b
];`
			, output: null
			, errors: [
				{ messageId: 'expectedSpaceAfterLeadingComma' }
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
			code: `const x = [
	reallyLongIdentifierAlpha /* keep comment */, reallyLongIdentifierBeta, reallyLongIdentifierGammaPlus
];`
			, output: null
			, errors: [
				{ messageId: 'groupedRowTooWide' }
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
		, {
			code: `const x = [
	{ alpha: 1, beta: 2, gamma: 3 }, { alpha: 4, beta: 5, gamma: 6 }, { alpha: 7, beta: 8, gamma: 9 }
];`
			, output: `const x = [
	{ alpha: 1, beta: 2, gamma: 3 }
	, { alpha: 4, beta: 5, gamma: 6 }
	, { alpha: 7, beta: 8, gamma: 9 }
];`
			, errors: [
				{ messageId: 'groupedRowTooWide' }
			]
		}
		, {
			code: `const x = [
	{ alpha: 1, beta: 2, gamma: 3 },
	{ alpha: 4, beta: 5, gamma: 6 },
	{ alpha: 7, beta: 8, gamma: 9 },
	{ alpha: 10, beta: 11, gamma: 12 },
	{ alpha: 13, beta: 14, gamma: 15 },
	{ alpha: 16, beta: 17, gamma: 18 },
	{ alpha: 19, beta: 20, gamma: 21 },
	{ alpha: 22, beta: 23, gamma: 24 },
	{ alpha: 25, beta: 26, gamma: 27 },
	{ alpha: 28, beta: 29, gamma: 30 }
];`
			, output: `const x = [
	{ alpha: 1, beta: 2, gamma: 3 }
	, { alpha: 4, beta: 5, gamma: 6 }
	, { alpha: 7, beta: 8, gamma: 9 }
	, { alpha: 10, beta: 11, gamma: 12 }
	, { alpha: 13, beta: 14, gamma: 15 }
	, { alpha: 16, beta: 17, gamma: 18 }
	, { alpha: 19, beta: 20, gamma: 21 }
	, { alpha: 22, beta: 23, gamma: 24 }
	, { alpha: 25, beta: 26, gamma: 27 }
	, { alpha: 28, beta: 29, gamma: 30 }
];`
			, errors: [
				{ messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
			]
		}
		, {
			code: `const x = [
	{
		alpha: 1
		, beta: 2
		, gamma: 3
	}, {
		alpha: 4
		, beta: 5
		, gamma: 6
	}, {
		alpha: 7
		, beta: 8
		, gamma: 9
	}
];`
			, output: `const x = [
	{
		alpha: 1
		, beta: 2
		, gamma: 3
	}
	, {
		alpha: 4
		, beta: 5
		, gamma: 6
	}
	, {
		alpha: 7
		, beta: 8
		, gamma: 9
	}
];`
			, errors: [
				{ messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
				, { messageId: 'expectedLeadingComma' }
				, { messageId: 'unexpectedTrailingComma' }
			]
		}
	]
});
