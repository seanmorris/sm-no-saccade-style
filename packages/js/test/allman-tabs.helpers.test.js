import assert from 'node:assert/strict';

import {
	collectChainedCallbackBodyRanges,
	hasMultilineControlHead
} from '../source/rules/allman-tabs.js';

assert.deepEqual(collectChainedCallbackBodyRanges(null), []);

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
}), true
);

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
}), true
);

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
}), true
);

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
}), true
);

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
}), true
);

assert.equal(hasMultilineControlHead({
	type: 'SwitchStatement'
	, loc: {
		start: { line: 3 }
	}
	, discriminant: {
		loc: { end: { line: 2 } }
	}
}), true
);

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
}), true
);

assert.equal(hasMultilineControlHead({
	type: 'BlockStatement'
	, parent: {
		type: 'DoWhileStatement'
	}
}), false
);
