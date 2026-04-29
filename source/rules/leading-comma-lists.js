import {
	getListItems,
	hasCommentsBetween,
	isFirstTokenOnLine,
} from './_list-utils.js';

const SUPPORTED_TYPES = new Set([
	'ArrayExpression'
	, 'ArrayPattern'
	, 'ObjectExpression'
	, 'ObjectPattern'

]);

const MAX_GROUPED_ROW_COLUMN = 80;

function buildMoveCommaFix(sourceCode, fixer, commaToken, itemToken)
{
	/* c8 ignore next 4 */
	if(!isFirstTokenOnLine(sourceCode, itemToken))
	{
		return null;
	}

	return [
		fixer.remove(commaToken)
		, fixer.insertTextBefore(itemToken, ', ')
	];
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'code'
		, schema: []
		, messages: {
			expectedLeadingComma: 'Comma should be at the beginning of the line for multiline list items.'
			, unexpectedTrailingComma: 'Trailing comma should not stay on the previous item line in a multiline list.'
			, groupedRowTooWide: `Grouped multiline list rows should stay within column ${MAX_GROUPED_ROW_COLUMN}.`
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;

		function isGroupedArrayPair(node, leftToken, rightToken)
		{
			return (
				(node.type === 'ArrayExpression' || node.type === 'ArrayPattern')
				&& leftToken.loc.end.line === rightToken.loc.start.line
			);
		}

		function checkGroupedArrayRows(node, items)
		{
			if(node.type !== 'ArrayExpression' && node.type !== 'ArrayPattern')
			{
				return;
			}

			const lines = new Map();

			for(const item of items)
			{
				const token = sourceCode.getFirstToken(item);
				const line = token.loc.start.line;

				if(!lines.has(line))
				{
					lines.set(line, []);
				}

				lines.get(line).push(token);
			}

			for(const [lineNumber, lineTokens] of lines)
			{
				if(lineTokens.length < 2)
				{
					continue;
				}

				const line = sourceCode.lines[lineNumber - 1];

				if(line.length <= MAX_GROUPED_ROW_COLUMN)
				{
					continue;
				}

				context.report({
					loc: {
						start: { line: lineNumber, column: 0 }
						, end: { line: lineNumber, column: line.length }
					}
					, messageId: 'groupedRowTooWide'
				});
			}
		}

		function checkNode(node)
		{
			if(!SUPPORTED_TYPES.has(node.type) || node.loc.start.line === node.loc.end.line)
			{
				return;
			}

			const items = getListItems(node);

			if(items.length === 0)
			{
				return;
			}

			checkGroupedArrayRows(node, items);

			for(let i = 1; i < items.length; i += 1)
			{
				const itemToken = sourceCode.getFirstToken(items[i]);
				const commaToken = sourceCode.getTokenBefore(itemToken);

				/* c8 ignore next 4 */
				if(!commaToken || commaToken.value !== ',')
				{
					continue;
				}

				if(isGroupedArrayPair(node, commaToken, itemToken))
				{
					continue;
				}

				const commaOnItemLine = commaToken.loc.start.line === itemToken.loc.start.line;
				const commaLeading = isFirstTokenOnLine(sourceCode, commaToken);

				if(!commaOnItemLine || !commaLeading)
				{
					const canFix
						= commaToken.loc.start.line !== itemToken.loc.start.line
						&& !hasCommentsBetween(sourceCode, commaToken, itemToken);

					context.report({
						node: items[i]
						, loc: commaToken.loc
						, messageId: 'expectedLeadingComma'
						, fix: canFix
							? (fixer) => buildMoveCommaFix(sourceCode, fixer, commaToken, itemToken)
							: null
					});
				}
			}

			for(let i = 0; i < items.length - 1; i += 1)
			{
				const itemToken = sourceCode.getLastToken(items[i]);
				const commaToken = sourceCode.getTokenAfter(itemToken);
				const nextItemToken = sourceCode.getFirstToken(items[i + 1]);

				/* c8 ignore next 4 */
				if(!commaToken || commaToken.value !== ',')
				{
					continue;
				}

				if(isGroupedArrayPair(node, itemToken, nextItemToken))
				{
					continue;
				}

				if(commaToken.loc.start.line === itemToken.loc.end.line)
				{
					const canFix
						= !hasCommentsBetween(sourceCode, commaToken, nextItemToken)
						&& isFirstTokenOnLine(sourceCode, nextItemToken);

					context.report({
						node: items[i]
						, loc: commaToken.loc
						, messageId: 'unexpectedTrailingComma'
						, fix: canFix
							? (fixer) => buildMoveCommaFix(sourceCode, fixer, commaToken, nextItemToken)
							: null
					});
				}
			}
		}

		return {
			ArrayExpression: checkNode
			, ArrayPattern: checkNode
			, ObjectExpression: checkNode
			, ObjectPattern: checkNode
		};
	}
};
