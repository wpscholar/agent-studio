import { defineConfig } from 'vite';

// WordPress already ships React and its components; use those instead of bundling copies.
const wordpress = {
  '@wordpress/element': 'wp.element',
  '@wordpress/components': 'wp.components',
  '@wordpress/i18n': 'wp.i18n',
  '@wordpress/api-fetch': 'wp.apiFetch',
};

export default defineConfig({
  oxc: {
    jsx: {
      runtime: 'classic',
      pragma: 'wp.element.createElement',
      pragmaFrag: 'wp.element.Fragment',
    },
  },
  build: {
    outDir: 'build/assets',
    emptyOutDir: true,
    rollupOptions: {
      input: { admin: 'assets/src/index.jsx' },
      external: Object.keys(wordpress),
      output: {
        format: 'iife',
        globals: wordpress,
        entryFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
