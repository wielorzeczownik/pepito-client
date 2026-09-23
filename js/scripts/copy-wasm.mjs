import { log } from 'node:console';
import { cp, mkdir } from 'node:fs/promises';

await mkdir(new URL('../dist/wasm/', import.meta.url), { recursive: true });
for (const file of ['pepito.js', 'pepito_bg.wasm', 'pepito.d.ts']) {
  await cp(
    new URL(`../src/wasm/${file}`, import.meta.url),
    new URL(`../dist/wasm/${file}`, import.meta.url)
  );
}
log('wasm copied to dist/wasm/');
