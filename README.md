<h1 align="center">pepito-client</h1>

<p align="center">
  <a href="https://github.com/wielorzeczownik/pepito-client/actions/workflows/release.yml"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/actions/workflow/status/wielorzeczownik/pepito-client/release.yml?branch=main&style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/github/actions/workflow/status/wielorzeczownik/pepito-client/release.yml?branch=main&style=flat-square&color=2ea043"/><img src="https://img.shields.io/github/actions/workflow/status/wielorzeczownik/pepito-client/release.yml?branch=main&style=flat-square&labelColor=2d333b&color=3fb950" alt="release"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/releases/latest"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/v/release/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/github/v/release/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/github/v/release/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Latest Release"/></picture></a> <a href="https://crates.io/crates/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="crates.io"/></picture></a> <a href="https://www.npmjs.com/package/@wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/npm/v/%40wielorzeczownik%2Fpepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="npm"/></picture></a> <a href="https://packagist.org/packages/wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Packagist"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/Rust-B7410E?style=flat-square&logo=rust&logoColor=white" alt="Rust"/>
  <img src="https://img.shields.io/badge/TypeScript-3178C6?style=flat-square&logo=typescript&logoColor=white" alt="TypeScript"/>
  <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP"/>
</p>

[Pepito API](https://github.com/Clement87/Pepito-API) client with one Rust core, usable as a crate, as an npm package (wasm), and from PHP (FFI).

> Reaching for Rust as the shared core just to hand-roll FFI and wasm-bindgen for a cat-door tracker was probably overkill. In hindsight, three thin bindings in their native languages would have shipped faster and been half the maintenance. Not rewriting it now, though.

The core does: SSE parsing, the `Way`/`State`/`Update` enums, the in/out state machine, dedup of repeats after a reconnect, an age-aware status cache, connection heartbeat checks, and statistics from the [Clement87/Pepito-data](https://github.com/Clement87/Pepito-data) archive.

The core never touches the network. Transport belongs to the binding, which is why the same code runs in wasm (no sockets there) and in PHP. Each binding lives in its own crate so its dependencies never leak into the others: building for PHP does not compile wasm-bindgen, and the wasm build never touches reqwest.

```text
crates/core/   no-IO core: state, cache, dedup, heartbeat,  -> crates.io
               archive + stats, feature "net"
crates/ffi/    C ABI, JSON in / JSON out                    -> PHP
crates/wasm/   wasm-bindgen, JS objects with no JSON round trip -> npm

js/            transport: fetch, reconnect, EventTarget, TS types
php/src/       FFI, PHP 8.2+, PSR-4
php/lib/       pepito.h generated from crates/ffi + the built library
php/tests/     php/examples/    not shipped in the Composer package
```

The shape of `Tracker::status()` is the shared contract between both bindings and is assembled once, in the core. A test in `pepito-client` guards against it drifting silently.

## Usage

Each binding has its own README with installation, an example:

- [crates/core/README.md](crates/core/README.md): Rust / crates.io
- [js/README.md](js/README.md): JS / TS / Node / npm
- [php/README.md](php/README.md): PHP / Composer

Want a binding for another language (Python, Go, Ruby, whatever)? The core already speaks JSON in and out through `crates/ffi`, so most of the work is a thin wrapper, not a rewrite. Open a PR, happy to add it.

## Building

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
