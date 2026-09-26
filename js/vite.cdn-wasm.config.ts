import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    lib: {
      entry: 'src/wasm.ts',
      name: 'PepitoWasm',
      formats: ['es', 'umd'],
      fileName: (format) =>
        format === 'es' ? 'pepito-wasm.esm.js' : 'pepito-wasm.umd.js',
    },
    outDir: 'dist',
    emptyOutDir: false,
    sourcemap: true,
    rollupOptions: {
      external: ['node:fs/promises'],
    },
  },
});
