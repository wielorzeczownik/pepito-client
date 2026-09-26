import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    lib: {
      entry: 'src/wasm.ts',
      formats: ['es'],
      fileName: () => 'pepito-wasm.esm.js',
    },
    outDir: 'dist',
    emptyOutDir: false,
    sourcemap: true,
    rollupOptions: {
      external: ['node:fs/promises'],
    },
  },
});
