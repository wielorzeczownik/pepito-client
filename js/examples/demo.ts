// node examples/demo.ts            state + statistics from the archive
// node examples/demo.ts watch      the live stream
import { log } from 'node:console';
import { readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';

import { ARCHIVE_URL, Pepito } from '../dist/pepito.js';

const cacheFile = path.join(tmpdir(), 'pepito.json');

let snapshot: string | undefined;
try {
  snapshot = await readFile(cacheFile, 'utf8');
} catch {
  // no cache yet
}
const pepito = new Pepito(snapshot);
const save = () => writeFile(cacheFile, pepito.snapshot());

if (process.argv[2] === 'watch') {
  pepito.addEventListener('change', (event) => {
    const update = event.detail;
    if (update.kind !== 'sighting') {
      return;
    }

    log(
      `${new Date(update.time * 1000).toLocaleTimeString()} -> ${update.way}`
    );
    void save();
  });
  await pepito.watch();
}

let state = pepito.state;
if (state.state === 'unknown' || (state.age ?? Infinity) > 300) {
  await pepito.refresh();
  await save();
  state = pepito.state;
}
log(
  `the cat is ${state.state} since ${state.since ? new Date(state.since * 1000).toISOString() : 'unknown'}`
);

const archiveResponse = await fetch(ARCHIVE_URL);
const archive = await archiveResponse.text();
const stats = Pepito.historyStats(archive);
log(
  `archive: ${stats.total} events, ${stats.outings} outings, median ${Math.round(stats.median_outing_secs / 60)} min`
);
