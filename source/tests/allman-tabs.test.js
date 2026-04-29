import { RuleTester } from 'eslint';

import rule from '../rules/allman-tabs.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022,
		sourceType: 'module',
	},
});

ruleTester.run('sm-no-saccade-style/allman-tabs', rule, {
	valid: [
		"if(foo)\n{\n\tbar();\n}",
		"const short\t    = 1;\nconst longerName = 2;",
		"promise.then(value => {\n\tbar(value);\n});",
	],
	invalid: [
		{
			code: "if(foo) {\n  bar();\n}",
			output: "if(foo)\n{\n\tbar();\n}",
			errors: 2,
		},
		{
			code: "if(foo)\n{\n \tbar();\n}",
			output: "if(foo)\n{\n\tbar();\n}",
			errors: [
				{ messageId: 'wrongIndentation' },
				{ messageId: 'mixedSpacesAndTabs' },
			],
		},
		{
			code: "promise.then(value =>\n{\n\tbar(value);\n});",
			output: "promise.then(value => {\n\tbar(value);\n});",
			errors: [{ messageId: 'unexpectedArrowAllmanOpen' }],
		},
	],
});
