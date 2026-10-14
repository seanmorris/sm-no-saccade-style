import { columns } from './_layout.js';
import { compactExpression } from './_compact-expression.js';

/** Identify proven string concatenation without imposing numeric-operator spacing. */
export function stringConcatenation(node)
{

	const string = value => value?.type === 'Literal' && typeof value.value === 'string'
		|| value?.type === 'TemplateLiteral' || stringConcatenation(value);

	return node?.type === 'BinaryExpression' && node.operator === '+'
		&& (string(node.left) || string(node.right));
}

/** Find the outer control/body boundary before wrapping the body's expressions. */
export function controlBoundary(source, node, max, tabWidth = 4)
{
	for(let body = node; body?.parent; body = body.parent)
	{
		const parent = body.parent;

		if(parent.type === 'IfStatement' && parent.alternate === body
			&& body.type === 'IfStatement'
		)
		continue;

		const control = ['ForStatement'
			, 'ForInStatement'
			, 'ForOfStatement'
			, 'WhileStatement'
			, 'DoWhileStatement'
		]
			.includes(parent.type) && parent.body === body
			|| parent.type === 'IfStatement' && [parent.consequent, parent.alternate].includes(
				body
			);

		if(!control || body.type === 'BlockStatement') continue;

		const previous = source.getTokenBefore(body);

		if(!previous || previous.loc.end.line !== body.loc.start.line) continue;

		const line = source.lines[body.loc.start.line - 1];
		const flat = body.type === 'ExpressionStatement' ? compactExpression(source, body) : null;

		if(columns(line, tabWidth) <= max
			&& (flat === null || columns(
				line.slice(0, body.loc.start.column) + flat
				, tabWidth
			) <= max
			)
		)
		continue;

		const range = [previous.range[1], body.range[0]];

		if(!/^\s*$/u.test(source.text.slice(...range))) continue;

		return {
			range
			, text: '\n' + source.lines[parent.loc.start.line - 1].match(/^[\t ]*/u)[0]
			, line: body.loc.start.line
		};
	}

	return null;
}

/** Normalize the complete ternary while preserving branch comments and raw literals. */
export function conditionalLayout(source, node, max, tabWidth)
{
	const question = source.getFirstTokenBetween(
		node.test
		, node.consequent
		, token => token.value === '?'
	);

	const colon = source.getFirstTokenBetween(
		node.consequent
		, node.alternate
		, token => token.value === ':'
	);

	if(!question || !colon) return null;

	const line = source.lines[node.loc.start.line - 1];
	const indent = line.match(/^[\t ]*/u)[0];
	const prefix = line.slice(0, node.loc.start.column);
	const suffix = source.lines[node.loc.end.line - 1].slice(node.loc.end.column);
	const flat = compactExpression(source, node);
	const multiline = node.loc.start.line !== node.loc.end.line;
	const both = question.loc.start.line > node.test.loc.end.line && colon.loc.start.line > node
		.consequent.loc.end.line;

	if(multiline && !both && flat !== null && columns(prefix + flat + suffix, tabWidth) <= max)
	{
		return [{range: node.range, text: flat}];
	}

	const direct = ['VariableDeclarator', 'AssignmentExpression', 'ReturnStatement'].includes(
		node.parent?.type
	);

	if(!multiline && columns((direct ? prefix : indent) + (flat ?? '') + suffix, tabWidth) <= max
	)
	return null;

	const edits = [];

	for(const operator of [question, colon])
	{
		const before = source.getTokenBefore(operator, {includeComments: true});
		const after = source.getTokenAfter(operator, {includeComments: true});

		for(const [left, right, text] of [[before, operator, `\n${indent}\t`]
			, [operator, after
				, ' '
			]
		]){
			const range = [left.range[1], right.range[0]];
			const actual = source.text.slice(...range);

			if(actual !== text && /^\s*$/u.test(actual)) edits.push({range, text});
		}
	}

	return edits.length ? edits : null;
}
