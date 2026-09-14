import wordpress from '@wordpress/eslint-plugin';

export default [
	{
		ignores: ['build/**', 'node_modules/**', 'vendor/**'],
	},
	...wordpress.configs.recommended,
];
