import { stringConcatenation } from './_expression-layout.js';

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: []
		, messages: {space: "Put spaces around '{{operator}}' on the same line."}
	}

	, create(context) {
		const source = context.sourceCode;

		function space(token)
		{
			if(!token) return;

			const previous = source.getTokenBefore(token, {includeComments: true});
			const next = source.getTokenAfter(token, {includeComments: true});

			for(const [left, right] of [[previous, token], [token, next]])
			{
				if(!left || !right || left.loc.end.line !== right.loc.start.line
					|| left.range[1] !== right.range[0]
				) continue;

				context.report({
					loc: token.loc
					, messageId: 'space'
					, data: {operator: token.value}
					, fix: fixer => fixer.insertTextAfter(left, ' ')
				});
			}
		}

		function between(left, right, operator)
		{
			space(source.getFirstTokenBetween(left, right, token => token.value === operator));
		}

		return {

			VariableDeclarator(node) {
				if(node.init) between(node.id, node.init, '=');
			}
			, AssignmentExpression: node => between(node.left, node.right, node.operator)
			, AssignmentPattern: node => between(node.left, node.right, '=')

			, BinaryExpression(node) {
				if(stringConcatenation(node)) between(node.left, node.right, '+');
			}
			, ArrowFunctionExpression: node => space(
				source.getTokenBefore(node.body, token => token.value === '=>')
			)

			, ConditionalExpression(node) {
				between(node.test, node.consequent, '?');
				between(node.consequent, node.alternate, ':');
			}
		};
	}
};
