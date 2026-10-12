export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: []
		, messages: {
			space: 'Use one space after {{separator}} when the next item is on the same line.'
		}
	}

	, create(context) {
		const sourceCode = context.sourceCode;

		function checkSeparator(token)
		{
			if(!token)
			{
				return;
			}

			const next = sourceCode.getTokenAfter(token, { includeComments: true });
			if(!next || next.value === ')' || token.loc.end.line !== next.loc.start.line)
			{
				return;
			}

			const range = [token.range[1], next.range[0]];
			if(sourceCode.text.slice(...range) === ' ')
			{
				return;
			}

			context.report({
				loc: token.loc
				, messageId: 'space'
				, data: { separator: token.value === ',' ? 'a comma' : 'a for-header semicolon' }
				, fix: fixer => fixer.replaceTextRange(range, ' ')
			});
		}

		function checkList(node)
		{
			const items = node.params ?? node.arguments;
			for(let index = 1; index < items.length; index++)
			{
				checkSeparator(sourceCode.getFirstTokenBetween(items[index - 1], items[index]
					, token => token.value === ','));
			}
		}

		function checkFor(node)
		{
			const opening = sourceCode.getFirstToken(node, token => token.value === '(');
			const first = sourceCode.getTokenAfter(node.init ?? opening);
			const second = sourceCode.getTokenAfter(node.test ?? first);
			checkSeparator(first);
			checkSeparator(second);
		}

		return {
			CallExpression: checkList
			, NewExpression: checkList
			, FunctionDeclaration: checkList
			, FunctionExpression: checkList
			, ArrowFunctionExpression: checkList
			, TSDeclareFunction: checkList
			, TSEmptyBodyFunctionExpression: checkList
			, TSFunctionType: checkList
			, TSConstructorType: checkList
			, TSMethodSignature: checkList
			, TSCallSignatureDeclaration: checkList
			, TSConstructSignatureDeclaration: checkList
			, ForStatement: checkFor
		};
	}
};
