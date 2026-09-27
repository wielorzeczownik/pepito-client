import { defineConfig } from 'vite';

export default defineConfig({
  define: {
    'import.meta.url': 'require("node:url").pathToFileURL(__filename).href',
  },
  build: {
    lib: {
      entry: { pepito: 'src/pepito.ts', 'pepito-wasm': 'src/wasm.ts' },
      formats: ['cjs'],
      fileName: (_format, name) => `${name}.cjs`,
    },
    outDir: 'dist',
    emptyOutDir: false,
    sourcemap: true,
    rollupOptions: {
      external: ['node:fs/promises', 'node:url'],
    },
  },
});
