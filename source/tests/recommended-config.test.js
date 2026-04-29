import assert from 'node:assert/strict';

import { ESLint } from 'eslint';

import smNoSaccadeStyle from '../index.js';

const eslint = new ESLint({
	overrideConfigFile: true
	, overrideConfig: [
		...smNoSaccadeStyle.configs.recommended
		, {
			files: ['**/*.js']
			, languageOptions: {
				ecmaVersion: 'latest'
				, sourceType: 'module'
			}
		}
	]
});

const fixingEslint = new ESLint({
	overrideConfigFile: true
	, fix: true
	, overrideConfig: [
		...smNoSaccadeStyle.configs.recommended
		, {
			files: ['**/*.js']
			, languageOptions: {
				ecmaVersion: 'latest'
				, sourceType: 'module'
			}
		}
	]
});

const [jsResult] = await eslint.lintText('const x = 1;', { filePath: 'fixture.js' });
const [tsResult] = await eslint.lintText('const x: number = 1;', { filePath: 'fixture.ts' });
const [fixedJsdocResult] = await fixingEslint.lintText(`/**\n * Keeps docs.\n */\nfunction example() {\n\treturn {\n\t\tvalue: 1\n\t};\n}\n`, {
	filePath: 'fixture-fix.js'
});

assert.equal(jsResult.messages.length, 1);
assert.equal(jsResult.messages[0].ruleId, '@stylistic/eol-last');

assert.equal(tsResult.messages.length, 1);
assert.equal(tsResult.messages[0].ruleId, '@stylistic/eol-last');

assert.match(fixedJsdocResult.output, /\/\*\*\n \* Keeps docs\.\n \*\//u);
assert.match(fixedJsdocResult.output, /function example\(\)\n\{/u);
