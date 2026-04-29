import {
	getTokenLineIndent,
	hasCommentsBetween,
} from './_list-utils.js';

const OPENING_TOKENS = new Set(['(', '{', '[']);
const CLOSING_TOKENS = new Set([')', '}', ']']);

function isOpeningToken(token)
{
	return OPENING_TOKENS.has(token.value);
}

function isClosingToken(token)
{
	return CLOSING_TOKENS.has(token.value);
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: []
		, messages: {
			unexpectedDelimiterGap: 'Unexpected extra blank lines between structural delimiters.'
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;

		return {
			'Program:exit'() {
				const tokens = sourceCode.getTokens(sourceCode.ast);

				for(let i = 0; i < tokens.length - 1; i += 1)
				{
					const leftToken = tokens[i];
					const rightToken = tokens[i + 1];

					const closeClose = isClosingToken(leftToken) && isClosingToken(rightToken);
					const openOpen = isOpeningToken(leftToken) && isOpeningToken(rightToken);
					const closeOpen = isClosingToken(leftToken) && isOpeningToken(rightToken);

					if(!closeClose && !openOpen && !closeOpen)
					{
						continue;
					}

					const allowedGap = closeOpen ? 2 : 1;

					if(leftToken.loc.end.line + allowedGap >= rightToken.loc.start.line)
					{
						continue;
					}

					if(hasCommentsBetween(sourceCode, leftToken, rightToken))
					{
						continue;
					}

					const rightIndent = getTokenLineIndent(sourceCode, rightToken);
					const replacement = closeOpen
						? `\n\n${rightIndent}`
						: `\n${rightIndent}`;

					context.report({
						loc: {
							start: leftToken.loc.end
							, end: rightToken.loc.start
						}
						, messageId: 'unexpectedDelimiterGap'
						, fix: (fixer) => fixer.replaceTextRange(
							[leftToken.range[1], rightToken.range[0]]
							, replacement
						)
					});
				}
			}
		};
	}
};
