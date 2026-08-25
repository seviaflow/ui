import { defineConfig } from 'vite';
import path from 'node:path';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public/dist',
    emptyOutDir: true,
    manifest: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'resources/js/seviaflow.js'),
      output: {
        entryFileNames: () => 'seviaflow.js',
        chunkFileNames: 'chunks/[name].js',
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
