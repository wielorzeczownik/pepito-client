# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.0](https://github.com/wielorzeczownik/pepito-client/compare/v3.0.0...v3.1.0) - 2026-09-26

### Features

- Bundle ESM/UMD builds for jsDelivr/unpkg via Vite (#11) ([412d8bd](https://github.com/wielorzeczownik/pepito-client/commit/412d8bd4a4fe3ff0adf215d192ee0f681ad1fc71))

### Documentation

- Describe the plain TS/PHP default and the opt-in Rust backends ([24a6e76](https://github.com/wielorzeczownik/pepito-client/commit/24a6e767571569f52bdafab3dcd76c8d5a3887fc))

### Build System

- Update dependency prettier to v3.9.9 (#10) ([e87af7d](https://github.com/wielorzeczownik/pepito-client/commit/e87af7df90bdf356b307d814e372b27aef98d944))
- Inline sources into source maps, drop declaration maps (#8) ([c1d082b](https://github.com/wielorzeczownik/pepito-client/commit/c1d082b31539d47afab040d0f820b2433aec0366))

### CI/CD

- Update github actions (#1) ([0feafbf](https://github.com/wielorzeczownik/pepito-client/commit/0feafbfdfdce99ba1479d3a572850b8e798c7f04))

## [3.0.0](https://github.com/wielorzeczownik/pepito-client/compare/v2.0.0...v3.0.0) - 2026-09-24

### Features

- Plain PHP core by default, FFI as opt-in through withFfi() (#7) ([f1df728](https://github.com/wielorzeczownik/pepito-client/commit/f1df7284ad737c076e7a6715488a0391bdbdf885))

## [2.0.0](https://github.com/wielorzeczownik/pepito-client/compare/v1.0.0...v2.0.0) - 2026-09-24

### Features

- Plain TS core by default, wasm as opt-in under /wasm (#6) ([7507fc0](https://github.com/wielorzeczownik/pepito-client/commit/7507fc00305e2f4b2e1946938212ed4e540c2c85))

## [1.0.0](https://github.com/wielorzeczownik/pepito-client/compare/v0.1.2...v1.0.0) - 2026-09-23

### Features

- Take your own fetch, add idleTimeout, check refresh() status ([0e8aa00](https://github.com/wielorzeczownik/pepito-client/commit/0e8aa0088a0bdfc98d3dba5ef46fe2827b4d0a66))
- Add WatchOptions and log reconnects in net::watch ([98ffbcd](https://github.com/wielorzeczownik/pepito-client/commit/98ffbcd20e9b7a4f7d34c48b40e4fad367abb84e))
- Take the caller's reqwest client in net, stop forcing a TLS backend ([28367ee](https://github.com/wielorzeczownik/pepito-client/commit/28367ee645a2255ba954e6f9401bc7b911401f97))

### CI/CD

- Pass -- to sha256sum to satisfy shellcheck SC2035 ([28a6446](https://github.com/wielorzeczownik/pepito-client/commit/28a6446757a4255394531e5abbfabe29b2ac0b0e))

## [0.1.2](https://github.com/wielorzeczownik/pepito-client/compare/v0.1.1...v0.1.2) - 2026-09-23

### Bug Fixes

- Recognize v-prefixed tags and ./-prefixed checksums in the installer ([5d20a5f](https://github.com/wielorzeczownik/pepito-client/commit/5d20a5fc0fac093cac6c08eeb8c723685968da10))

### CI/CD

- Drop the ./ prefix from SHA256SUMS filenames ([c67a2af](https://github.com/wielorzeczownik/pepito-client/commit/c67a2af1512edb571c7786a12e6cdb255f827b68))
- Switch npm and crates.io publishing to OIDC trusted publishing ([7868e67](https://github.com/wielorzeczownik/pepito-client/commit/7868e673859e667d29d4d36819354cf6f8f4487c))

## [0.1.1](https://github.com/wielorzeczownik/pepito-client/compare/v0.1.0...v0.1.1) - 2026-09-23

### Bug Fixes

- Pin laravel/pint to a version that installs on PHP 8.2 ([f434faa](https://github.com/wielorzeczownik/pepito-client/commit/f434faa64cddc7ec1758559df0abdb761da0ff15))

### CI/CD

- Build the package before linting so eslint can resolve dist types ([d01dea3](https://github.com/wielorzeczownik/pepito-client/commit/d01dea386bc5d6a9d129b4a898dcec6cc16e104d))
- Disable crt-static for musl targets so cdylib actually builds ([90c32d4](https://github.com/wielorzeczownik/pepito-client/commit/90c32d46703711317a65819d7b119a7dddc48a4e))
- Install npm dependencies before building the npm package ([5cd0259](https://github.com/wielorzeczownik/pepito-client/commit/5cd02590bb59c88fd25ea9ba199b90b0a02dd14c))

### Miscellaneous

- Exclude the git-cliff generated CHANGELOG.md from prettier ([3b8b226](https://github.com/wielorzeczownik/pepito-client/commit/3b8b226fa220a88af8a7f205e0ee9386635c709d))

## [0.1.0] - 2026-09-23

### Features

- Initial commit ([ef9213c](https://github.com/wielorzeczownik/pepito-client/commit/ef9213c2c6c396043d18550a7b8d8eab60d4d7df))

