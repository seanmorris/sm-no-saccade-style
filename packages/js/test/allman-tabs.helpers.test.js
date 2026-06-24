import assert from 'node:assert/strict';

import {
	buildMixedClosingRailFix,
	collectChainedCallbackBodyRanges,
	hasMultilineControlHead
} from '../source/rules/allman-tabs.js';

assert.deepEqual(collectChainedCallbackBodyRanges(null), []);

const mismatchSourceCode = {
	lines: [
		'\t\tbar();'
	]
};
const mismatchPreviousToken = {
	loc: {
		start: { line: 1, column: 0 }
	}
};
const mismatchClosingTokens = [
	{ value: ')', range: [0, 1] }
];

assert.equal(
	buildMixedClosingRailFix(mismatchSourceCode, {}, mismatchPreviousToken, mismatchClosingTokens, ['\t']),
	null
);

const fixCalls = [];
const fixer = {
	replaceTextRange(range, replacement) {
		fixCalls.push({ range, replacement });

		return { range, replacement };
	}
};
const sourceCode = {
	lines: [
		'\tfoo();'
	]
};
const previousToken = {
	loc: {
		start: { line: 1, column: 0 }
	}
	, range: [0, 7]
};
const closingTokens = [
	{ value: ')', range: [8, 9] }
	, { value: ']', range: [9, 10] }
	, { value: '}', range: [10, 11] }
];
const result = buildMixedClosingRailFix(sourceCode, fixer, previousToken, closingTokens, ['\t', '\t', '']);

assert.deepEqual(result, {
	range: [7, 11]
	, replacement: ')]\n}'
});
assert.deepEqual(fixCalls, [
	{
		range: [7, 11]
		, replacement: ')]\n}'
	}
]);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, loc: {
		start: { line: 3 }
	}
	, parent: {
		type: 'ForStatement'
		, init: null
		, test: {
			loc: { end: { line: 2 } }
		}
		, update: null
		, loc: { start: { line: 1 } }
	}
}), true);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, loc: {
		start: { line: 3 }
	}
	, parent: {
		type: 'ForInStatement'
		, right: {
			loc: { end: { line: 2 } }
		}
		, loc: { start: { line: 1 } }
	}
}), true);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, loc: {
		start: { line: 3 }
	}
	, parent: {
		type: 'ForOfStatement'
		, right: {
			loc: { end: { line: 2 } }
		}
		, loc: { start: { line: 1 } }
	}
}), true);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, loc: {
		start: { line: 3 }
	}
	, parent: {
		type: 'ForStatement'
		, init: {
			loc: { end: { line: 2 } }
		}
		, test: null
		, update: null
		, loc: { start: { line: 1 } }
	}
}), true);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, loc: {
		start: { line: 3 }
	}
	, parent: {
		type: 'ForStatement'
		, init: null
		, test: null
		, update: {
			loc: { end: { line: 2 } }
		}
		, loc: { start: { line: 1 } }
	}
}), true);

assert.equal(hasMultilineControlHead({
	type: 'SwitchStatement'
	, loc: {
		start: { line: 3 }
	}
	, discriminant: {
		loc: { end: { line: 2 } }
	}
}), true);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, loc: {
		start: { line: 3 }
	}
	, parent: {
		type: 'WithStatement'
		, object: {
			loc: { end: { line: 2 } }
		}
		, loc: { start: { line: 1 } }
	}
}), true);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, parent: {
		type: 'DoWhileStatement'
	}
}), false);
