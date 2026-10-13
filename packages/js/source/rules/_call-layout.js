import { columns } from './_layout.js';

export function callLayout(source, node, max, tabWidth)
{
	const tokens = source.getTokens(node);
	const closing = tokens.at(-1);

	if(closing.value !== ')' || !node.arguments.length) return null;

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

	const argumentsText = [];
	let current = '';
	let previous = opening;
	depth = 0;

	for(const token of inner)
	{
		if(['Block', 'Line'].includes(token.type) || ['{', '}'].includes(token.value)) return null;

		const text = source.getText(token);

		if(text.includes('\n')) return null;

		if(token.type === 'Punctuator' && token.value === ',' && depth === 0)
		{
			argumentsText.push(current.trim());
			current = '';
			previous = token;
			continue;
		}

		if(token.type === 'Punctuator' && ['(', '['].includes(token.value)) depth++;

		if(token.type === 'Punctuator' && [')', ']'].includes(token.value)) depth--;

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
