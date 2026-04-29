import stylistic from '@stylistic/eslint-plugin';

import { composeListeners, withOptions } from './_compose.js';

const indentRule = stylistic.rules.indent;
const noMixedSpacesAndTabsRule = stylistic.rules['no-mixed-spaces-and-tabs'];

function isInlineFunctionBlock(node)
{
	return node.type === 'BlockStatement'
		&& (
			node.parent?.type === 'ArrowFunctionExpression'
			|| node.parent?.type === 'FunctionExpression'
		);
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
			, unexpectedInlineFunctionAllmanOpen: 'Inline function opening brace should stay on the same line as the function head.'
		}
		, docs: {
			description: 'Enforce Allman braces, tab indentation, and smart-tab alignment.'
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;
		const indentListeners = indentRule.create(withOptions(context, ['tab']));
		const noMixedListeners = noMixedSpacesAndTabsRule.create(withOptions(context, ['smart-tabs']));

		function checkOpeningBrace(node)
		{
			const openingBrace = sourceCode.getFirstToken(node);

			if(!openingBrace || openingBrace.value !== '{')
			{
				return;
			}

			const previousToken = sourceCode.getTokenBefore(openingBrace);

			if(!previousToken)
			{
				return;
			}

			const hasComments = sourceCode.getTokensBetween(previousToken, openingBrace, { includeComments: true }).some((token) => token.type === 'Block' || token.type === 'Line');
			const inlineFunctionBlock = isInlineFunctionBlock(node);

			if(inlineFunctionBlock)
			{
				if(previousToken.loc.end.line === openingBrace.loc.start.line)
				{
					return;
				}

				context.report({
					node
					, loc: openingBrace.loc
					, messageId: 'unexpectedInlineFunctionAllmanOpen'
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
			const openingBrace = sourceCode.getFirstToken(node);

			if(!openingBrace || openingBrace.value !== '{')
			{
				return;
			}

			const previousToken = sourceCode.getTokenBefore(openingBrace);

			if(!previousToken)
			{
				return;
			}

			const between = sourceCode.text.slice(previousToken.range[1], openingBrace.range[0]);

			if(isInlineFunctionBlock(node))
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
