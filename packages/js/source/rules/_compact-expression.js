/** Flatten whitespace while preserving comments, literals, blocks and expanded objects. */
export function compactExpression(source, node)
{

	function canFlatten(value)
	{
		if(['BlockStatement', 'ClassBody', 'StaticBlock'].includes(value.type)) return false;

		if(['ObjectExpression', 'ObjectPattern', 'ArrayExpression', 'ArrayPattern'].includes(
			value.type
		)
			&& value.loc.start.line !== value.loc.end.line
		) return false;

		return (source.visitorKeys[value.type] ?? []).every(key => {
			const children = Array.isArray(value[key]) ? value[key] : [value[key]];

			return children.every(child => !child || canFlatten(child));
		});
	}

	if(!canFlatten(node)) return null;

	const tokens = source.getTokens(node, {includeComments: true});
	let previous;
	let output = '';

	for(const token of tokens)
	{
		const text = source.getText(token);

		if(['Line', 'Block'].includes(token.type) || /[\r\n]/u.test(text)) return null;

		if(previous)
		{
			let gap = source.text.slice(previous.range[1], token.range[0]);

			if(/[\r\n]/u.test(gap))
			{
				const tight = ['(', '[', '.', '?.'].includes(previous.value)
					|| [')', ']', ',', '.', '?.'].includes(token.value);

				gap = tight ? '' : ' ';
			}

			output += gap;
		}

		output += text;
		previous = token;
	}

	return output;
}
