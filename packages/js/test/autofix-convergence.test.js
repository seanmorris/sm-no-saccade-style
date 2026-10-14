import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import { ESLint } from 'eslint';
import { parse } from 'espree';
import tsParser from '@typescript-eslint/parser';
import plugin from '../source/index.js';

const argumentsText = [
	'firstLongVariableName'
	, 'secondLongVariableName'
	, 'thirdLongVariableName'
	, 'fourthLongVariableName'
].join(', ');

const cases = [
	`const ok = first === processValue(${argumentsText});`
	, `const ok = first\n\t=== processValue(${argumentsText});`
	, `const label = 'prefix' + processValue(${argumentsText});`
	, `const label = 'prefix'\n\t+ processValue(${argumentsText});`
	, `const ok = first\n\t&& processValue(${argumentsText});`
	, 'const ok = Boolean(veryLongIdentifierUsedAsFirstValue) ? transform(anotherLongIdentifierUsedAsSecondValue) : null;'
	, `const ok = Boolean(veryLongIdentifierUsedAsFirstValue) ? transform(${argumentsText}) : null;`
	, `const ok = first ? second ? transform(${argumentsText}) : other : null;`
	, `const ok = Boolean(veryLongIdentifierUsedAsFirstValue) ? transform(/* Preserve this argument. */ ${argumentsText}) : null;`
	, `const ok = first\n\t? transform(${argumentsText})\n\t: null;`
	, `consume(first ? transform(${argumentsText}) : null);`
	, `const ok = first === processValue(/* Keep the note. */ ${argumentsText});`
];

const warnings = [];

function warning(event)
{
	if(event.name === 'ESLintCircularFixesWarning') warnings.push(event.message);
}

process.on('warning', warning);

try
{
	for(const max of [100, 120])
	for(const filePath of ['convergence.js', 'convergence.ts'])
	{
		const eslint = new ESLint({
			overrideConfigFile: true
			, fix: true
			, overrideConfig: [
				...plugin.configs.recommended
				, {
					rules: {
						'sm-no-saccade-style/max-line-length': ['error', {max}]
						, 'sm-no-saccade-style/allman-tabs': ['error', {maxLineLength: max}]
						, 'sm-no-saccade-style/parameter-layout': ['error', {max}]
						, 'sm-no-saccade-style/object-layout': ['error', {max}]
					}
				}
			]
		});

		const parser = filePath.endsWith('.ts') ? tsParser.parse : parse;

		const program = text => JSON.parse(JSON.stringify(parser(text, {
			ecmaVersion: 'latest'
			, sourceType: 'module'
			, tokens: true
			, comment: true
		}), (key, value) => ['start', 'end', 'loc', 'range'].includes(key) ? undefined : value
		));

		for(const statement of cases)
		{
			const input = (filePath.endsWith('.ts')
				? statement.replace('const ok =', 'const ok: boolean =')
				: statement
			) + '\n';

			const [first] = await eslint.lintText(input, {filePath});
			const output = first.output ?? input;
			const label = `${filePath}, ${max} columns: ${statement}`;
			assert.equal(first.errorCount, 0, label + '\n' + JSON.stringify(first.messages));
			const [second] = await eslint.lintText(output, {filePath});
			assert.equal(second.errorCount, 0, label);
			assert.equal(second.output ?? output, output, label + '\nAutofix must converge.');
			assert.deepEqual(program(output), program(input), label + '\nPreserve the program.');
			assert.ok(!/\n[\t ]*\n[\t ]*\n/u.test(output), label + '\nDo not grow blank lines.');
		}
	}

	await setImmediate();
	assert.deepEqual(warnings, [], 'No circular autofixes are permitted.');
}
finally
{
	process.off('warning', warning);
}
