import stylistic from '@stylistic/eslint-plugin';

import { composeListeners, withOptions } from './_compose.js';
import { indentOptions } from './_indent-options.js';

const indentRule = stylistic.rules.indent;
const noMixedSpacesAndTabsRule = stylistic.rules['no-mixed-spaces-and-tabs'];
const MULTILINE_CONTROL_HEAD_TYPES = new Set([
	'ForInStatement'
	, 'ForOfStatement'
	, 'ForStatement'
	, 'IfStatement'
	, 'SwitchStatement'
	, 'WhileStatement'
	, 'WithStatement'
]);

function isInlineBody(node)
{
	if(node.type === 'ClassBody')
	{
		return node.parent?.type === 'ClassExpression';
	}

	if(node.type !== 'BlockStatement')
	{
		return false;
	}

	if(node.parent?.type === 'ArrowFunctionExpression')
	{
		return true;
	}

	if(node.parent?.type !== 'FunctionExpression')
	{
		return false;
	}

	if(node.parent.parent?.type === 'MethodDefinition')
	{
		return node.parent.parent.parent?.parent?.type === 'ClassExpression';
	}

	if(node.parent.parent?.type === 'Property' && node.parent.parent.method)
	{
		return node.parent.parent.parent?.type === 'ObjectExpression';
	}

	return true;
}

function getOpeningBraceToken(sourceCode, node)
{
	if(node.type === 'SwitchStatement')
	{
		const rightParen = sourceCode.getTokenAfter(node.discriminant, (token) => token.value === ')');

		return rightParen
			? sourceCode.getTokenAfter(rightParen, (token) => token.value === '{')
			: null;
	}

	return sourceCode.getFirstToken(node);
}

function isEmptyBody(node)
{
	if(node.type === 'ClassBody')
	{
		return node.body.length === 0;
	}

	if(node.type === 'SwitchStatement')
	{
		return node.cases.length === 0;
	}

	return node.type === 'BlockStatement' && node.body.length === 0;
}

function hasMultilineControlHead(node)
{
	if(node.type === 'SwitchStatement')
	{
		return node.loc.start.line !== node.discriminant.loc.end.line;
	}

	if(node.type !== 'BlockStatement' || !MULTILINE_CONTROL_HEAD_TYPES.has(node.parent?.type))
	{
		return false;
	}

	const headEndNode = node.parent.test ?? node.parent.right ?? node.parent.discriminant ?? node.parent.object;

	return node.type === 'BlockStatement'
		&& !!headEndNode
		&& node.parent.loc.start.line !== headEndNode.loc.end.line
		&& node.parent.loc.start.line !== node.loc.start.line;
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: []
		, messages: {
			...indentRule.meta.messages,
			...noMixedSpacesAndTabsRule.meta.messages,
			expectedAllmanOpen: 'Opening brace should be on its own line.'
			, unexpectedInlineAllmanOpen: 'Inline opening brace should stay on the same line as the head.'
			, unexpectedMultilineControlOpen: 'Multiline control heads should keep the opening brace on the same line as the closing parenthesis.'
		}
		, docs: {
			description: 'Enforce Allman braces, tab indentation, and smart-tab alignment.'
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;
		const indentListeners = indentRule.create(withOptions(context, indentOptions));
		const noMixedListeners = noMixedSpacesAndTabsRule.create(withOptions(context, ['smart-tabs']));

		function checkOpeningBrace(node)
		{
			const openingBrace = getOpeningBraceToken(sourceCode, node);

			/* c8 ignore next 4 */
			if(!openingBrace || openingBrace.value !== '{')
			{
				return;
			}

			const previousToken = sourceCode.getTokenBefore(openingBrace);

			/* c8 ignore next 4 */
			if(!previousToken)
			{
				return;
			}

			const hasComments = sourceCode.getTokensBetween(previousToken, openingBrace, { includeComments: true }).some((token) => token.type === 'Block' || token.type === 'Line');
			const inlineBody = isInlineBody(node);
			const emptyBody = isEmptyBody(node);
			const multilineControlHead = hasMultilineControlHead(node);

			if(emptyBody && previousToken.loc.end.line === openingBrace.loc.start.line)
			{
				return;
			}

			if(multilineControlHead)
			{
				if(previousToken.loc.end.line === openingBrace.loc.start.line)
				{
					return;
				}

				context.report({
					node
					, loc: openingBrace.loc
					, messageId: 'unexpectedMultilineControlOpen'
					, fix: hasComments
						? null
						: (fixer) => fixer.replaceTextRange([previousToken.range[1], openingBrace.range[0]], '')
				});

				return;
			}

			if(inlineBody)
			{
				if(previousToken.loc.end.line === openingBrace.loc.start.line)
				{
					return;
				}

				context.report({
					node
					, loc: openingBrace.loc
					, messageId: 'unexpectedInlineAllmanOpen'
					, fix: hasComments
						? null
						: (fixer) => fixer.replaceTextRange([previousToken.range[1], openingBrace.range[0]], ' ')
				});

				return;
			}

			if(previousToken.loc.end.line !== openingBrace.loc.start.line)
			{
				return;
			}

			context.report({
				node
				, loc: openingBrace.loc
				, messageId: 'expectedAllmanOpen'
				, fix: hasComments
					? null
					: (fixer) => fixer.replaceTextRange([previousToken.range[1], openingBrace.range[0]], '\n')
			});
		}

		function stripSpaceBeforeOpeningBrace(node)
		{
			const openingBrace = getOpeningBraceToken(sourceCode, node);

			/* c8 ignore next 4 */
			if(!openingBrace || openingBrace.value !== '{')
			{
				return;
			}

			const previousToken = sourceCode.getTokenBefore(openingBrace);

			/* c8 ignore next 4 */
			if(!previousToken)
			{
				return;
			}

			const between = sourceCode.text.slice(previousToken.range[1], openingBrace.range[0]);

			if(isInlineBody(node))
			{
				return;
			}

			if(!between.includes('\n') || !/[ \t]+\n[ \t]*$/u.test(between))
			{
				return;
			}

			context.report({
				node
				, loc: openingBrace.loc
				, message: 'Unexpected horizontal whitespace before an Allman opening brace.'
				, fix: (fixer) => fixer.replaceTextRange([previousToken.range[1], openingBrace.range[0]], between.replace(/[ \t]+\n([ \t]*)$/u, '\n$1'))
			});
		}

		return composeListeners(
			indentListeners,
			noMixedListeners,
			{
				BlockStatement: checkOpeningBrace
				, ClassBody: checkOpeningBrace
				, SwitchStatement: checkOpeningBrace
			},
			{
				BlockStatement: stripSpaceBeforeOpeningBrace
				, ClassBody: stripSpaceBeforeOpeningBrace
				, SwitchStatement: stripSpaceBeforeOpeningBrace
			},
		);
	}
};
