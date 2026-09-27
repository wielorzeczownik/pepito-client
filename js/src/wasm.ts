// The Rust core compiled to wasm, as an explicit opt-in. Same API as the main
// entry plus init()

import { InvalidArchiveError, NotInitializedError } from './errors.js';
import { type Backend, Pepito as PurePepito } from './pepito.js';
import type { Sighting, Stats } from './types.js';
import wasmInit, {
  historyParse,
  historyStats,
  Tracker as WasmTracker,
} from './wasm/pepito.js';

export * from './pepito.js';

const wasmModule = { loaded: false };

// UMD build has no import.meta, so remember the script's URL while it loads
// (currentScript is gone by the time init() runs).
const script =
  typeof document === 'undefined'
    ? undefined
    : (document.currentScript as HTMLScriptElement | null);
const scriptUrl = script?.src;

function ready(): void {
  if (!wasmModule.loaded) {
    throw new NotInitializedError('await init() first');
  }
}

/**
 * wasm-bindgen throws the Rust error as a bare string.
 */
function archive<T>(parse: () => unknown): T {
  ready();
  try {
    return parse() as T;
  } catch (error) {
    throw new InvalidArchiveError(String(error));
  }
}

const wasm: Backend = {
  Tracker: class extends WasmTracker {
    constructor(snapshot?: string) {
      ready();
      super(snapshot);
    }
  },
  historyStats: (json) => archive<Stats>(() => historyStats(json)),
  historyParse: (json) => archive<Sighting[]>(() => historyParse(json)),
};

/**
 * Loads the wasm module. It has to finish before the first `Pepito` is built.
 */
export async function init(source?: Uint8Array | URL): Promise<void> {
  if (wasmModule.loaded) {
    return;
  }
  let input: Uint8Array | URL =
    source ?? new URL('wasm/pepito_bg.wasm', import.meta.url ?? scriptUrl);
  if (input instanceof URL && input.protocol === 'file:') {
    const nodeFs = 'node:fs/promises';
    const { readFile } = (await import(
      /* webpackIgnore: true */ /* @vite-ignore */ nodeFs
    )) as typeof import('node:fs/promises');
    input = new Uint8Array(await readFile(input));
  }
  await wasmInit({ module_or_path: input });
  wasmModule.loaded = true;
}

/**
 * `Pepito` backed by the wasm build. `close()` frees its memory.
 */
export class Pepito extends PurePepito {
  protected static override backend = wasm;
}
