import assert from 'node:assert/strict';
import { ESLint } from 'eslint';
import { parse } from 'espree';
import plugin from '../source/index.js';

const eslint = new ESLint({
	overrideConfigFile: true
	, overrideConfig: [
		...plugin.configs.recommended
		, {
			rules: {
				'sm-no-saccade-style/parameter-layout': ['error', {max: 120}]
				, 'sm-no-saccade-style/max-line-length': ['error', {max: 120}]
				, 'sm-no-saccade-style/allman-tabs': ['error', {maxLineLength: 120}]
			}
		}
	]
	, fix: true
});

function program(code)
{
	return JSON.parse(
		JSON.stringify(
			parse(code, {ecmaVersion: 'latest', sourceType: 'module'})
			, (key, value) => ['start', 'end'].includes(key) ? undefined : value
		)
	);
}

async function format(input, filePath = 'parameters.js')
{
	const [result] = await eslint.lintText(input, {filePath});
	assert.equal(result.errorCount, 0, JSON.stringify(result.messages));
	const output = result.output ?? input;
	const [again] = await eslint.lintText(output, {filePath});
	assert.equal(again.errorCount, 0, JSON.stringify(again.messages));
	assert.equal(again.output ?? output, output, 'Formatting must converge.');

	if(filePath.endsWith('.js')) assert.deepEqual(program(output), program(input));

	return output;
}

assert.equal(await format('call(first, second);\n'), 'call(first, second);\n');

assert.equal(
	await format('const identity = value => value;\n')
	, 'const identity = value => value;\n'
);

const mixed = await format(`function work(first, second
	, third = false
){
	return first;
}
`
);

assert.match(mixed, /work\(\n\tfirst\n\t, second\n\t, third = false\n\)\{/u);

const wide = await format(
	`function configureRendering(world, request = null, release = null, horizontalLoadMargin = 256, verticalRetentionMargin = 768)
{
	return world;
}
`
);

assert.match(wide, /configureRendering\(\n\tworld\n\t, request = null/u);
assert.match(wide, /\n\t, horizontalLoadMargin = 256\n\t, verticalRetentionMargin = 768/u);

const args = await format(`call(first, ((second))
	, third
);
`
);

assert.match(args, /call\(\n\tfirst\n\t, \(\(second\)\)\n\t, third\n\);/u);

const grouped = await format('call(\n\tfirst, second\n);\n');
assert.equal(grouped, 'call(\n\tfirst\n\t, second\n);\n');

const comments = await format(`call(first, // Describe the second argument.
	second,
	/* Describe the third argument. */ third
);
`
);

assert.match(comments, /\/\/ Describe the second argument\./u);
assert.match(comments, /\/\* Describe the third argument\. \*\//u);
assert.match(comments, /call\(\n/u);

const callback = await format(`page.evaluate(async source => {
	const result = await run(source);
	return result;
}, source);
`
);

assert.match(callback, /page\.evaluate\(async source => \{/u);
assert.match(callback, /\}, source\n\);/u);

const expression = await format(`const state = page => page.evaluate(canvas =>
	JSON.parse(canvas.dataset.state ?? 'null'));
`
);

assert.match(expression, /JSON\.parse\(canvas\.dataset\.state \?\? 'null'\)\n\);/u);

const literal = await format('await run(page, `<?php\n  echo "unchanged";\n`);\n');
assert.ok(literal.includes('`<?php\n  echo "unchanged";\n`'));

await format('const value = new (factory())(first,\nsecond);\n');
await format('call((first), /* Keep */ (second),\n(...third) => third);\n');
await format('function defaults({first, second} = {},\n...rest) { return rest; }\n');
await format('const transform = (first,\nsecond) => first + second;\n');

const typed = await format(`declare function configure<T>(first: T, second: number,
	third: boolean): T;
interface Factory
{
	create(first: number,
		second: number): void;
}
`, 'parameters.ts'
);

assert.match(typed, /configure<T>\(\n\tfirst: T\n\t, second: number\n\t, third: boolean\n\): T;/u);

const defaults = new ESLint({
	overrideConfigFile: true
	, overrideConfig: [{plugins: {style: plugin}, rules: {'style/parameter-layout': 'error'}}]
	, fix: true
});

const [defaulted] = await defaults.lintText('work(first,\nsecond);\n', {filePath: 'defaults.js'});
assert.equal(defaulted.errorCount, 0);
assert.equal(defaulted.output, 'work(\n\tfirst\n\t, second\n);\n');
