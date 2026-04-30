import {
	getListItems,
	getLineIndent,
	getTokenLineIndent,
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
const SPACED_FINAL_TYPES = new Set([
	'ArrayExpression'
	, 'ArrowFunctionExpression'
	, 'FunctionExpression'
	, 'ObjectExpression'
]);

function buildMoveCommaFix(sourceCode, fixer, commaToken, itemToken)
{
	if(commaToken.loc.start.line === itemToken.loc.start.line)
	{
		return fixer.replaceTextRange(
			[commaToken.range[0], itemToken.range[0]]
			, `\n${getTokenLineIndent(sourceCode, itemToken)}, `
		);
	}

	return [
		fixer.remove(commaToken)
		, fixer.insertTextBefore(itemToken, ', ')
	];
}

function buildLeadingCommaSpacingFix(fixer, commaToken, itemToken)
{
	return fixer.replaceTextRange([commaToken.range[1], itemToken.range[0]], ' ');
}

function buildWrapGroupedRowFix(sourceCode, fixer, lineItems)
{
	const firstItemToken = sourceCode.getFirstToken(lineItems[0]);
	const previousToken = sourceCode.getTokenBefore(firstItemToken);
	const hasLeadingComma = previousToken?.value === ',' && previousToken.loc.start.line === firstItemToken.loc.start.line;
	const rowStartToken = hasLeadingComma ? previousToken : firstItemToken;
	const rowEndToken = sourceCode.getLastToken(lineItems.at(-1));

	if(hasCommentsBetween(sourceCode, rowStartToken, rowEndToken))
	{
		return null;
	}

	const indent = getLineIndent(sourceCode, rowStartToken);
	const replacement = lineItems
		.map((item, index) => {
			const text = sourceCode.getText(item);

			if(index === 0 && !hasLeadingComma)
			{
				return text;
			}

			return `, ${text}`;
		})
		.join(`\n${indent}`);

	return fixer.replaceTextRange([rowStartToken.range[0], rowEndToken.range[1]], replacement);
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'code'
		, schema: []
		, messages: {
			expectedLeadingComma: 'Comma should be at the beginning of the line for multiline list items.'
			, unexpectedTrailingComma: 'Trailing comma should not stay on the previous item line in a multiline list.'
			, expectedSpaceAfterLeadingComma: 'Leading comma must be followed by a space.'
			, groupedRowTooWide: `Grouped multiline list rows should stay within column ${MAX_GROUPED_ROW_COLUMN}.`
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;

		function isGroupedRowPair(node, leftToken, rightToken)
		{
			return SUPPORTED_TYPES.has(node.type) && leftToken.loc.end.line === rightToken.loc.start.line;
		}

		function isSingleLineNode(node)
		{
			return node.loc.start.line === node.loc.end.line;
		}

		function isAllowedCompactPair(node, leftItem, rightItem, leftToken, rightToken)
		{
			return isGroupedRowPair(node, leftToken, rightToken)
				&& isSingleLineNode(leftItem)
				&& isSingleLineNode(rightItem);
		}

		function isAllowedSpacedFinalItem(items, index)
		{
			if(index !== items.length - 1)
			{
				return false;
			}

			const previousItem = items[index - 1];

			return SPACED_FINAL_TYPES.has(items[index].type)
				&& index > 0
				&& isSingleLineNode(previousItem)
				&& previousItem.loc.end.line === items[index].loc.start.line;
		}

		function checkGroupedRows(node, items)
		{
			const lines = new Map();

			for(const item of items)
			{
				const token = sourceCode.getFirstToken(item);
				const line = token.loc.start.line;

				if(!lines.has(line))
				{
					lines.set(line, []);
				}

				lines.get(line).push(item);
			}

			for(const [lineNumber, lineItems] of lines)
			{
				if(lineItems.length < 2)
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
					, fix: (fixer) => buildWrapGroupedRowFix(sourceCode, fixer, lineItems)
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

			checkGroupedRows(node, items);

			for(let i = 1; i < items.length; i += 1)
			{
				const itemToken = sourceCode.getFirstToken(items[i]);
				const commaToken = sourceCode.getTokenBefore(itemToken);

				/* c8 ignore next 4 */
				if(!commaToken || commaToken.value !== ',')
				{
					continue;
				}

				if((isAllowedCompactPair(node, items[i - 1], items[i], commaToken, itemToken) && !isFirstTokenOnLine(sourceCode, commaToken))
					|| isAllowedSpacedFinalItem(items, i)) {
					continue;
					}

				const commaOnItemLine = commaToken.loc.start.line === itemToken.loc.start.line;
				const commaLeading = isFirstTokenOnLine(sourceCode, commaToken);

				if(!commaOnItemLine || !commaLeading)
				{
					const canFix
						= !hasCommentsBetween(sourceCode, commaToken, itemToken);

					context.report({
						node: items[i]
						, loc: commaToken.loc
						, messageId: 'expectedLeadingComma'
						, fix: canFix
							? (fixer) => buildMoveCommaFix(sourceCode, fixer, commaToken, itemToken)
							: null
					});

					continue;
				}

				const spacingText = sourceCode.text.slice(commaToken.range[1], itemToken.range[0]);

				if(spacingText !== ' ')
				{
					context.report({
						node: items[i]
						, loc: commaToken.loc
						, messageId: 'expectedSpaceAfterLeadingComma'
						, fix: hasCommentsBetween(sourceCode, commaToken, itemToken)
							? null
							: (fixer) => buildLeadingCommaSpacingFix(fixer, commaToken, itemToken)
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

				if(isAllowedCompactPair(node, items[i], items[i + 1], itemToken, nextItemToken) || isAllowedSpacedFinalItem(items, i + 1))
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
