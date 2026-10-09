import prettier from 'eslint-config-prettier';

const browser = {
	window: 'readonly',
	document: 'readonly',
	fetch: 'readonly',
	navigator: 'readonly',
	setTimeout: 'readonly',
};

const node = {
	process: 'readonly',
	console: 'readonly',
};

export default [
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'build/**',
			'dist/**',
			'coverage/**',
			'playwright-report/**',
			'test-results/**',
		],
	},
	{
		files: ['assets/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: browser,
		},
		rules: { 'no-unused-vars': 'error', 'no-undef': 'error', eqeqeq: 'error' },
	},
	{
		// The admin app: ES modules with JSX, built by Vite against WordPress's own React.
		files: ['assets/src/**/*.{js,jsx}'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			parserOptions: { ecmaFeatures: { jsx: true } },
			globals: {
				...browser,
				Blob: 'readonly',
				URL: 'readonly',
				URLSearchParams: 'readonly',
				FileReader: 'readonly',
				clearTimeout: 'readonly',
				Intl: 'readonly',
				history: 'readonly',
				location: 'readonly',
				confirm: 'readonly',
			},
		},
		rules: { 'no-unused-vars': 'error', 'no-undef': 'error', eqeqeq: 'error' },
	},
	{
		files: ['*.js', '*.mjs', 'scripts/**/*.mjs'],
		languageOptions: { ecmaVersion: 2022, sourceType: 'module', globals: node },
		rules: { 'no-unused-vars': 'error', 'no-undef': 'error' },
	},
	prettier,
];
