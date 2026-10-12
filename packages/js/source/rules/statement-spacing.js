const CONTROL = new Set(
	['IfStatement'
		, 'ForStatement'
		, 'ForInStatement'
		, 'ForOfStatement'
		, 'WhileStatement'
		, 'DoWhileStatement', 'SwitchStatement', 'TryStatement'
	]
);

export default {
	meta: {
		type: 'layout', fixable: 'whitespace', schema: []
		, messages: { blank: 'Separate control flow and multiline calls from adjacent statements.' }
	}

	, create(context) {
		const source = context.sourceCode;

		function hasMultilineCall(node)
		{
			if(['CallExpression', 'NewExpression'].includes(node.type)
				&& node.loc.start.line !== node.loc.end.line
			) return true;

			if(['FunctionExpression', 'ArrowFunctionExpression'].includes(node.type)) return false;

			return (source.visitorKeys[node.type] ?? []).some(key => {
				const value = node[key];

				return (Array.isArray(value) ? value : [value]).some(
					child => child && hasMultilineCall(child)
				);
			});
		}

		function check(node)
		{
			if(node.type !== 'Program' && node.loc.start.line === node.loc.end.line) return;

			const statements = node.body ?? node.consequent;

			for(let index = 1; index < statements.length; index++)
			{
				const previous = statements[index - 1];
				const current = statements[index];

				const multilineCall = ['ExpressionStatement', 'VariableDeclaration'
					, 'ReturnStatement'
				]
					.includes(current.type) && hasMultilineCall(current);

				if(!CONTROL.has(
					current.type
				) && !multilineCall && previous.type !== 'IfStatement'
				) continue;

				let first = source.getFirstToken(current);

				for(const comment of source.getCommentsBefore(current))
				{
					if(comment.loc.start.line > previous.loc.end.line && comment.range[0] < first
						.range[0]
					)
						first = comment;
				}
				let start = previous.range[1];
				const gap = source.text.slice(start, first.range[0]);

				if(/\n[\t ]*\n/u.test(gap)) continue;

				const indent = source.lines[first.loc.start.line - 1].match(/^[\t ]*/u)[0];
				let end = first.range[0];
				let text = `\n\n${indent}`;

				if(gap.trim())
				{
					start = source.getIndexFromLoc({ line: first.loc.start.line, column: 0 });

					if(start < previous.range[1]) continue;

					end = start;
					text = '\n';
				}

				context.report({ node: current, messageId: 'blank'
					, fix: fixer => fixer.replaceTextRange([start, end], text)
				});
			}
		}
		return { Program: check, BlockStatement: check, SwitchCase: check };
	}
};
