import {
	getLineStartIndex,
	getListItems,
	getTokenLineIndent,
	hasCommentsBetween,
	isFirstTokenOnLine,
} from './_list-utils.js';

const DEFAULT_OPTIONS = {
	mode: 'allow'

};

const SUPPORTED_TYPES = new Set([
	'ArrayExpression'
	, 'ArrayPattern'
	, 'ObjectExpression'
	, 'ObjectPattern'

]);

function buildFinalCommaLineFix(sourceCode, fixer, commaToken, closerToken, anchorToken)
{
	const itemIndent = getTokenLineIndent(sourceCode, anchorToken);
	const insertText = `${itemIndent},\n`;

	return [
		fixer.remove(commaToken)
		, fixer.insertTextBefore(closerToken, insertText)
	];
}

function buildInsertFinalCommaFix(sourceCode, fixer, closerToken, anchorToken)
{
	const itemIndent = getTokenLineIndent(sourceCode, anchorToken);
	const insertText = `${itemIndent},\n`;

	return fixer.insertTextBefore(closerToken, insertText);
}

function buildRemoveFinalCommaLineFix(sourceCode, fixer, commaToken)
{
	const lineStartIndex = getLineStartIndex(sourceCode, commaToken.loc.start.line);
	let lineEndIndex = lineStartIndex + sourceCode.lines[commaToken.loc.start.line - 1].length;

	if(sourceCode.text[lineEndIndex] === '\n')
	{
		lineEndIndex += 1;
	}

	return fixer.removeRange([lineStartIndex, lineEndIndex]);
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'code'
		, schema: [
			{
				type: 'object'
				, properties: {
					mode: {
						enum: ['allow', 'require', 'forbid']
					}
				}
				, additionalProperties: false
			}
		]
		, messages: {
			expectedFinalCommaLine: 'Final comma must be on its own line before the closing delimiter.'
			, unexpectedFinalCommaLine: 'Final comma-only line is not allowed.'
		}
	}
	, create(context)
	{
		const sourceCode = context.sourceCode;
		const options = { ...DEFAULT_OPTIONS, ...(context.options[0] || {}) };

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

			const closerToken = sourceCode.getLastToken(node);
			const lastItemToken = sourceCode.getLastToken(items[items.length - 1]);
			const tokenBeforeCloser = sourceCode.getTokenBefore(closerToken);

			if(tokenBeforeCloser && tokenBeforeCloser.value === ',')
			{
				const commaIsOwnLine
					= tokenBeforeCloser.loc.start.line < closerToken.loc.start.line
					&& isFirstTokenOnLine(sourceCode, tokenBeforeCloser);

				if(!commaIsOwnLine)
				{
					const canFix
						= tokenBeforeCloser.loc.start.line === lastItemToken.loc.end.line
						&& !hasCommentsBetween(sourceCode, tokenBeforeCloser, closerToken);

					context.report({
						node: items[items.length - 1]
						, loc: tokenBeforeCloser.loc
						, messageId:
							options.mode === 'forbid'
								? 'unexpectedFinalCommaLine'
								: 'expectedFinalCommaLine'
						, fix: canFix
							? (fixer) => buildFinalCommaLineFix(sourceCode, fixer, tokenBeforeCloser, closerToken, lastItemToken)
							: null
					});

					return;
				}

				if(options.mode === 'forbid')
				{
					context.report({
						node: items[items.length - 1]
						, loc: tokenBeforeCloser.loc
						, messageId: 'unexpectedFinalCommaLine'
						, fix: (fixer) => buildRemoveFinalCommaLineFix(sourceCode, fixer, tokenBeforeCloser)
					});
				}

				return;
			}

			if(options.mode === 'require' && closerToken.loc.start.line > lastItemToken.loc.end.line)
			{
				context.report({
					node: closerToken
					, loc: closerToken.loc
					, messageId: 'expectedFinalCommaLine'
					, fix: (fixer) => buildInsertFinalCommaFix(sourceCode, fixer, closerToken, lastItemToken)
				});
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
