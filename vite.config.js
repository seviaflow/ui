import { defineConfig } from 'vite';
import path from 'node:path';

export default defineConfig({
  publicDir: 'resources/public',
  define: {
    __SEVIAFLOW_TEST_RICH_TEXT_FAILURE__: JSON.stringify(process.env.SEVIAFLOW_TEST_RICH_TEXT_FAILURE === '1'),
  },
  build: {
    outDir: 'public/dist',
    emptyOutDir: true,
    manifest: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'resources/js/seviaflow.js'),
      output: {
        entryFileNames: () => 'seviaflow.js',
        chunkFileNames: 'chunks/[name].js',
        inlineDynamicImports: true,
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name ?? '';

          if (name.endsWith('.css')) {
            return 'seviaflow.css';
          }

          return '[name][extname]';
        },
      },
    },
  },
});
