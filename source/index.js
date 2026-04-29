import stylistic from '@stylistic/eslint-plugin';

import allmanTabs from './rules/allman-tabs.js';
import finalCommaLine from './rules/final-comma-line.js';
import leadingCommaLists from './rules/leading-comma-lists.js';
import leadingOperators from './rules/leading-operators.js';
import noSpaceControlParen from './rules/no-space-control-paren.js';

const plugin = {
	meta: {
		name: 'sm-no-saccade-style',
	},
	rules: {
		'allman-tabs': allmanTabs,
		'final-comma-line': finalCommaLine,
		'leading-comma-lists': leadingCommaLists,
		'leading-operators': leadingOperators,
		'no-space-control-paren': noSpaceControlParen,
	},
	configs: {},
};

plugin.configs.recommended = [
	{
		plugins: {
			'sm-no-saccade-style': plugin,
			'@stylistic': stylistic,
		},
		rules: {
			'sm-no-saccade-style/leading-comma-lists': 'error',
			'sm-no-saccade-style/final-comma-line': ['error', { mode: 'allow' }],
			'sm-no-saccade-style/leading-operators': 'error',
			'sm-no-saccade-style/allman-tabs': 'error',
			'sm-no-saccade-style/no-space-control-paren': 'error',
			'@stylistic/comma-style': ['error', 'first', {
				exceptions: {
					ArrayExpression: true,
					ArrayPattern: true,
					ObjectExpression: true,
					ObjectPattern: true,
				},
			}],
			'@stylistic/operator-linebreak': ['error', 'before', {
				overrides: {
					'?': 'before',
					':': 'before',
				},
			}],
			'@stylistic/eol-last': ['error', 'always'],
			'@stylistic/indent': ['error', 'tab'],
		},
	},
];

export const recommended = plugin.configs.recommended;

export default plugin;
