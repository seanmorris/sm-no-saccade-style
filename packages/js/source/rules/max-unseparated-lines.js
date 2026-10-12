export default {
	meta: {
		type: 'suggestion'
		, schema: [{ type: 'object'
			, properties: { max: { type: 'integer', minimum: 1 } }
			, additionalProperties: false }]
		, messages: { run: '{{count}} consecutive code lines without a blank line; consider separating semantic blocks (limit {{max}}).' }
	}

	, create(context) {
		const max = context.options[0]?.max ?? 10;
		return {

			'Program:exit'() {
				const source = context.sourceCode;
				const ignored = new Set();
				for(const token of [...source.getAllComments(), ...source.ast.tokens])
				{
					if(!['Block', 'Line', 'Template', 'String'].includes(token.type)) continue;
					for(let line = token.loc.start.line; line <= token.loc.end.line; line++)
					{
						const prefix = line === token.loc.start.line ? source.lines[line - 1].slice(
							0, token.loc.start.column) : '';
						const suffix = line === token.loc.end.line ? source.lines[line - 1].slice(
							token.loc.end.column) : '';
						if(!prefix.trim() && !suffix.trim()) ignored.add(line);
					}
				}
				let start = 0;
				let count = 0;

				function flush()
				{
					if(count > max) context.report({ loc: { line: start, column: 0 }
						, messageId: 'run', data: { count, max } });
					count = 0;
				}
				source.lines.forEach((text, index) => {
					if(!text.trim() || ignored.has(index + 1)) return flush();
					if(!count) start = index + 1;
					count++;
				});
				flush();
			}
		};
	}
};
