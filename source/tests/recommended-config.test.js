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
				,
			}
			,
		}
		,
	]
});

const [result] = await eslint.lintText('const x = 1;', { filePath: 'fixture.js' });

assert.equal(result.messages.length, 1);
assert.equal(result.messages[0].ruleId, '@stylistic/eol-last');
