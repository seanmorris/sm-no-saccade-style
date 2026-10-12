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
		, tokens: true
	})
		.tokens.map(({ type, value }) => [type, value]);
	assert.deepEqual(tokens(output), tokens(input), 'Layout must preserve every executable token.');

	const ast = code => JSON.parse(JSON.stringify(parse(code, {
		ecmaVersion: 'latest', sourceType: 'module'
	}), (key, value) => ['start', 'end', 'loc', 'range', 'raw'].includes(key) ? undefined : value
	));
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
`
);

assert.equal(create, `class World
{

	create(...components)
	{
		const id = this.next++;
		this.entities[id] = [];

		for(const c of components)
		{
			this.add(id, c);
		}
		return id;
	}
}
`
);

const nested = await format(`for(const item of items)
{ if(item.enabled)
{
	remove(item);
	for(const entry of item.entries)
	{ remove(entry); if(empty(entry)) { clear(entry); } }
	emit(item);
} }
`
);
assert.match(nested, /for\(const item of items\)\n\{\n\tif\(item.enabled\)\n\t\{/u);
assert.doesNotMatch(nested, /\{ (?:if|for|remove)|; \}|\} \}/u);

assert.equal(await format('if(ready){ run(); }\n'), 'if(ready)\n{\n\trun();\n}\n');

assert.equal(
	await format('try{ run(); } catch(error){ fail(error); }\n')
	, 'try{ run(); } catch(error){ fail(error); }\n'
);
assert.equal(await format('function short(){ return 1; }\n'), 'function short(){ return 1; }\n');

const documented = await format(`const value = 1;
/** Keeps this documentation attached. */
export async function work() { return value; }
class Worker {
/** Describes the method. */
run() { return value; }
}
`
);
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
	.recommended
});
const [run] = await checking.lintText('work();\n'.repeat(11), { filePath: 'runs.js' });
assert.equal(run.warningCount, 1);
assert.equal(run.messages[0].ruleId, 'sm-no-saccade-style/max-unseparated-lines');
assert.match(run.messages[0].message, /semantic/iu);
const [ten] = await checking.lintText('work();\n'.repeat(10), { filePath: 'runs.js' });
assert.equal(ten.warningCount, 0);

const [split] = await checking.lintText('work();\n'.repeat(6) + '\n' + 'work();\n'.repeat(6),
	{ filePath: 'runs.js' }
);
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
`
);
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
	'docs.js'
});
assert.equal(docsOnly.warningCount, 0);

const [literal] = await checking.lintText('const text = `\n' + 'literal\n'.repeat(15) + '`;\n'
	, {filePath: 'literal.js'}
);
assert.equal(literal.warningCount, 0);

const warningsInput = 'work();\n'.repeat(11);
const [warningFix] = await eslint.lintText(warningsInput, {filePath: 'runs.js'});
assert.equal(warningFix.output ?? warningsInput, warningsInput);

const separators = await format(`function combine(first,second = pick(1,2),...rest){ return first; }
class Worker {
run(first,second){ return combine(first,second); }
}
const callback = (first,second) => combine(first,second);
const worker = new Worker(1,2);
worker.run?.(1,/* keep the argument comment */2);
combine((first),(second));
combine('literal,;','unchanged');
combine(first,   second);
`
);
assert.match(separators, /combine\(first, second = pick\(1, 2\), \.\.\.rest\)/u);
assert.match(separators, /run\(first, second\)/u);
assert.match(separators, /\(first, second\) => combine\(first, second\)/u);
assert.match(separators, /new Worker\(1, 2\)/u);
assert.match(separators, /run\?\.\(1, \/\* keep the argument comment \*\/2\)/u);
assert.match(separators, /combine\(\(first\), \(second\)\)/u);
assert.match(separators, /combine\('literal,;', 'unchanged'\)/u);
assert.doesNotMatch(separators, /, {2}/u);

const loops = await format(`for(let i = 0;i < 3;i++){ use(i); }
for(;;){ break; }
for(;ready;){ break; }
for(let i = (() => { tick(); return 0; })();i < 3;i++){ use(i); }
for(
let i = 0;
i < 3;
i++
){ use(i); }
for(let i = 0;/* keep the loop comment */i < 3;i++){ use(i); }
`
);
assert.match(loops, /for\(let i = 0; i < 3; i\+\+\)/u);
assert.match(loops, /for\(; ;\)/u);
assert.match(loops, /for\(; ready;\)/u);
assert.match(loops, /\}\)\(\); i < 3; i\+\+\)/u);
assert.match(loops, /let i = 0;\n\ti < 3;\n\ti\+\+/u);
assert.match(loops, /0; \/\* keep the loop comment \*\/i < 3; i\+\+\)/u);

const leadingArguments = `function combine(
	first
	, second
){
	return run(first
		, second
	);
}
`;
assert.equal(await format(leadingArguments), leadingArguments);

const typedInput = `declare function run(first: number,second: number): void;
interface Worker {
run(first: number,second: number): void;
}
type Callback = (first: number,second: number) => void;
run<number>(1,2);
`;
const [typedSpacing] = await eslint.lintText(typedInput, { filePath: 'spacing.ts' });
assert.equal(typedSpacing.errorCount, 0, JSON.stringify(typedSpacing.messages));
assert.equal((typedSpacing.output.match(/first: number, second: number/gu) ?? []).length, 3);
assert.match(typedSpacing.output, /run<number>\(1, 2\)/u);
const [typedAgain] = await eslint.lintText(typedSpacing.output, { filePath: 'spacing.ts' });
assert.equal(typedAgain.output ?? typedSpacing.output, typedSpacing.output);

const [indentedLiteral] = await checking.lintText(`'${'x'.repeat(101)}';\n`, {
	filePath: 'literal.js'
});
assert.equal(indentedLiteral.errorCount, 0);

const spawning = `class Spawner
{

	spawn(map, registry)
	{
		const entities = [];
		for(const layer of map.layers)
		{
			for(const object of layer.objects)
			{
				const className = object.class ?? object.type ?? '';
				if(!(this.factories[className]))
				{
					continue;
				}
				object.x = (object.x ?? 0) + map.x + layer.offsetX;
				object.y = (object.y ?? 0) + map.y + layer.offsetY;
				object.properties = Loader.properties(object.properties ?? []);
				entities[object['id']] = registry.create(...(this.factories[className])(object
					, map));
			}
		}
		return entities;
	}
}
`;

const spawnExpected = spawning
	.replace('const entities = [];\n', 'const entities = [];\n\n')
	.replace(
		"const className = object.class ?? object.type ?? '';\n"
		, "const className = object.class ?? object.type ?? '';\n\n"
	)
	.replace('\t\t\t\t}\n\t\t\t\tobject.x', '\t\t\t\t}\n\n\t\t\t\tobject.x')
	.replace(`\t\t\t\tentities[object['id']] = registry.create(...(this.factories[className])(object
					, map));`, `
				entities[object['id']] = registry.create(
					...(this.factories[className])(object, map)
				);`
	);
assert.equal(await format(spawning), spawnExpected);

assert.equal(await format(`const result = outer(
	inner(
		[
			true]));
`
), `const result = outer(
	inner(
		[
			true
		]
	)
);
`
);

const grouped = await format(`function work(items)
{
	const results = [];
	// Filter each item.
	for(const item of items)
	{
		const ready = item !== null;
		/** Keep this with the guard. */
		if(!ready)
		{
			continue;
		}
		else if(item === false)
		{
			throw new Error('literal },);');
		}
		// Keep processing details.
		results.push(item);
	}
	return results;
}
`
);
assert.match(grouped, /results = \[\];\n\n\t\/\/ Filter/u);
assert.match(grouped, /ready = item !== null;\n\n\t\t\/\*\* Keep/u);
assert.match(grouped, /\}\n\t\telse if/u);
assert.match(grouped, /\}\n\n\t\t\/\/ Keep processing/u);
assert.match(grouped, /\t\}\n\treturn results;/u);
await format('const result = outer(\n\tinner(\n\t\t[true] // keep\n\t));\n');

const constructed = await format(
	'const someLongResultName = new ExampleFactory(transform(firstLongParameterName, secondLongParameterName));\n'
);
assert.match(constructed, /new ExampleFactory\(\n\ttransform\(/u);
assert.match(constructed, /secondLongParameterName\)\n\);/u);
