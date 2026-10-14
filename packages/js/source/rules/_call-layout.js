import { columns } from './_layout.js';
import { compactExpression } from './_compact-expression.js';

/** Expand only argument boundaries when callback bodies or comments must stay intact. */
function expandedCall(source, node, opening, closing, max, tabWidth)
{
	const line = source.lines[opening.loc.start.line - 1];

	if(columns(line, tabWidth) <= max) return null;

	const indent = line.match(/^[\t ]*/u)[0];
	const edits = [];

	function gap(left, right, text)
	{
		const actual = source.text.slice(left.range[1], right.range[0]);

		if(actual !== text && /^\s*$/u.test(actual))
		{
			edits.push({range: [left.range[1], right.range[0]], text});
		}
	}

	gap(opening, source.getTokenAfter(opening, {includeComments: true}), `\n${indent}\t`);

	for(let index = 1; index < node.arguments.length; index++)
	{
		const comma = source.getFirstTokenBetween(
			node.arguments[index - 1]
			, node.arguments[index]
			, token => token.value === ','
		);

		gap(source.getTokenBefore(comma, {includeComments: true}), comma, `\n${indent}\t`);
		gap(comma, source.getTokenAfter(comma, {includeComments: true}), ' ');
	}

	gap(source.getTokenBefore(closing, {includeComments: true}), closing, `\n${indent}`);

	if(!edits.length) return null;

	const range = [opening.range[1], closing.range[0]];
	let text = source.text.slice(...range);

	for(const edit of edits.sort((left, right) => right.range[0] - left.range[0]))
	{
		text = text.slice(0, edit.range[0] - range[0]) + edit.text
			+ text.slice(edit.range[1] - range[0]);
	}

	return {range, text, startLine: opening.loc.start.line, endLine: closing.loc.start.line};
}

export function callLayout(source, node, max, tabWidth)
{
	const tokens = source.getTokens(node);
	const closing = tokens.at(-1);

	if(closing.value !== ')' || !node.arguments.length) return null;

	// Returned objects expand their properties before the enclosing callback call wraps.
	if(node.arguments.some(
		argument => argument.type === 'ArrowFunctionExpression' && argument.body
			.type === 'ObjectExpression'
			&& argument.body.loc.start.line === argument.body.loc.end.line
	)) return null;

	let depth = 0;
	let opening;

	for(let index = tokens.length - 1; index >= 0; index--)
	{
		const token = tokens[index];

		if(token.type !== 'Punctuator') continue;

		if(token.value === ')') depth++;

		if(token.value === '(' && --depth === 0)
		{
			opening = token;
			break;
		}
	}

	if(!opening) return null;

	const inner = source.getTokensBetween(opening, closing, { includeComments: true });

	const closingPrefix = source.lines[closing.loc.start.line - 1].slice(
		0
		, closing.loc.start.column
	);

	if(inner[0].loc.start.line > opening.loc.start.line && !closingPrefix.trim()) return null;

	if(node.arguments.some(argument => compactExpression(source, argument) === null)
		|| inner.some(token => ['Block', 'Line'].includes(token.type))
	){
		return expandedCall(source, node, opening, closing, max, tabWidth);
	}

	const argumentsText = [];
	let current = '';
	let previous = opening;
	depth = 0;

	for(const token of inner)
	{
		if(['Block', 'Line'].includes(token.type)) return null;

		const text = source.getText(token);

		if(text.includes('\n')) return null;

		if(token.type === 'Punctuator' && token.value === ',' && depth === 0)
		{
			argumentsText.push(current.trim());
			current = '';
			previous = token;
			continue;
		}

		if(token.type === 'Punctuator' && ['(', '[', '{'].includes(token.value)) depth++;

		if(token.type === 'Punctuator' && [')', ']', '}'].includes(token.value)) depth--;

		let gap = source.text.slice(previous.range[1], token.range[0]);

		if(gap.includes('\n'))
		{
			const tight = ['(', '[', '.', '?.'].includes(previous.value)
				|| [')', ']', ',', '.', '?.'].includes(token.value);

			gap = tight ? '' : ' ';
		}

		current += gap + text;
		previous = token;
	}

	argumentsText.push(current.trim());

	if(argumentsText.some(text => !text)) return null;

	const line = source.lines[opening.loc.start.line - 1];
	const head = line.slice(0, opening.loc.end.column);
	const tail = source.lines[closing.loc.start.line - 1].slice(closing.loc.start.column);

	if(columns(head + argumentsText.join(', ') + tail, tabWidth) <= max) return null;

	const indent = line.match(/^[\t ]*/u)[0];

	return {
		range: [opening.range[1], closing.range[0]]
		, text: `\n${indent}\t${argumentsText.join(`\n${indent}\t, `)}\n${indent}`
		, startLine: opening.loc.start.line
		, endLine: closing.loc.start.line
	};
}
