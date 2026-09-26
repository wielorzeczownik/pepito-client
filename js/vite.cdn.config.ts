import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    lib: {
      entry: 'src/pepito.ts',
      name: 'Pepito',
      formats: ['es', 'umd'],
      fileName: (format) =>
        format === 'es' ? 'pepito.esm.js' : 'pepito.umd.js',
    },
    outDir: 'dist',
    emptyOutDir: false,
    sourcemap: true,
  },
});
