import stylistic from '@stylistic/eslint-plugin';

import { withOptions } from './_compose.js';

const baseRule = stylistic.rules['operator-linebreak'];
const defaultOptions = [
	'before'
	, {
		overrides: {
			'?': 'before'
			, ':': 'before'
		}
	}
];

export default {
	...baseRule,
	meta: {
		...baseRule.meta,
		docs: {
			...baseRule.meta.docs,
			description: 'Enforce operator-first continuation lines.'
		}
	}
	, create(context)
	{
		const effectiveOptions = context.options.length > 0 ? context.options : defaultOptions;

		return baseRule.create(withOptions(context, effectiveOptions));
	}

};
