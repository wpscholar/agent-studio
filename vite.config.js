import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'build/assets',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        admin: 'assets/admin.js',
      },
      output: {
        entryFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
