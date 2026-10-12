import assert from 'node:assert/strict';
import { ESLint } from 'eslint';
import { parse } from 'espree';
import plugin from '../source/index.js';

const eslint = new ESLint({
	overrideConfigFile: true
	, overrideConfig: plugin.configs.recommended
	, fix: true
});

async function format(input)
{
	const [result] = await eslint.lintText(input, { filePath: 'layout.js' });
	assert.equal(result.errorCount, 0, JSON.stringify(result.messages));
	const output = result.output ?? input;
	const [again] = await eslint.lintText(output, { filePath: 'layout.js' });
	assert.equal(again.output ?? output, output, 'Formatting must be idempotent.');

	const tokens = code => parse(code, { ecmaVersion: 'latest'
		, sourceType: 'module'
		, tokens: true })
		.tokens.map(({ type, value }) => [type, value]);
	assert.deepEqual(tokens(output), tokens(input), 'Layout must preserve every executable token.');

	const ast = code => JSON.parse(JSON.stringify(parse(code, {
		ecmaVersion: 'latest', sourceType: 'module'
	}), (key, value) => ['start', 'end', 'loc', 'range', 'raw'].includes(key) ? undefined : value));
	assert.deepEqual(ast(output), ast(input), 'Layout must preserve the parsed program.');
	return output;
}

const create = await format(`class World
{
	create(...components)
	{ const id = this.next++;
	this.entities[id] = [];
	for(const c of components)
	{ this.add(id,c); } return id; }
}
`);
assert.equal(create, `class World
{

	create(...components)
	{
		const id = this.next++;
		this.entities[id] = [];
		for(const c of components)
		{
			this.add(id,c);
		}
		return id;
	}
}
`);

const nested = await format(`for(const item of items)
{ if(item.enabled)
{
	remove(item);
	for(const entry of item.entries)
	{ remove(entry); if(empty(entry)) { clear(entry); } }
	emit(item);
} }
`);
assert.match(nested, /for\(const item of items\)\n\{\n\tif\(item.enabled\)\n\t\{/u);
assert.doesNotMatch(nested, /\{ (?:if|for|remove)|; \}|\} \}/u);

assert.equal(await format('if(ready){ run(); }\n'), 'if(ready)\n{\n\trun();\n}\n');
assert.equal(await format('try{ run(); } catch(error){ fail(error); }\n'),
	'try{ run(); } catch(error){ fail(error); }\n');
assert.equal(await format('function short(){ return 1; }\n'), 'function short(){ return 1; }\n');

const documented = await format(`const value = 1;
/** Keeps this documentation attached. */
export async function work() { return value; }
class Worker {
/** Describes the method. */
run() { return value; }
}
`);
assert.match(documented, /value = 1;\n\n\/\*\*/u);
assert.match(documented, /\*\/\nexport async function/u);
assert.match(documented, /\n\nclass Worker/u);
assert.match(documented, /\{\n\n\t\/\*\* Describes/u);

const long = await format(`function long(){ ${'work(); '.repeat(14)}}\n`);
assert.match(long, /function long\(\)\n\{\n\twork\(\);\n/u);
assert.doesNotMatch(long, /; work/u);

const multiHead = await format('function work(\n\tvalue\n){ return value; }\n');
assert.match(multiHead, /\)\{\n\treturn value;\n\}/u);

// The complete try/catch statement is multiline, even if each body is short.
const multiTry = await format('try{ run(); }\ncatch(error){ fail(error); }\n');
assert.match(multiTry, /try\n\{\n\trun\(\);\n\}/u);

const stacked = 'for(const item of items)\nif(item.enabled)\n{\n\trun(item);\n}\n';
assert.equal(await format(stacked), stacked);

await format('if(ready){ let value = 1; use(value); } else { fallback(); }\n');
await format('async function work(){ for await(const item of items){ use(item); } }\n');
await format('const text = `line one\n  line two`;\nif(ready){ /* keep */ use(text); }\n');

const checking = new ESLint({ overrideConfigFile: true
	, overrideConfig: plugin.configs
	.recommended });
const [run] = await checking.lintText('work();\n'.repeat(11), { filePath: 'runs.js' });
assert.equal(run.warningCount, 1);
assert.equal(run.messages[0].ruleId, 'sm-no-saccade-style/max-unseparated-lines');
assert.match(run.messages[0].message, /semantic/iu);
const [ten] = await checking.lintText('work();\n'.repeat(10), { filePath: 'runs.js' });
assert.equal(ten.warningCount, 0);
const [split] = await checking.lintText('work();\n'.repeat(6) + '\n' + 'work();\n'.repeat(6),
	{ filePath: 'runs.js' });
assert.equal(split.warningCount, 0);
const [wide] = await checking.lintText('call(' + 'a'.repeat(95) + ');\n', { filePath: 'wide.js' });
assert.ok(wide.messages.some(message => message.ruleId === 'sm-no-saccade-style/max-line-length'));

const object = await format(`const config = {
	schema: [{ type: 'object', properties: { max: { type: 'integer', minimum: 1 } },
		additionalProperties: false }],
	/** Keep the method comment. */
	create() { return 1; },
	other() { return 2; }
};
`);
assert.equal((object.match(/schema/gu) ?? []).length, 1);
assert.match(object, /\n\n\t, other/u);

const [ts] = await eslint.lintText(`const value: number = 1;
/** Keep documentation above decorators. */
@sealed
export class Example {
@method
run(): number { return value; }
}
`, {filePath: 'example.ts'});
assert.equal(ts.errorCount, 0, JSON.stringify(ts.messages));
assert.match(ts.output, /value: number = 1;\n\n\/\*\*/u);
assert.match(ts.output, /\{\n\n\t@method\n\trun/u);

const [docsOnly] = await checking.lintText('/*\n' + 'comment\n'.repeat(15) + '*/\n', {filePath:
	'docs.js'});
assert.equal(docsOnly.warningCount, 0);
const [literal] = await checking.lintText('const text = `\n' + 'literal\n'.repeat(15) + '`;\n'
	, {filePath: 'literal.js'});
assert.equal(literal.warningCount, 0);

const warningsInput = 'work();\n'.repeat(11);
const [warningFix] = await eslint.lintText(warningsInput, {filePath: 'runs.js'});
assert.equal(warningFix.output ?? warningsInput, warningsInput);
