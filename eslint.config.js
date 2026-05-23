/**
 * ESLint flat configuration.
 *
 * Extends the @wordpress/scripts default config and adds explicit *.jsx
 * file support so JSX components are linted alongside *.js files.
 *
 * @see https://eslint.org/docs/latest/use/configure/configuration-files
 * @since 0.2.0
 */

const wpPlugin = require('@wordpress/eslint-plugin');

module.exports = [
	// Global ignores.
	{
		ignores: [
			'**/build/**',
			'**/node_modules/**',
			'**/vendor/**',
			'**/dist/**',
		],
	},

	// Base recommended rules from @wordpress/eslint-plugin (covers *.js).
	...wpPlugin.configs.recommended,

	// Extend linting to *.jsx files with the same recommended rule set.
	{
		files: ['src/**/*.jsx'],
		languageOptions: {
			parserOptions: {
				ecmaFeatures: {
					jsx: true,
				},
			},
		},
	},
];
