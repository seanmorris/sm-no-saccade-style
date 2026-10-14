import assert from 'node:assert/strict';
import { ESLint } from 'eslint';
import plugin from 'sm-no-saccade-style';

assert.ok(plugin.rules['max-line-length']);
assert.ok(plugin.rules['parameter-layout']);

const eslint = new ESLint({
	overrideConfigFile: true
	, fix: true
	, overrideConfig: plugin.configs.recommended
});

for(const filePath of ['smoke.js', 'smoke.ts'])
for(const statement of [
	'const ok = first === processValue(firstLongVariableName, secondLongVariableName, thirdLongVariableName, fourthLongVariableName);\n'
	, 'const ok = Boolean(veryLongIdentifierUsedAsFirstValue) ? transform(anotherLongIdentifierUsedAsSecondValue) : null;\n'
]){
	const input = filePath.endsWith('.ts')
		? statement.replace('const ok =', 'const ok: boolean =')
		: statement;

	const [first] = await eslint.lintText(input, {filePath});
	assert.equal(first.errorCount, 0, JSON.stringify(first.messages));
	const output = first.output ?? input;
	assert.notEqual(output, input, 'The installed recommended preset must format the long line.');
	const [second] = await eslint.lintText(output, {filePath});
	assert.equal(second.errorCount, 0, JSON.stringify(second.messages));
	assert.equal(second.output ?? output, output);
}
