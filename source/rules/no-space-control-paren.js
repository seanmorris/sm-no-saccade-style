const CONTROL_TYPES = new Set([
	'CatchClause'
	, 'DoWhileStatement'
	, 'ForInStatement'
	, 'ForOfStatement'
	, 'ForStatement'
	, 'IfStatement'
	, 'SwitchStatement'
	, 'WhileStatement'
	, 'WithStatement'

]);

function getParenToken(sourceCode, node)
{
	if(node.type === 'CatchClause')
	{
		return node.param ? sourceCode.getFirstTokenBetween(sourceCode.getFirstToken(node), node.param, (token) => token.value === '(') : null;
	}

	if(node.type === 'DoWhileStatement')
	{
		return sourceCode.getFirstTokenBetween(sourceCode.getFirstToken(node, { skip: 1 }), node.test, (token) => token.value === '(');
	}

	return sourceCode.getFirstTokenBetween(sourceCode.getFirstToken(node), node.test ?? node.discriminant ?? node.right, (token) => token.value === '(');
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: []
		, messages: {
			unexpectedSpace: 'Control keywords should hug the opening parenthesis.'
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;

		function checkNode(node)
		{
			if(!CONTROL_TYPES.has(node.type))
			{
				return;
			}

			const keywordToken = sourceCode.getFirstToken(node);
			const parenToken = getParenToken(sourceCode, node);

			if(!keywordToken || !parenToken)
			{
				return;
			}

			if(keywordToken.loc.end.line !== parenToken.loc.start.line)
			{
				return;
			}

			if(!sourceCode.isSpaceBetween(keywordToken, parenToken))
			{
				return;
			}

			context.report({
				node
				, loc: {
					start: keywordToken.loc.end
					, end: parenToken.loc.start
				}
				, messageId: 'unexpectedSpace'
				, fix: (fixer) => fixer.removeRange([keywordToken.range[1], parenToken.range[0]])
			});
		}

		return {
			CatchClause: checkNode
			, DoWhileStatement: checkNode
			, ForInStatement: checkNode
			, ForOfStatement: checkNode
			, ForStatement: checkNode
			, IfStatement: checkNode
			, SwitchStatement: checkNode
			, WhileStatement: checkNode
			, WithStatement: checkNode
		};
	}

};
