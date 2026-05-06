import { defineConfig } from 'vite';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  build: {
    outDir: 'public/vendor/flow-admin',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        admin: resolve(here, 'resources/js/admin.js'),
        styles: resolve(here, 'resources/css/admin.css'),
      },
    },
  },
});
