import stylistic from '@stylistic/eslint-plugin';

import { composeListeners, withOptions, withReportFilter } from './_compose.js';
import { indentOptions } from './_indent-options.js';
import { canCompactBody, DEFAULT_MAX_LENGTH, isBlockClose } from './_layout.js';

const indentRule = stylistic.rules.indent;
const noMixedSpacesAndTabsRule = stylistic.rules['no-mixed-spaces-and-tabs'];

const MULTILINE_CONTROL_HEAD_TYPES = new Set(
	['ForInStatement'
		, 'ForOfStatement'
		, 'ForStatement'
		, 'IfStatement'
		, 'SwitchStatement'
		, 'WhileStatement', 'WithStatement'
	]
);

function isInlineBody(node)
{
	if(node.type === 'ClassBody')
	{
		return node.parent?.type === 'ClassExpression'
			|| node.loc.start.line === node.loc.end.line;
	}

	if(node.type !== 'BlockStatement')
	{
		return false;
	}

	if(node.loc.start.line === node.loc.end.line
		&& (
			node.parent?.type === 'ArrowFunctionExpression'
			|| node.parent?.type === 'FunctionDeclaration'
			|| node.parent?.type === 'FunctionExpression'
		)
	){
		return true;
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
		const rightParen = sourceCode.getTokenAfter(
			node.discriminant
			, (token) => token.value === ')'
		);

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

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: [{
			type: 'object'
			, properties: {
				maxLineLength: { type: 'integer', minimum: 40 }
			}
			, additionalProperties: false
		}]
		, messages: {
			...indentRule.meta.messages
			, ...noMixedSpacesAndTabsRule.meta.messages
			, expectedAllmanOpen: 'Opening brace should be on its own line.'
			, unexpectedInlineAllmanOpen:
				'Inline opening brace should stay on the same line as the head.'
			, unexpectedMultilineControlOpen: 'Multiline control heads should keep the opening brace on the same line as the closing parenthesis.'
			, unexpectedMultilineFunctionOpen: 'Multiline function heads should keep the opening brace on the same line as the closing parenthesis.'
			, expandedBody: 'Multiline blocks need separate statement and brace lines.'
			, attachedClause:
				"Keep '{{clause}}' on the next line after its block, without a blank line."
		}
		, docs: {
			description: 'Enforce Allman braces, tab indentation, and smart-tab alignment.'
		}
	}

	, create(context) {
		const sourceCode = context.sourceCode;
		const maxLength = context.options[0]?.maxLineLength ?? DEFAULT_MAX_LENGTH;
		const blockIndents = new Map();

		function openingIndent(token)
		{
			return blockIndents.get(token.range[0]) ?? getTokenLineIndent(sourceCode, token);
		}

		const filteredIndentContext = withOptions(
			withReportFilter(
				context,
				(descriptor) => !isOwnLineClosingDelimiter(sourceCode, descriptor)
			),
			indentOptions
		);

		const indentListeners = indentRule.create(filteredIndentContext);

		const noMixedListeners = noMixedSpacesAndTabsRule.create(
			withOptions(context, ['smart-tabs'])
		);

		function checkOpeningBrace(node)
		{
			if(canCompactBody(node, sourceCode, maxLength)) return;

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

			const hasComments = sourceCode.getTokensBetween(
				previousToken
				, openingBrace
				, { includeComments: true }
			).some((token) => token.type === 'Block' || token.type === 'Line');

			const inlineBody = isInlineBody(node);
			const emptyBody = isEmptyBody(node);
			const multilineControlHead = hasMultilineControlHead(node);
			const multilineFunctionHead = hasMultilineFunctionHead(node, previousToken);

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
						: (fixer) => fixer.replaceTextRange(
							[previousToken.range[1], openingBrace.range[0]]
							, ''
						)
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
						: (fixer) => fixer.replaceTextRange(
							[previousToken.range[1], openingBrace.range[0]]
							, ''
						)
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
						: (fixer) => fixer.replaceTextRange(
							[previousToken.range[1], openingBrace.range[0]]
							, ' '
						)
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
					: (fixer) => fixer.replaceTextRange(
						[previousToken.range[1], openingBrace.range[0]]
						, '\n'
					)
			});
		}

		function checkBlockLayout(node)
		{
			const open = getOpeningBraceToken(sourceCode, node);
			const owner = node.type === 'SwitchStatement' ? node : node.parent;
			const first = sourceCode.getFirstToken(owner);
			blockIndents.set(open.range[0], getTokenLineIndent(sourceCode, first));

			if(canCompactBody(node, sourceCode, maxLength)) return;

			const close = sourceCode.getLastToken(node);
			const children = node.type === 'SwitchStatement' ? node.cases : node.body;
			const ranges = new Map();

			function separate(left, right)
			{
				if(left.loc.end.line !== right.loc.start.line) return;

				const between = sourceCode.text.slice(left.range[1], right.range[0]);

				if(between.trim()) return;

				ranges.set(left.range[1], [left.range[1], right.range[0]]);
			}

			separate(open, sourceCode.getTokenAfter(open, { includeComments: true }));
			const previous = sourceCode.getTokenBefore(close, { includeComments: true });

			if(!isBlockClose(previous, sourceCode)) separate(previous, close);

			for(const child of children)
			{
				const first = sourceCode.getFirstToken(child);
				separate(sourceCode.getTokenBefore(first, { includeComments: true }), first);
			}

			if(!ranges.size) return;

			context.report({
				node
				, messageId: 'expandedBody'
				, fix: fixer =>
					[...ranges.values()].map(range => fixer.replaceTextRange(range, '\n'))
			});
		}

		function checkAttachedClauses(node)
		{

			function separate(body, keyword)
			{
				const close = sourceCode.getLastToken(body);

				if(!isBlockClose(close, sourceCode) || canCompactBody(body, sourceCode, maxLength))
				{
					return;
				}

				const clause = sourceCode.getTokenAfter(close);
				const previous = sourceCode.getTokenBefore(clause, { includeComments: true });
				const range = [previous.range[1], clause.range[0]];
				const indent = getTokenLineIndent(sourceCode, sourceCode.getFirstToken(node));
				const expected = `\n${indent}`;

				if(sourceCode.text.slice(...range) === expected) return;

				context.report({
					node
					, loc: clause.loc
					, messageId: 'attachedClause'
					, data: { clause: keyword }
					, fix: fixer => fixer.replaceTextRange(range, expected)
				});
			}

			if(node.type === 'IfStatement' && node.alternate)
			{
				separate(node.consequent, 'else');
			}

			if(node.type === 'TryStatement')
			{
				if(node.handler) separate(node.block, 'catch');

				if(node.finalizer) separate(node.handler?.body ?? node.block, 'finally');
			}

			if(node.type === 'DoWhileStatement') separate(node.body, 'while');
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
				, fix: (fixer) => fixer.replaceTextRange(
					[previousToken.range[1], openingBrace.range[0]]
					, between.replace(/[ \t]+\n([ \t]*)$/u, '\n$1')
				)
			});
		}

		function checkClosingDelimiterIndent()
		{
			const stack = [];
			const pairs = new Map();

			for(const token of sourceCode.ast.tokens)
			{
				if(token.type !== 'Punctuator') continue;

				if(['(', '[', '{'].includes(token.value))
				{
					stack.push(token);
					continue;
				}

				if(![')', ']', '}'].includes(token.value)) continue;

				const opening = stack.pop();

				if(!opening) continue;

				pairs.set(token, opening);

				if(opening.loc.start.line === token.loc.start.line) continue;

				const previous = sourceCode.getTokenBefore(token, { includeComments: true });
				const expected = openingIndent(opening);
				const range = [previous.range[1], token.range[0]];
				const gap = sourceCode.text.slice(...range);

				if(isBlockClose(token, sourceCode) && isBlockClose(previous, sourceCode)
					&& !gap.includes('\n')
				) continue;

				const sameRail = pairs.has(previous)
					&& openingIndent(pairs.get(previous)) === expected;

				if(sameRail && !gap.includes('\n')) continue;

				const prefix = sourceCode.lines[token.loc.start.line - 1].slice(
					0
					, token.loc.start.column
				);

				if(prefix === expected) continue;

				if(token.value === '}' && blockIndents.has(opening.range[0])
					&& previous.loc.end.line === token.loc.start.line
				) continue;

				context.report({
					loc: token.loc
					, message: `Closing '${token.value}' should align with its opening rail.`
					, fix: fixer => fixer.replaceTextRange(range, `\n${expected}`)
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
				BlockStatement: checkBlockLayout
				, ClassBody: checkBlockLayout
				, SwitchStatement: checkBlockLayout
			},
			{
				BlockStatement: stripSpaceBeforeOpeningBrace
				, ClassBody: stripSpaceBeforeOpeningBrace
				, SwitchStatement: stripSpaceBeforeOpeningBrace
			},
			{
				IfStatement: checkAttachedClauses
				, TryStatement: checkAttachedClauses
				, DoWhileStatement: checkAttachedClauses
			},
			{
				'Program:exit': checkClosingDelimiterIndent
			},
		);
	}
};
