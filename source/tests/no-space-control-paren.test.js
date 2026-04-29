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
		, `try {
	work();
} catch(error) {
	handle(error);
}`
		, `do {
	work();
} while(done);`
		, `switch(value) {
	case 1:
		break;
}`
		, `for(const key in value) {
	use(key);
}`
		, `for(const item of value) {
	use(item);
}`
		, `if
(foo) {
	bar();
}`
		, `try {
	work();
} catch {
	recover();
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
		, {
			code: `do {
	work();
} while (done);`
			, output: `do {
	work();
} while(done);`
			, errors: [{ messageId: 'unexpectedSpace' }]
		}
		, {
			code: `for (const key in value) {
	use(key);
}`
			, output: `for(const key in value) {
	use(key);
}`
			, errors: [{ messageId: 'unexpectedSpace' }]
		}
		, {
			code: `for (const item of value) {
	use(item);
}`
			, output: `for(const item of value) {
	use(item);
}`
			, errors: [{ messageId: 'unexpectedSpace' }]
		}
		, {
			code: `switch (value) {
case 1:
	break;
}`
			, output: `switch(value) {
case 1:
	break;
}`
			, errors: [{ messageId: 'unexpectedSpace' }]
		}
		, {
			code: `try {
	work();
} catch (error) {
	handle(error);
}`
			, output: `try {
	work();
} catch(error) {
	handle(error);
}`
			, errors: [{ messageId: 'unexpectedSpace' }]
		}
	]
});
