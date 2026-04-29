import stylistic from '@stylistic/eslint-plugin';
import tsParser from '@typescript-eslint/parser';

import allmanTabs from './rules/allman-tabs.js';
import { indentOptions } from './rules/_indent-options.js';
import finalCommaLine from './rules/final-comma-line.js';
import leadingCommaLists from './rules/leading-comma-lists.js';
import leadingOperators from './rules/leading-operators.js';
import noDoubleClosingGap from './rules/no-double-closing-gap.js';
import noSpaceControlParen from './rules/no-space-control-paren.js';
import noTrailingWhitespace from './rules/no-trailing-whitespace.js';

const plugin = {
	meta: {
		name: 'sm-no-saccade-style',
	},
	rules: {
		'allman-tabs': allmanTabs,
		'final-comma-line': finalCommaLine,
		'leading-comma-lists': leadingCommaLists,
		'leading-operators': leadingOperators,
		'no-double-closing-gap': noDoubleClosingGap,
		'no-space-control-paren': noSpaceControlParen,
		'no-trailing-whitespace': noTrailingWhitespace,
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
			'sm-no-saccade-style/final-comma-line': ['error', { mode: 'forbid' }],
			'sm-no-saccade-style/leading-operators': 'error',
			'sm-no-saccade-style/allman-tabs': 'error',
			'sm-no-saccade-style/no-double-closing-gap': 'error',
			'sm-no-saccade-style/no-space-control-paren': 'error',
			'sm-no-saccade-style/no-trailing-whitespace': 'error',
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
		},
	},
	{
		files: ['**/*.ts', '**/*.tsx', '**/*.mts', '**/*.cts'],
		languageOptions: {
			parser: tsParser,
		},
	},
];

export const recommended = plugin.configs.recommended;

export default plugin;
