import { columns, DEFAULT_MAX_LENGTH } from './_layout.js';
import { compactExpression } from './_compact-expression.js';

export default {
	meta: {
		type: 'layout'
		, fixable: 'code'
		, schema: [{
			type: 'object'
			, properties: {
				max: {type: 'integer', minimum: 40}
			}
			, additionalProperties: false
		}]
		, messages: {expanded: 'Put every property of an expanded object on its own line.'}
	}

	, create(context) {
		const source = context.sourceCode;
		const max = context.options[0]?.max ?? DEFAULT_MAX_LENGTH;

		function check(node)
		{
			if(!node.properties.length) return;

			const opening = source.getFirstToken(node);
			const closing = source.getLastToken(node);
			const line = source.lines[opening.loc.start.line - 1];
			const indent = line.match(/^[\t ]*/u)[0];
			const parent = node.parent;
			const value = parent.init ?? parent.right ?? parent.argument ?? parent.body;

			const expandable = ['VariableDeclarator'
				, 'AssignmentExpression'
				, 'ReturnStatement'
				, 'ArrowFunctionExpression'
			].includes(parent.type) && value === node;

			if(opening.loc.start.line === closing.loc.end.line
				&& !(expandable && columns(line) > max)
			) return;

			const edits = [];
			const first = source.getTokenAfter(opening, {includeComments: true});
			const expanding = first.loc.start.line === opening.loc.end.line;

			function gap(left, right, prefix, newline)
			{
				const actual = source.text.slice(left.range[1], right.range[0]);

				if(!/^\s*$/u.test(actual)) return;

				const breaks = newline
					? '\n'.repeat(Math.max(1, (actual.match(/\n/gu) ?? []).length))
					: '';

				const text = breaks + prefix;

				if(text !== actual) edits.push({range: [left.range[1], right.range[0]], text});
			}

			gap(opening, first, indent + '\t', true);

			for(const property of node.properties)
			{
				const first = source.getFirstToken(property);
				const comma = source.getTokenBefore(first, token => token.value === ',');

				if(property !== node.properties[0] && comma)
				{
					const comments = source.getTokensBetween(comma, first, {includeComments: true})
						.filter(token => ['Line', 'Block'].includes(token.type));

					if(comments.length && !property.method)
					{
						// Move the comma to the property while leaving comments in place.
						edits.push({range: comma.range, text: ''});
						gap(comments.at(-1), first, indent + '\t, ', true);
					}
					else
					{
						gap(
							source.getTokenBefore(comma, {includeComments: true})
							, comma
							, indent + '\t'
							, true
						);

						gap(
							comma
							, source.getTokenAfter(comma, {includeComments: true})
							, ' '
							, false
						);
					}
				}

				if(!expanding || property.loc.start.line === property.loc.end.line) continue;

				const text = compactExpression(source, property);

				if(text !== null && columns(indent + '\t, ' + text) <= max)
				{
					edits.push({range: property.range, text});
				}
			}

			gap(source.getTokenBefore(closing, {includeComments: true}), closing, indent, true);

			if(edits.length) context.report({
				node
				, messageId: 'expanded'
				, fix: fixer => edits.map(edit => fixer.replaceTextRange(edit.range, edit.text))
			});
		}

		return {ObjectExpression: check, ObjectPattern: check};
	}
};
