export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: []
		, messages: { blank: 'Add a blank line before this declaration and its documentation.' }
	}



	, create(context) {
		const source = context.sourceCode;
		const seen = new Set();

		function check(node)
		{
			if(node.type === 'FunctionExpression' || node.type === 'ArrowFunctionExpression'
				|| node.type === 'ClassExpression'
			){
				if(node.parent.type === 'VariableDeclarator') node = node.parent.parent;
				else if(node.parent.type === 'AssignmentExpression' && node.parent.right === node
					&& node.parent.parent.type === 'ExpressionStatement'
				) node = node.parent.parent;
				else return;
			}

			let first = source.getFirstToken(node);

			if(node.decorators?.length)
			{
				const decorator = source.getFirstToken(node.decorators[0]);

				if(decorator.range[0] < first.range[0]) first = decorator;
			}

			if(node.parent.type.startsWith('Export'))
			{
				node = node.parent;
				const exported = source.getFirstToken(node);

				if(exported.range[0] < first.range[0]) first = exported;
			}

			let previous = source.getTokenBefore(first, { includeComments: true });

			while(previous?.type === 'Block' && previous.value.startsWith('*')
				&& previous.loc.end.line + 1 >= first.loc.start.line
			){
				first = previous;
				previous = source.getTokenBefore(first, { includeComments: true });
			}

			if(previous?.value === ',')
			{
				first = previous;
				previous = source.getTokenBefore(first, { includeComments: true });
			}

			if(!previous || first.loc.start.line > previous.loc.end.line + 1 || seen.has(
				first.range[0]
			)) return;

			seen.add(first.range[0]);
			const indent = source.lines[first.loc.start.line - 1].match(/^[\t ]*/u)[0];

			context.report({
				node
				, messageId: 'blank'
				, fix: fixer => fixer.replaceTextRange(
					[previous.range[1], first.range[0]]
					, `\n\n${indent}`
				)
			});
		}

		return {
			ClassDeclaration: check
			, FunctionDeclaration: check
			, MethodDefinition: check
			, TSDeclareFunction: check
			, TSAbstractMethodDefinition: check
			, FunctionExpression: check
			, ArrowFunctionExpression: check
			, ClassExpression: check
			, 'Property[method=true]': check
		};
	}
};
