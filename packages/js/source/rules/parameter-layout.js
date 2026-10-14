import { columns, DEFAULT_MAX_LENGTH } from './_layout.js';
import { controlBoundary } from './_expression-layout.js';
import { callLayout } from './_call-layout.js';

/** Find a parameter/argument list without mistaking parenthesized arguments for its bounds. */
function list(source, node)
{
	const items = node.params ?? node.arguments;

	if(!items?.length) return null;

	const punctuator = value => token => token.type === 'Punctuator' && token.value === value;

	const opening = node.params
		? source.getTokenBefore(items[0], punctuator('('))
		: source.getTokenAfter(
			node.typeArguments ?? node.typeParameters ?? node.callee
			, punctuator('(')
		);

	if(!opening || opening.range[0] < node.range[0]) return null;

	let depth = 0;
	let closing;

	for(const token of source.getTokens(node))
	{
		if(token.range[0] < opening.range[0] || token.type !== 'Punctuator') continue;

		if(token.value === '(') depth++;

		if(token.value === ')' && --depth === 0)
		{
			closing = token;
			break;
		}
	}

	if(!closing) return null;

	return {
		items
		, opening
		, closing
		, commas: items.slice(1).map(item => source.getTokenBefore(item, punctuator(',')))
	};
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: [{
			type: 'object'
			, properties: {max: {type: 'integer', minimum: 40}}
			, additionalProperties: false
		}]
		, messages: {
			layout:
				'Use a complete inline argument list when it fits, or consistent vertical arguments.'
			, vertical: 'Use one item per line throughout this expanded parameter or argument list.'
		}
	}

	, create(context) {
		const source = context.sourceCode;
		const max = context.options[0]?.max ?? DEFAULT_MAX_LENGTH;

		function check(node)
		{
			if(controlBoundary(source, node, max)) return;

			if(node.params && node.params.every(
				param => param.loc.start.line === param.loc.end.line
			)){
				for(let parent = node.parent; parent; parent = parent.parent)
				{
					if(['CallExpression', 'NewExpression'].includes(parent.type)
						&& callLayout(source, parent, max, 4)
					)
					return;

					if(parent.type === 'BlockStatement') break;
				}
			}

			const call = node.arguments && callLayout(source, node, max, 4);

			if(call)
			{
				context.report(
					{
						node
						, messageId: 'layout'
						, fix: fixer => fixer.replaceTextRange(call.range, call.text)
					}
				);

				return;
			}

			const span = list(source, node);

			if(!span || span.commas.some(comma => !comma)) return;

			const {items, opening, closing, commas} = span;
			const first = source.getTokenAfter(opening, {includeComments: true});
			const line = source.lines[opening.loc.start.line - 1];
			const indent = line.match(/^[\t ]*/u)[0];
			const longHead = node.params && opening.loc.start.line === closing.loc.end.line
				&& columns(line.slice(0, closing.loc.end.column)) > max;

			const separated = commas.some(comma => {
				const before = source.getTokenBefore(comma);
				const after = source.getTokenAfter(comma);

				return before.loc.end.line !== comma.loc.start.line
					|| comma.loc.end.line !== after.loc.start.line;
			});

			// Multiline callback bodies remain attached; a break between items expands the list.
			if(!longHead && !separated && first.loc.start.line === opening.loc.end.line
				&& !(node.params && items.some(item => item.loc.start.line !== item.loc.end.line))
			) return;

			const edits = [];

			function gap(left, right, expected)
			{
				const actual = source.text.slice(left.range[1], right.range[0]);

				if(actual !== expected && /^\s*$/u.test(actual))
				{
					edits.push({range: [left.range[1], right.range[0]], text: expected});
				}
			}

			gap(opening, first, `\n${indent}\t`);

			for(const comma of commas)
			{
				gap(source.getTokenBefore(comma, {includeComments: true}), comma, `\n${indent}\t`);
				gap(comma, source.getTokenAfter(comma, {includeComments: true}), ' ');
			}

			gap(source.getTokenBefore(closing, {includeComments: true}), closing, `\n${indent}`);

			if(edits.length)
			{
				context.report({
					node
					, messageId: 'vertical'
					, fix: fixer => edits.map(edit => fixer.replaceTextRange(edit.range, edit.text))
				});
			}
		}

		return {
			FunctionDeclaration: check
			, FunctionExpression: check
			, ArrowFunctionExpression: check
			, CallExpression: check
			, NewExpression: check
			, TSDeclareFunction: check
			, TSMethodSignature: check
			, TSFunctionType: check
			, TSCallSignatureDeclaration: check
			, TSConstructSignatureDeclaration: check
		};
	}
};
