import { columns, DEFAULT_MAX_LENGTH } from './_layout.js';
import { callLayout } from './_call-layout.js';

export default {
	meta: {
		type: 'layout'
		, fixable: 'whitespace'
		, schema: [{ type: 'object'
			, properties: {
				max: { type: 'integer', minimum: 40 }
				, tabWidth: { type: 'integer', minimum: 1 }
				, ignoreLiterals: { type: 'boolean' }
			}
			, additionalProperties: false
		}]
		, messages: { long:
			'Line is {{length}} columns; limit is {{max}}. Wrap at a logical boundary.'
		}
	}



	, create(context) {
		const { max = DEFAULT_MAX_LENGTH, tabWidth = 4, ignoreLiterals = true } = context.options[
			0
		] ?? {};
		const expanding = new Set();
		const calls = [];

		function block(node)
		{
			if(node.loc.start.line === node.loc.end.line) expanding.add(node.loc.start.line);
		}

		return {
			CallExpression: node => calls.push(node)
			, NewExpression: node => calls.push(node)
			, BlockStatement: block
			, ClassBody: block
			, SwitchStatement: block

			, 'Program:exit'() {
				const source = context.sourceCode;
				const wrapped = new Set();
				let covered = -1;

				calls.sort(
					(left, right) => left.range[0] - right.range[0] || right.range[1] - left.range[
						1
					]
				);

				for(const call of calls)
				{
					const layout = callLayout(source, call, max, tabWidth);

					if(!layout || layout.range[0] < covered) continue;

					covered = layout.range[1];

					for(let line = layout.startLine; line <= layout.endLine; line++) wrapped.add(
						line
					);

					context.report({ node: call
						, message: 'Wrap this call around complete arguments and align its closing parenthesis.'
						, fix: fixer => fixer.replaceTextRange(layout.range, layout.text)
					});
				}

				const excluded = new Set();

				if(ignoreLiterals)
				{
					for(const token of source.ast.tokens)
					{
						if(!['String', 'Template', 'RegularExpression'].includes(
							token.type
						)) continue;

						for(let line = token.loc.start.line; line <= token.loc.end.line; line++)
						{
							if(token.loc.start.line !== token.loc.end.line || columns(
								token.value
								, tabWidth
							) + columns(
								source.lines[line - 1].match(/^[\t ]*/u)[0]
								, tabWidth
							) > max
							){
								excluded.add(line);
							}
						}
					}
				}

				source.lines.forEach((text, index) => {
					const length = columns(text, tabWidth);

					if(length <= max || excluded.has(index + 1) || wrapped.has(index + 1)) return;

					const start = source.getIndexFromLoc({ line: index + 1, column: 0 });
					const indent = text.match(/^[\t ]*/u)[0] + '\t';
					let cut = null;

					for(const token of source.ast.tokens)
					{
						if(token.loc.start.line !== index + 1 || token.loc.end.line !== index
							+ 1
						) continue;

						let position;

						if([',', '&&', '||', '??', '.', '?.'].includes(token.value))
						{
							position = token.range[0];
						}
						else if(['(', '[', '=', '=>', ';', ':'].includes(token.value))
						{
							position = token.range[1];
						}
						else continue;

						const prefix = source.text.slice(start, position);

						if(prefix.trim() && columns(prefix, tabWidth) <= max) cut = position;
					}

					if(expanding.has(index + 1)) cut = null;

					let range = null;

					if(cut !== null)
					{
						let left = cut;
						let right = cut;

						while(left > start && /[\t ]/u.test(source.text[left - 1])) left--;

						while(/[\t ]/u.test(source.text[right] ?? '\n')) right++;

						if(right < start + text.length) range = [left, right];
					}

					context.report({ loc: { line: index + 1, column: 0 }
						, messageId: 'long', data: { length, max }
						, fix: range ? fixer => fixer.replaceTextRange(
							range
							, `\n${indent}`
						) : null
					});
				});
			}
		};
	}
};
