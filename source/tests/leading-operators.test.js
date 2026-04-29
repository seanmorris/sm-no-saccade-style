import { RuleTester } from 'eslint';

import rule from '../rules/leading-operators.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022
		, sourceType: 'module'
	}
});

ruleTester.run('sm-no-saccade-style/leading-operators', rule, {
	valid: [
		`const value = source
	|| fallback
	|| defaultValue;`
		, `const output = condition
	? good
	: bad;`
	]
	, invalid: [
		{
			code: `const ready = loaded &&
	validated;`
			, output: `const ready = loaded
	&& validated;`
			, errors: [{ messageId: 'operatorAtBeginning' }]
		}
	]
});
