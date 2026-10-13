import assert from 'node:assert/strict';
import { ESLint } from 'eslint';
import { parse } from 'espree';
import tsParser from '@typescript-eslint/parser';
import plugin from '../source/index.js';

const eslint = new ESLint({
	overrideConfigFile: true, overrideConfig: plugin.configs.recommended, fix: true
});

async function format(input, filePath = 'blocks.js')
{
	const [result] = await eslint.lintText(input, { filePath });
	assert.equal(result.errorCount, 0, JSON.stringify(result.messages));
	const output = result.output ?? input;
	const [again] = await eslint.lintText(output, { filePath });
	assert.equal(again.output ?? output, output, 'Block layout must converge.');

	const parser = filePath.endsWith('.ts') ? tsParser.parse : parse;

	const ast = text => JSON.parse(JSON.stringify(parser(text, {
		ecmaVersion: 'latest', sourceType: 'module'
	}), (key, value) => ['start', 'end', 'loc', 'range', 'raw'].includes(key) ? undefined : value
	));
	assert.deepEqual(ast(output), ast(input), 'Block layout must preserve the program.');
	return output;
}

for(const block of [
	'for(const item of items)\n{\n\tstep(item);\n}'
	, 'for(let i = 0; i < 3; i++)\n{\n\tstep(i);\n}'
	, 'while(ready)\n{\n\tstep();\n}'
	, 'do\n{\n\tstep();\n}\nwhile(ready);'
	, 'switch(value)\n{\n\tdefault:\n\t\tbreak;\n}'
	, 'try\n{\n\tstep();\n}\ncatch(error)\n{\n\trecover();\n}\nfinally\n{\n\tcleanup();\n}'
	, 'function work()\n{\n\tstep();\n}'
	, 'class Worker\n{\n\twork()\n\t{\n\t\tstep();\n\t}\n}'
	, 'const work = () => {\n\tstep();\n};'
	, 'consume(() => {\n\tstep();\n});'
	, '{\n\tstep();\n}'
	, 'function short(){ return 1; }'
]){
	const output = await format(`${block}\nafter();\n`);
	assert.match(output, /\n\nafter\(\);\n$/u, block);
}

for(const closes of ['}}', '} }', '}}}'])
{
	const extra = closes === '}}}' ? '\t\twhile(ready)\n\t\t{\n' : '';
	const indent = closes === '}}}' ? '\t\t' : '\t';

	const output = await format(`for(const item of items)
{
	if(item)
	{
${extra}${indent}\tstep(item);
${indent}${closes} after();
`
	);
	assert.ok(output.includes(`${indent}${closes}\n\nafter();\n`), output);
}

const nested = await format(`function work()
{
	while(ready)
	{
		if(check())
		{
			step();
		}}
}
after();
`
);
assert.match(nested, /\t\t\}\}\n\}\n\nafter/u);

const branches = await format(`if(ready)
{
	while(pending)
	{
		step();
	}} else
{
	recover();
}
try
{
	while(pending)
	{
		step();
	}}
catch(error)
{
	recover();
}
finally
{
	cleanup();
}
do
{
	while(pending)
	{
		step();
	}}
while(ready);
after();
`
);
assert.match(branches, /\t\}\} else/u);
assert.match(branches, /\t\}\}\ncatch/u);
assert.match(branches, /\}\nfinally/u);
assert.match(branches, /\t\}\}\nwhile\(ready\);\n\nafter/u);
assert.doesNotMatch(branches, /\}\n\n(?:else|catch|finally|while)/u);

const comments = await format(`while(ready)
{
	step();
} // Done iterating.
// Describe the next statement.
after();
`
);
assert.match(comments, /\} \/\/ Done iterating\.\n\n\/\/ Describe/u);
assert.match(comments, /Describe the next statement\.\nafter/u);

for(const comment of ['/* Done. */', '/* Done. */ /* Also done. */', '/* Done.\n\nStill done. */'])
{
	const output = await format(`function work()
{
	while(ready)
	{
		step();
	} ${comment} after();
}
`
	);
	assert.ok(output.includes(`} ${comment}\n\n\tafter();`), output);
}

const staticBlock = await format(`class Worker
{
	static
	{
		if(ready)
		{
			step();
		}}
	value = 1;
}
`
);
assert.match(staticBlock, /\t\t\}\}\n\n\tvalue/u);

const namespace = await format(`namespace Game
{
	export class Worker
	{
		run(): void
		{
			step();
		}}}
after();
`, 'blocks.ts'
);
assert.match(namespace, /\t\t\}\}\}\n\nafter/u);

const members = await format(`class Worker
{
	work()
	{
		step();
	}
	value = 1;
}
`
);
assert.match(members, /\t\}\n\n\tvalue/u);

const data = await format('const value = { text: "}} next();" };\nafter();\n');
assert.doesNotMatch(data, /;\n\nafter/u);
assert.ok(data.includes('"}} next();"'));
