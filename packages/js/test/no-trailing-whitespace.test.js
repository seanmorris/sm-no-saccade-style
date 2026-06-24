import { RuleTester } from 'eslint';

import rule from '../source/rules/no-trailing-whitespace.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022
		, sourceType: 'module'
	}
});

ruleTester.run('sm-no-saccade-style/no-trailing-whitespace', rule, {
	valid: [
		"const x = 1;\n"
		, "const x = `line with spaces   \nnext`;\n"
	]
	, invalid: [
		{
			code: "const x = 1;   \n"
			, output: "const x = 1;\n"
			, errors: [{ messageId: 'unexpectedTrailingWhitespace' }]
		}
		, {
			code: "const x = `line with spaces   \nnext`;   \n"
			, output: "const x = `line with spaces   \nnext`;\n"
			, errors: [{ messageId: 'unexpectedTrailingWhitespace' }]
		}
		, {
			code: "/**   \n * Docs stay here.   \n */   \nconst x = 1;\n"
			, output: "/**\n * Docs stay here.\n */\nconst x = 1;\n"
			, errors: [
				{ messageId: 'unexpectedTrailingWhitespace' }
				, { messageId: 'unexpectedTrailingWhitespace' }
				, { messageId: 'unexpectedTrailingWhitespace' }
			]
		}
	]
});
