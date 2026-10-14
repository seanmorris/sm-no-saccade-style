import assert from 'node:assert/strict';
import { ESLint } from 'eslint';
import { parse } from 'espree';
import tsParser from '@typescript-eslint/parser';
import plugin from '../source/index.js';

const eslint = new ESLint({
	overrideConfigFile: true
	, fix: true
	, overrideConfig: [
		...plugin.configs.recommended
		, {
			rules: {
				'sm-no-saccade-style/max-line-length': ['error', {max: 120}]
				, 'sm-no-saccade-style/allman-tabs': ['error', {maxLineLength: 120}]
				, 'sm-no-saccade-style/parameter-layout': ['error', {max: 120}]
				, 'sm-no-saccade-style/object-layout': ['error', {max: 120}]
			}
		}
	]
});

async function format(input, filePath = 'expressions.js')
{
	const [result] = await eslint.lintText(input, {filePath});
	assert.equal(result.errorCount, 0, JSON.stringify(result.messages));
	const output = result.output ?? input;
	const [again] = await eslint.lintText(output, {filePath});
	assert.equal(again.errorCount, 0, JSON.stringify(again.messages));
	assert.equal(again.output ?? output, output, 'Formatting must converge.');
	const parser = filePath.endsWith('.ts') ? tsParser.parse : parse;

	const program = text => JSON.parse(JSON.stringify(parser(text, {
		ecmaVersion: 'latest'
		, sourceType: 'module'
		, tokens: true
		, comment: true
	}), (key, value) => ['start', 'end', 'loc', 'range'].includes(key) ? undefined : value
	));

	assert.deepEqual(
		program(output)
		, program(input)
		, 'Preserve program, tokens, comments and literals.'
	);

	return output;
}

function nested(statement)
{
	return `function setup()
{
	if(first)
	{
		if(second)
		{
			if(third)
			{
${statement.split('\n').map(line => '\t\t\t\t' + line).join('\n')}
			}
		}
	}
}
`;
}

assert.equal(
	await format('const pad={id: name\n\t, index: 0, connected: true\n};\n')
	, 'const pad = {\n\tid: name\n\t, index: 0\n\t, connected: true\n};\n'
);

assert.equal(
	await format('const pad={id: name, index: 0};\n')
	, 'const pad = {id: name, index: 0};\n'
);

assert.equal(await format('const {id,\nindex}=pad;\n'),
	'const {\n\tid\n\t, index\n} = pad;\n'
);

for(const argument of [
	"Object.assign(new Event('gamepaddisconnected'), {gamepad: menuPads[0]})"
	, "Object.assign(new Event(\n\t'gamepaddisconnected'\n), {gamepad: menuPads[0]})"
]){
	const output = await format(nested(`if(menuPads[0]) window.dispatchEvent(${argument});`));

	assert.ok(
		output.includes(
			"if(menuPads[0]) window.dispatchEvent(\n\t\t\t\t\tObject.assign(new Event('gamepaddisconnected'), {gamepad: menuPads[0]})\n\t\t\t\t);"
		)
		, output
	);
}

for(const object of [
	'{pressed: buttons.includes(i), value: buttons.includes(i)?1:0}'
	, '{pressed: buttons.includes(\n\ti\n)\n, value: buttons.includes(\n\ti\n)?1:0\n}'
]){
	const output = await format(
		nested(`menuPads[0].buttons=Array.from({length: 17}, (_, i)=>(${object}));`)
	);

	assert.ok(
		output.includes(
			'menuPads[0].buttons = Array.from({length: 17}, (_, i) => ({\n\t\t\t\t\tpressed: buttons.includes(i)\n\t\t\t\t\t, value: buttons.includes(i) ? 1 : 0\n\t\t\t\t}));'
		)
		, output
	);
}

const spacing = await format(`const fn=(value=0)=>value?1:0;
let total=0;
total+=fn();
const {ready=false}={};
const aligned    = 1;
const comparison=a===b;
`
);

assert.ok(spacing.includes('const fn = (value = 0) => value ? 1 : 0;'));
assert.ok(spacing.includes('total += fn();'));
assert.ok(spacing.includes('const {ready = false} = {};'));
assert.ok(spacing.includes('const aligned    = 1;'));
assert.ok(spacing.includes('const comparison = a===b;'));

const groups = await format(`function setup()
{
	const pad = {id: name
		, index: 0
	}; // Keep the trailing comment.
	connect(pad);
	/** Installs a named handler. */
	window.run=(value=0)=>{
		step(value);
	};
	page.addInitScript(() => {
		step();
	}, {fallback});
	ready();
	return pad;
}
`
);

assert.match(groups, /\}; \/\/ Keep the trailing comment\.\n\n\tconnect/u);

assert.match(
	groups
	, /connect\(pad\);\n\n\t\/\*\* Installs a named handler\. \*\/\n\twindow\.run =/u
);

assert.match(groups, /\}, \{fallback\}\);\n\n\tready\(\);\n\n\treturn pad;/u);

assert.equal(
	await format('function compact(){ step(); return 1; }\n')
	, 'function compact(){ step(); return 1; }\n'
);

const comments = await format(`const pad = { /* Identity. */ id: name, // Stable index.
	index: 0,
	/** Connected flag. */ connected: true
};
`
);

assert.match(comments, /const pad = \{\n/u);

await format(`const pad = {
	text: \`<?php\n  echo "literal = => ? :";\n\`
	, match: /[{}=?:]/
	, /* Keep this nested object. */ options: {enabled: true}
};
`
);

const objectMethod = await format(`const pad = {id: name,
	run(){ return this.id; }, stop(){ return false; }
};
`
);

assert.match(objectMethod, /const pad = \{\n\tid: name/u);
assert.match(objectMethod, /\n\n\t, run/u);

const explicit = `const pad = {
	value: call(
		first
		, second
	)
};
`;

assert.equal(await format(explicit), explicit);

const typed = await format(`const pad: Pad={id: name,
	index: 0
};
window.run=(value: number=0): number=>value?1:0;
`, 'expressions.ts'
);

assert.match(typed, /const pad: Pad = \{\n\tid: name/u);
assert.match(typed, /window\.run = \(value: number = 0\): number => value \? 1 : 0/u);

const optional = await format(
	nested(
		"if(menuPads[0]) target?.dispatchEvent?.(Object.assign(new Event('gamepaddisconnected'), {gamepad: menuPads[0]}));"
	)
);

assert.match(optional, /dispatchEvent\?\.\(\n/u);

const callbackHead = await format(
	`test('world downloads nearby maps and shared dependencies, evicts distant files and restores graphics', async({page})=>{
	await page.goto('/');
});
`
);

assert.match(callbackHead, /test\(\n/u);
assert.match(callbackHead, /\n\t, async\(\{page\}\) => \{/u);
assert.match(callbackHead, /\n\t\tawait page\.goto\('\/'\);\n\t\}/u);

const chained = await format(`const config = {
	plugins: [new CopyPlugin({patterns: ['architecture.md'
		, 'input.md'
	]
	.map(
		name=>({from: 'docs/'+name,to: name})
	)})]
};
`
);

assert.match(chained, /(\t+)\.map\(\n\1\tname =>/u);

const loopCall = await format(`for(const file of files) await fs
	.copyFile(
		source
		, destination
	);
`
);

assert.match(loopCall, /\n\t\.copyFile\(\n\t\tsource\n\t\t, destination\n\t\);/u);

await format(`const pad = { ...defaults,
	['axis' + index]: ({left: 0, right: 1}), get id(){ return name; }
};
`
);

const literalBody = await format(
	`test('world downloads nearby maps and shared dependencies, evicts distant files and restores graphics', async({page})=>{
	await run(\`<?php\n  echo "literal = => ? :";\n\`);
});
`
);

assert.ok(literalBody.includes('`<?php\n  echo "literal = => ? :";\n`'));
