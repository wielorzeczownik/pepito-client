<h1 align="center">@wielorzeczownik/pepito-client</h1>

<p align="center">
  <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm"/></picture></a> <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm downloads"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/TypeScript-3178C6?style=flat-square&logo=typescript&logoColor=white" alt="TypeScript"/>
</p>

[Pepito API](https://github.com/Clement87/Pepito-API) client in plain TypeScript, no WebAssembly needed. The Rust core compiled to wasm is an explicit opt-in, see [Wasm](#wasm).

```sh
npm install @wielorzeczownik/pepito-client
```

```js
import { Pepito } from '@wielorzeczownik/pepito-client';

const p = new Pepito(localStorage.getItem('pepito') ?? undefined);

await p.refresh();
console.log(p.state); // { state: "away", since, age, healthy, count_in, count_out }

p.addEventListener('in', (e) => console.log('came back', e.detail.img));
p.addEventListener('out', (e) => console.log('left'));
p.addEventListener('change', (e) =>
  localStorage.setItem('pepito', p.snapshot())
);

p.addEventListener('error', (e) => console.warn('reconnecting', e.detail));

p.watch(); // fetch + reconnect + heartbeat watchdog
// p.watch({ url, idleTimeout: 45, maxBackoff: 60 }); the same knobs as in PHP and Rust
// p.stop(); p.close();
```

Your own `fetch`

```js
import { fetch, ProxyAgent } from 'undici';

const proxy = new ProxyAgent('http://127.0.0.1:8080');
const p = new Pepito(snapshot, {
  fetch: (url, init) => fetch(url, { ...init, dispatcher: proxy }),
});
```

## Archive

```js
const stats = Pepito.historyStats(json);
// total, outings, avg_outing_secs, median_outing_secs, by_hour_out/by_hour_in/by_weekday_out
```

## Wasm

The same API backed by the Rust core compiled to wasm, for raw speed on large archives. It has to be loaded with `await init()` first. There is no fallback if the wasm fails to load you get an error, not the TS version running quietly instead.

```js
import { init, Pepito } from '@wielorzeczownik/pepito-client/wasm';

await init();
const stats = Pepito.historyStats(json);
```

Snapshots have the same format in both, so either one restores what the other saved.

More on the architecture of the whole repo (crates + npm + composer together):
see the [README](https://github.com/wielorzeczownik/pepito-client#readme) at the repo root.
