import stylistic from '@stylistic/eslint-plugin';

import { composeListeners, withOptions, withReportFilter } from './_compose.js';
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

		return sourceCode.getTokenAfter(rightParen, (token) => token.value === '{');
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

export function hasMultilineControlHead(node)
{
	if(node.type === 'SwitchStatement')
	{
		return node.loc.start.line !== node.discriminant.loc.end.line;
	}

	if(node.type !== 'BlockStatement' || !MULTILINE_CONTROL_HEAD_TYPES.has(node.parent?.type))
	{
		return false;
	}

	let headEndNode = null;

	switch(node.parent.type)
	{
		case 'IfStatement':
		case 'WhileStatement':
			headEndNode = node.parent.test;
			break;

		case 'ForInStatement':
		case 'ForOfStatement':
			headEndNode = node.parent.right;
			break;

		case 'ForStatement':
			headEndNode = node.parent.update ?? node.parent.test ?? node.parent.init;
			break;

		case 'WithStatement':
			headEndNode = node.parent.object;
			break;
	}

	return node.type === 'BlockStatement'
		&& !!headEndNode
		&& node.parent.loc.start.line !== headEndNode.loc.end.line
		&& node.parent.loc.start.line !== node.loc.start.line;
}

function hasMultilineFunctionHead(node, previousToken)
{
	if(node.type !== 'BlockStatement' || !previousToken || previousToken.value !== ')')
	{
		return false;
	}

	const fn = node.parent;

	if(
		fn?.type !== 'FunctionDeclaration'
		&& fn?.type !== 'FunctionExpression'
		&& fn?.type !== 'ArrowFunctionExpression'
	){
		return false;
	}

	return fn.loc.start.line !== previousToken.loc.end.line
		&& previousToken.value === ')';
}

function getTokenLineIndent(sourceCode, token)
{
	const line = sourceCode.lines[token.loc.start.line - 1];

	return line.match(/^[\t ]*/u)[0];
}

export function buildMixedClosingRailFix(sourceCode, fixer, previousToken, closingTokens, openingIndents)
{
	const previousLineIndent = getTokenLineIndent(sourceCode, previousToken);

	if(previousLineIndent !== openingIndents[0])
	{
		return null;
	}

	let replacement = '';
	let currentIndent = openingIndents[0];

	for(let i = 0; i < closingTokens.length; i += 1)
	{
		const token = closingTokens[i];
		const openingIndent = openingIndents[i];

		if(i === 0)
		{
			replacement += token.value;
			continue;
		}

		if(openingIndent === currentIndent)
		{
			replacement += token.value;
			continue;
		}

		replacement += `\n${openingIndent}${token.value}`;
		currentIndent = openingIndent;
	}

	return fixer.replaceTextRange([previousToken.range[1], closingTokens.at(-1).range[1]], replacement);
}

function isOwnLineClosingDelimiter(sourceCode, descriptor)
{
	if(descriptor.messageId !== 'wrongIndentation' || descriptor.node?.type !== 'Punctuator')
	{
		return false;
	}

	const token = sourceCode.getTokenByRangeStart(descriptor.node.range[0]);

	if(!token || !')]}'.includes(token.value))
	{
		return false;
	}

	const lineIndent = getTokenLineIndent(sourceCode, token);

	return lineIndent.length === token.loc.start.column;
}

function isLeadingChainDot(sourceCode, descriptor)
{
	if(descriptor.messageId !== 'wrongIndentation' || descriptor.node?.type !== 'Punctuator')
	{
		return false;
	}

	const token = sourceCode.getTokenByRangeStart(descriptor.node.range[0]);

	if(!token || token.value !== '.')
	{
		return false;
	}

	const lineIndent = getTokenLineIndent(sourceCode, token);

	return lineIndent.length === token.loc.start.column;
}

export function collectChainedCallbackBodyRanges(root)
{
	const ranges = [];

	function visit(node, parent, grandparent)
	{
		if(!node || typeof node !== 'object')
		{
			return;
		}

		if(
			node.type === 'BlockStatement'
			&& parent?.type === 'ArrowFunctionExpression'
			&& grandparent?.type === 'CallExpression'
		){
			ranges.push([node.loc.start.line, node.loc.end.line]);
		}

		for(const [key, value] of Object.entries(node))
		{
			if(key === 'parent')
			{
				continue;
			}

			if(Array.isArray(value))
			{
				for(const child of value)
				{
					if(child?.type)
					{
						visit(child, node, parent);
					}
				}

				continue;
			}

			if(value?.type)
			{
				visit(value, node, parent);
			}
		}
	}

	visit(root, null, null);

	return ranges;
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
			, unexpectedMultilineFunctionOpen: 'Multiline function heads should keep the opening brace on the same line as the closing parenthesis.'
			, mixedClosingRays: 'Closing delimiters that belong to different opening rails should not share a line.'
		}
		, docs: {
			description: 'Enforce Allman braces, tab indentation, and smart-tab alignment.'
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;
		const chainedCallbackBodyRanges = collectChainedCallbackBodyRanges(sourceCode.ast);
		const filteredIndentContext = withOptions(
			withReportFilter(
				context,
				(descriptor) => !isOwnLineClosingDelimiter(sourceCode, descriptor)
					&& !isLeadingChainDot(sourceCode, descriptor)
					&& !(
						descriptor.messageId === 'wrongIndentation'
						&& descriptor.loc?.start?.line
						&& chainedCallbackBodyRanges.some(
							([start, end]) => descriptor.loc.start.line > start && descriptor.loc.start.line < end
						)
					)
			),
			indentOptions
		);
		const indentListeners = indentRule.create(filteredIndentContext);
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
			const multilineFunctionHead = hasMultilineFunctionHead(node, previousToken);

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

			if(multilineFunctionHead)
			{
				if(previousToken.loc.end.line === openingBrace.loc.start.line)
				{
					return;
				}

				context.report({
					node
					, loc: openingBrace.loc
					, messageId: 'unexpectedMultilineFunctionOpen'
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

		function checkClosingDelimiterIndent()
		{
			const tokens = sourceCode.ast.tokens;
			const stack = [];

			for(let i = 0; i < tokens.length; i += 1)
			{
				const token = tokens[i];

				if('([{'.includes(token.value))
				{
					stack.push(token);
					continue;
				}

				if(!')]}'.includes(token.value))
				{
					continue;
				}

				const openingToken = stack.pop();

				const lineIndent = getTokenLineIndent(sourceCode, token);

				if(lineIndent.length !== token.loc.start.column)
				{
					continue;
				}

				const previousToken = tokens[i - 1];

				const closingTokens = [token];
				const openingTokens = [openingToken];
				const tempStack = stack.slice();

				for(let j = i + 1; j < tokens.length; j += 1)
				{
					const nextToken = tokens[j];

					if(nextToken.loc.start.line !== token.loc.start.line || !')]}'.includes(nextToken.value))
					{
						break;
					}

					const nextOpeningToken = tempStack.pop();

					closingTokens.push(nextToken);
					openingTokens.push(nextOpeningToken);
				}

				const openingIndents = openingTokens.map((candidate) => getTokenLineIndent(sourceCode, candidate));
				const distinctOpeningIndents = [...new Set(openingIndents)];

				if(distinctOpeningIndents.length > 1)
				{
					context.report({
						loc: token.loc
						, messageId: 'mixedClosingRays'
						, fix: (fixer) => buildMixedClosingRailFix(sourceCode, fixer, previousToken, closingTokens, openingIndents)
					});

					continue;
				}

				const expectedIndent = openingTokens
					.map((candidate) => getTokenLineIndent(sourceCode, candidate))
					.sort((left, right) => left.length - right.length)[0];

				if(openingTokens.some((candidate) => candidate.loc.start.line === token.loc.start.line) || lineIndent === expectedIndent)
				{
					continue;
				}

				context.report({
					loc: token.loc
					, message: `Closing '${token.value}' should align with its opening rail.`
					, fix: (fixer) => fixer.replaceTextRange(
						[
							token.range[0] - token.loc.start.column
							, token.range[0]
						],
						expectedIndent
					)
				});
			}
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
			{
				'Program:exit': checkClosingDelimiterIndent
			},
		);
	}
};
