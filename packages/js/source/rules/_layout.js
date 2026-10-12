export const DEFAULT_MAX_LENGTH = 100;

export function columns(text, tabWidth = 4)
{
	let column = 0;

	for(const character of text)
	{
		column += character === '\t' ? tabWidth - column % tabWidth : 1;
	}
	return column;
}

export function canCompactBody(node, sourceCode, maxLength)
{
	let owner = node.parent;

	if(node.type === 'SwitchStatement')
	{
		owner = node;
	}
	else if(node.type !== 'ClassBody' && ![
		'FunctionDeclaration', 'FunctionExpression', 'ArrowFunctionExpression'
		, 'TryStatement', 'CatchClause', 'StaticBlock'
	].includes(owner?.type)){
		return false;
	}

	if(owner?.type === 'CatchClause')
	{
		owner = owner.parent;
	}

	if(owner?.parent?.type === 'MethodDefinition' || owner?.parent?.method)
	{
		owner = owner.parent;
	}

	return owner && owner.loc.start.line === owner.loc.end.line
		&& node.loc.start.line === node.loc.end.line
		&& columns(sourceCode.lines[owner.loc.start.line - 1]) <= maxLength;
}
