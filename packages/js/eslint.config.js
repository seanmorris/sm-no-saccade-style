import smNoSaccadeStyle from './source/index.js';

export default [
	...smNoSaccadeStyle.configs.recommended
	, {
		files: ['index.js', 'source/**/*.js', 'eslint.config.js']
		, languageOptions: {
			ecmaVersion: 'latest'
			, sourceType: 'module'
		}
	}
];
