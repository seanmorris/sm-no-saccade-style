import assert from 'node:assert/strict';

import { ESLint } from 'eslint';

import smNoSaccadeStyle from '../source/index.js';

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
const [semiResult] = await eslint.lintText('const x = 1\n', { filePath: 'fixture-semi.js' });
const [controlChainResult] = await eslint.lintText(`if(graphs)\nfor(const graph of graphs)\n{\n\tgraph.delete(entity);\n}\n`, { filePath: 'fixture-control-chain.js' });
const [singleLineControlResult] = await eslint.lintText(`if(foo)\n\tbar();\n`, { filePath: 'fixture-single-control.js' });
const [fixedJsdocResult] = await fixingEslint.lintText(`/**\n * Keeps docs.\n */\nfunction example() {\n\treturn {\n\t\tvalue: 1\n\t};\n}\n`, {
	filePath: 'fixture-fix.js'
});

assert.equal(jsResult.messages.length, 1);
assert.equal(jsResult.messages[0].ruleId, '@stylistic/eol-last');

assert.equal(tsResult.messages.length, 1);
assert.equal(tsResult.messages[0].ruleId, '@stylistic/eol-last');

assert.equal(semiResult.messages.length, 1);
assert.equal(semiResult.messages[0].ruleId, '@stylistic/semi');

assert.equal(controlChainResult.messages.length, 0);
assert.equal(singleLineControlResult.messages.length, 0);

assert.match(fixedJsdocResult.output, /\/\*\*\n \* Keeps docs\.\n \*\//u);
assert.match(fixedJsdocResult.output, /function example\(\)\n\{/u);
