import assert from 'node:assert/strict';
import { ESLint } from 'eslint';
import { parse } from 'espree';
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

async function format(input)
{
	const [result] = await eslint.lintText(input);
	assert.equal(result.errorCount, 0, JSON.stringify(result.messages));
	const output = result.output ?? input;
	const [again] = await eslint.lintText(output);
	assert.equal(again.output ?? output, output, 'Formatting must converge.');

	const program = text => JSON.parse(JSON.stringify(parse(text, {
		ecmaVersion: 'latest'
		, sourceType: 'module'
		, tokens: true
		, comment: true
	}), (key, value) => ['start', 'end', 'loc', 'range'].includes(key) ? undefined : value
	));

	assert.deepEqual(program(output), program(input));

	return output;
}

assert.ok(
	(await format("const label='mode '+mode+' name'; const count=a+b;\n")).includes(
		"'mode ' + mode + ' name'"
	)
);

assert.ok((await format("const count=a+b;\n")).includes('a+b'));

const ternary = await format(
	`const target = fallback ? document.querySelector('textarea[aria-label="SDL text input"]')
    : document.querySelector('canvas').editContext;
`
);

assert.ok(ternary.includes('fallback\n\t? document'), ternary);
assert.ok(ternary.includes('\n\t: document'), ternary);

const loop = await format(
	`for(const fallback of [false,true]) test('fullscreen IME with ' + (fallback ? 'textarea' : 'EditContext'), async({page}) => {
    start(page);
    finish(page);
});
`
);

assert.ok(loop.includes('of [false,true])\ntest('), loop);
assert.ok(loop.includes("(fallback ? 'textarea' : 'EditContext'), async({page}) => {"), loop);

const multilineControl = await format(`for(const item of items) run(() => {
    first();
    second();
});
`
);

assert.ok(multilineControl.includes('of items)\nrun(() => {'), multilineControl);

const concat = await format(`function example()
{
    test('editing discards held-key repeats after ' + recovery + ' recovery with ' + (fallback ? 'textarea' : 'EditContext'), run);
}
`
);

assert.ok(concat.includes("(fallback ? 'textarea' : 'EditContext')"), concat);

await format([
	'const result = first ? /* branch */ choose(first)'
	, '    : second ? choose(second) : null;'
	, 'const string = `<?php'
	, '    literal text, spaces and punctuation: ? :'
	, '?>`;'
	, ''
].join('\n'));

const shortCall = await format('install(\n    name\n    , {enabled: true}\n);\n');
assert.equal(shortCall, 'install(name, {enabled: true});\n');

const documented = await format(
	'install(\n    name // Keep this explanation.\n    , options\n);\n'
);

assert.ok(documented.includes('// Keep this explanation.\n\t, options'), documented);

const logical = await format(`function example()
{
    if(first)
    {
        if(second)
        {
            if(third)
            {
                const sameRail = pairs.has(previous) && openingIndentForCurrentContext(pairs.get(previous)) === expected;
            }
        }
    }
}
`
);

assert.match(
	logical
	, /pairs\.has\(previous\)\n\t+&& openingIndentForCurrentContext\(pairs\.get\(previous\)\)/u
);

const concatenatedCalls = await format(`function example()
{
    if(first)
    {
        if(second)
        {
            const indentationForContinuation = '\\t'.repeat(Math.floor(width / tabWidth) + 1) + ' '.repeat(width % tabWidth);
        }
    }
}
`
);

assert.match(
	concatenatedCalls
	, /repeat\(Math\.floor\(width \/ tabWidth\) \+ 1\)\n\t+\+ ' '\.repeat\(width % tabWidth\)/u
);

const clauses = await format(`if(ready)
{
    step();
}
else if(firstCondition && secondCondition && thirdCondition && fourthCondition && fifthCondition && sixthCondition)
{
    step();
}
`
);

assert.match(clauses, /\}\nelse if\(/u);
assert.doesNotMatch(clauses, /else\n\s*if/u);

await format([
	'console.log('
	, '\t`| ${name} | ${first.median_us.toFixed('
	, '\t\t2'
	, '\t)} | ${last.median_us.toFixed('
	, '\t\t2'
	, '\t)} | ${(first.median_us / last.median_us).toFixed('
	, '\t\t2'
	, '\t)}× |`'
	, ');'
	, ''
].join('\n'));
