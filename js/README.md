<h1 align="center">@wielorzeczownik/pepito-client</h1>

<p align="center">
  <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm"/></picture></a> <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm downloads"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/TypeScript-3178C6?style=flat-square&logo=typescript&logoColor=white" alt="TypeScript"/>
</p>

<p align="center">
  <img src="https://raw.githubusercontent.com/wielorzeczownik/pepito-client/main/assets/logo.svg" alt="pepito-client logo" width="200" />
</p>

A [Pepito API](https://github.com/Clement87/Pepito-API) client in plain TypeScript. It needs no WebAssembly. The Rust core compiled to wasm is available as an opt-in, see [Wasm](#wasm).

```sh
npm install @wielorzeczownik/pepito-client
```

```js
import { Pepito } from '@wielorzeczownik/pepito-client';

const p = new Pepito({
  snapshot: localStorage.getItem('pepito') ?? undefined,
});

await p.refresh();
console.log(p.state); // { state: "away", since, age, healthy, count_in, count_out }

p.addEventListener('in', (e) => console.log('came back', e.detail.img));
p.addEventListener('out', () => console.log('left'));
p.addEventListener('change', () =>
  localStorage.setItem('pepito', p.snapshot())
);
p.addEventListener('error', (e) => console.warn('reconnecting', e.detail));

p.watch(); // stream, reconnect and heartbeat checks, until p.stop()
```

`watch()` takes `{ url, idleTimeout, maxBackoff }` (seconds, defaults `45` and `60`), the same options as in PHP and Rust.

To route requests through a proxy or anything else, pass your own `fetch`:

```js
import { fetch, ProxyAgent } from 'undici';

const proxy = new ProxyAgent('http://127.0.0.1:8080');
const p = new Pepito({
  snapshot,
  fetch: (url, init) => fetch(url, { ...init, dispatcher: proxy }),
});
```

## Archive

```js
const stats = Pepito.historyStats(json);
// total, outings, avg_outing_secs, median_outing_secs, by_hour_out, by_hour_in, by_weekday_out
```

## Wasm

The same API on top of the Rust core, faster on large archives. Call `await init()` before using it.

```js
import { init, Pepito } from '@wielorzeczownik/pepito-client/wasm';

await init();
const stats = Pepito.historyStats(json);
```

If the wasm fails to load you get an error. It doesn't fall back to the TypeScript version. Snapshots are the same in both, so either one restores what the other saved.

This is one of three packages built from the same repository. See the [main README](https://github.com/wielorzeczownik/pepito-client#readme) for how they fit together.
