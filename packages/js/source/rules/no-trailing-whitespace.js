function getLineStartIndices(sourceCode)
{
	const starts = [];
	let index = 0;

	for(const line of sourceCode.lines)
	{
		starts.push(index);
		index += line.length + 1;
	}

	return starts;
}

function isInsideTemplateString(start, end, templateRanges)
{
	return templateRanges.some((range) => start >= range[0] && end <= range[1]);
}

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: []
		, messages: {
			unexpectedTrailingWhitespace: 'Trailing whitespace is not allowed outside strings.'
		}
	}
	, create(context) {
		const sourceCode = context.sourceCode;
		const templateRanges = [];

		return {
			TemplateElement(node) {
				templateRanges.push(node.range);
			}
			, 'Program:exit'() {
				const lineStarts = getLineStartIndices(sourceCode);

				for(let lineIndex = 0; lineIndex < sourceCode.lines.length; lineIndex += 1)
				{
					const line = sourceCode.lines[lineIndex];
					const match = line.match(/[ \t]+$/u);

					if(!match)
					{
						continue;
					}

					const start = lineStarts[lineIndex] + match.index;
					const end = lineStarts[lineIndex] + line.length;

					if(isInsideTemplateString(start, end, templateRanges))
					{
						continue;
					}

					context.report({
						loc: {
							start: {
								line: lineIndex + 1
								, column: match.index
							}
							, end: {
								line: lineIndex + 1
								, column: line.length
							}
						}
						, messageId: 'unexpectedTrailingWhitespace'
						, fix: (fixer) => fixer.removeRange([start, end])
					});
				}
			}
		};
	}
};
