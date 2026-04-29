export function getListItems(node)
{
	if(node.type === 'ArrayExpression' || node.type === 'ArrayPattern')
	{
		return node.elements.filter((element) => element && element.type !== 'SpreadElement' && element.type !== 'RestElement');
	}

	return node.properties.filter((property) => property.type === 'Property');
}

export function getLineStartIndex(sourceCode, lineNumber)
{
	let index = 0;

	for(let i = 0; i < lineNumber - 1; i += 1)
	{
		index += sourceCode.lines[i].length + 1;
	}

	return index;
}

export function getLineIndent(sourceCode, token)
{
	const lineStartIndex = getLineStartIndex(sourceCode, token.loc.start.line);

	return sourceCode.text.slice(lineStartIndex, token.range[0]);
}

export function getTokenLineIndent(sourceCode, token)
{
	return getLineIndent(sourceCode, token).match(/^[\t ]*/u)?.[0] ?? '';
}

export function isFirstTokenOnLine(sourceCode, token)
{
	const lineStartIndex = getLineStartIndex(sourceCode, token.loc.start.line);
	const beforeToken = sourceCode.text.slice(lineStartIndex, token.range[0]);

	return beforeToken.trim() === '';
}

export function hasCommentsBetween(sourceCode, leftToken, rightToken)
{
	const tokens = sourceCode.getTokensBetween(leftToken, rightToken, { includeComments: true });

	return tokens.some((token) => token.type === 'Block' || token.type === 'Line');
}
