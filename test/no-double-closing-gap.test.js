import { RuleTester } from 'eslint';

import rule from '../source/rules/no-double-closing-gap.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022
		, sourceType: 'module'
	}
});

ruleTester.run('sm-no-saccade-style/no-double-closing-gap', rule, {
	valid: [
		"call(\n\twrap(\n\t\tvalue\n\t)\n);"
		, "const x = {\n\tlist: [\n\t\tvalue\n\t]\n};"
		, "const x = [\n\t[\n\t\tvalue\n\t]\n];"
		, "const x = [\n\t[\n\t\tvalue\n\t]\n\n\t[\n\t\tother\n\t]\n];"
		, "call(\n\twrap(\n\t\tvalue\n\t)\n\t// spacer\n\n);"
	]
	, invalid: [
		{
			code: "call(\n\twrap(\n\t\tvalue\n\t)\n\n);"
			, output: "call(\n\twrap(\n\t\tvalue\n\t)\n);"
			, errors: [{ messageId: 'unexpectedDelimiterGap' }]
		}
		, {
			code: "const x = {\n\tlist: [\n\t\tvalue\n\t]\n\n};"
			, output: "const x = {\n\tlist: [\n\t\tvalue\n\t]\n};"
			, errors: [{ messageId: 'unexpectedDelimiterGap' }]
		}
		, {
			code: "const x = [\n\n\t[\n\t\tvalue\n\t]\n];"
			, output: "const x = [\n\t[\n\t\tvalue\n\t]\n];"
			, errors: [{ messageId: 'unexpectedDelimiterGap' }]
		}
		, {
			code: "const x = [\n\t[\n\t\tvalue\n\t]\n\n\n\t[\n\t\tother\n\t]\n];"
			, output: "const x = [\n\t[\n\t\tvalue\n\t]\n\n\t[\n\t\tother\n\t]\n];"
			, errors: [{ messageId: 'unexpectedDelimiterGap' }]
		}
	]
});
