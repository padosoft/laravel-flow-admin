import { defineConfig } from 'vite';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  // outDir lives inside Laravel's `public/` tree. Vite's default behaviour is to
  // copy `publicDir` into `outDir`, which would recursively copy `public/` into
  // itself on every build (`public/vendor/flow-admin/vendor/flow-admin/...`).
  // Disable the copy: this package ships its compiled assets to `outDir` and
  // does not maintain a separate static-public source tree.
  publicDir: false,
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
