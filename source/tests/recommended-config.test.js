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

const [jsResult] = await eslint.lintText('const x = 1;', { filePath: 'fixture.js' });
const [tsResult] = await eslint.lintText('const x: number = 1;', { filePath: 'fixture.ts' });

assert.equal(jsResult.messages.length, 1);
assert.equal(jsResult.messages[0].ruleId, '@stylistic/eol-last');

assert.equal(tsResult.messages.length, 1);
assert.equal(tsResult.messages[0].ruleId, '@stylistic/eol-last');
