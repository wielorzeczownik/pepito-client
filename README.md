<h1 align="center">pepito-client</h1>

<p align="center">
  <a href="https://github.com/wielorzeczownik/pepito-client/actions/workflows/release.yml"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/actions/workflow/status/wielorzeczownik/pepito-client/release.yml?branch=main&style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/github/actions/workflow/status/wielorzeczownik/pepito-client/release.yml?branch=main&style=flat-square&color=2ea043"/><img src="https://img.shields.io/github/actions/workflow/status/wielorzeczownik/pepito-client/release.yml?branch=main&style=flat-square&labelColor=2d333b&color=3fb950" alt="release"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/releases/latest"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/v/release/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/github/v/release/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/github/v/release/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Latest Release"/></picture></a> <a href="https://crates.io/crates/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="crates.io"/></picture></a> <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm"/></picture></a> <a href="https://packagist.org/packages/wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Packagist"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/Rust-B7410E?style=flat-square&logo=rust&logoColor=white" alt="Rust"/>
  <img src="https://img.shields.io/badge/TypeScript-3178C6?style=flat-square&logo=typescript&logoColor=white" alt="TypeScript"/>
  <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP"/>
</p>

[Pepito API](https://github.com/Clement87/Pepito-API) client for Rust (crate), JS/TS (npm) and PHP (Composer). The npm and Composer packages are plain TypeScript and plain PHP by default, with nothing native to install. The Rust core is there as an opt-in backend in both wasm under `@wielorzeczownik/pepito-client/wasm`, FFI through `Client::withFfi()`.

> Reaching for Rust as the shared core just to hand-roll FFI and wasm-bindgen for a cat-door tracker was probably overkill. Three thin implementations in their native languages turned out to be the better default, so that is what the JS and PHP packages ship now. The Rust core stayed, as a crate and as the opt-in backend.

Every implementation does SSE parsing, the `Way`/`State`/`Update` enums, the in/out state machine, dedup of repeats after a reconnect, an age-aware status cache, connection heartbeat checks, and statistics from the [Clement87/Pepito-data](https://github.com/Clement87/Pepito-data) archive.

The Rust core never touches the network. Transport belongs to the binding, which is why the same code runs in wasm (no sockets there) and in PHP. Each binding lives in its own crate so its dependencies never leak into the others: building for PHP does not compile wasm-bindgen, and the wasm build never touches reqwest.

```text
crates/core/   no-IO core: state, cache, dedup, heartbeat,  -> crates.io
               archive + stats, feature "net" (your reqwest client)
crates/ffi/    C ABI, JSON in / JSON out                    -> PHP
crates/wasm/   wasm-bindgen, JS objects with no JSON round trip -> npm /wasm

js/src/        core.ts: the plain TS port, the default backend
               pepito.ts: transport (fetch, reconnect, EventTarget), TS types
               wasm.ts: the same API over crates/wasm, opt-in
php/src/       Tracker + History: the plain PHP port, the default backend
               FfiTracker: the same API over crates/ffi, opt-in; PHP 8.2+, PSR-4
php/lib/       pepito.h generated from crates/ffi + the built library
php/tests/     php/examples/    not shipped in the Composer package
```

The events, `status()`, the statistics and the snapshot JSON are the shared contract. Snapshots move freely between backends, so a cache written by the plain port restores in the Rust one and back. Parity tests keep the ports honest: `js/tests` runs the TS port against the wasm build and `php/tests/BackendParityTest.php` runs the PHP port against FFI, on the same stream, snapshots and the full archive.

## Usage

Each package has its own README with installation and an example:

- [crates/core/README.md](crates/core/README.md): Rust / crates.io
- [js/README.md](js/README.md): JS / TS / Node / npm
- [php/README.md](php/README.md): PHP / Composer

## Building

Only the Rust core and the opt-in backends need building. The default JS and PHP packages do not.

```sh
make wasm     # js/dist/ through wasm-bindgen
make header   # php/lib/pepito.h straight from crates/ffi (needs cbindgen)
make dylib    # php/lib/pepito.h + php/lib/libpepito.{dylib,so,dll}
```

Tools needed only for building the bindings:

```sh
cargo install cbindgen
cargo install wasm-bindgen-cli --version 0.2.128   # must match crates/wasm/Cargo.toml down to the patch
```

## Disclaimer

This project is community-made and unofficial, and may break if the backend API changes.
