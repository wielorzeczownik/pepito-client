<h1 align="center">@wielorzeczownik/pepito-client</h1>

<p align="center">
  <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm"/></picture></a> <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/dm/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm downloads"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/TypeScript-3178C6?style=flat-square&logo=typescript&logoColor=white" alt="TypeScript"/>
</p>

[Pepito API](https://github.com/Clement87/Pepito-API) client: a Rust core compiled to wasm (wasm-bindgen), with transport (fetch, reconnect, `EventTarget`) written in TS.

```sh
npm install @wielorzeczownik/pepito-client
```

```js
import { init, Pepito } from '@wielorzeczownik/pepito-client';

await init();
const p = new Pepito(localStorage.getItem('pepito') ?? undefined);

await p.refresh();
console.log(p.state); // { state: "away", since, age, healthy, count_in, count_out }

p.addEventListener('in', (e) => console.log('came back', e.detail.img));
p.addEventListener('out', (e) => console.log('left'));
p.addEventListener('change', (e) =>
  localStorage.setItem('pepito', p.snapshot())
);

p.watch(); // fetch + reconnect + heartbeat watchdog
// p.stop(); p.close();
```

## Archive

```js
const stats = Pepito.historyStats(json);
// total, outings, avg_outing_secs, median_outing_secs, by_hour_out/by_hour_in/by_weekday_out
```

`Pepito.historyStats(json)` processes a 5 MB archive in about 40 ms.

More on the architecture of the whole repo (crates + npm + composer together):
see the [README](https://github.com/wielorzeczownik/pepito-client#readme) at the repo root.
