# Contributing to pepito

Thank you for considering a contribution. This document covers everything you need to get started.

## Overview

[Pepito API](https://github.com/Clement87/Pepito-API) client for Rust (crate), JS/TS (npm) and PHP (Composer). The npm and Composer packages run a plain TS and a plain PHP port by default, and the Rust core as an opt-in backend (wasm, FFI). See [README.md](README.md) for the shape of the repo.

### One behavior, three implementations

The tracker and the archive statistics exist three times: `crates/core/src/`, `js/src/core.ts` and `php/src/Tracker.php` + `php/src/History.php`. A change in behavior (parsing, dedup, state, `status()`, stats, snapshot format) has to land in all three in the same PR. The parity tests (`js/tests/pepito.test.ts`, `php/tests/BackendParityTest.php`) feed the same input to a port and to the Rust core and fail on any difference, so extend their fixtures along with the change.

## Project structure

```text
.
├── crates/core/     no-IO core: state, cache, dedup, heartbeat, archive stats   -> crates.io
├── crates/ffi/      C ABI, JSON in / JSON out                                  -> PHP
├── crates/wasm/     wasm-bindgen, JS objects with no JSON round trip           -> npm /wasm
├── js/src/          core.ts (plain TS port, default), pepito.ts (transport, types), wasm.ts (opt-in)
├── php/src/         Tracker + History (plain PHP port, default), FfiTracker (opt-in); PHP 8.2+, PSR-4
├── php/lib/         pepito.h generated from crates/ffi + the built library
└── scripts/
    ├── bump-version.sh     determines and applies the next release version from git-cliff output
    └── security-audit.sh   runs cargo/composer/npm audit, fixes what npm can, reports the rest
```

## Development setup

```bash
git clone https://github.com/wielorzeczownik/pepito-client.git
cd pepito-client
cargo install cbindgen
cargo install wasm-bindgen-cli --version 0.2.128   # must match crates/wasm/Cargo.toml down to the patch
composer install
cargo test --workspace --locked
make wasm    # the wasm build for js/, needed by the JS parity tests
make dylib   # libpepito for php/, needed by the PHP parity tests
```

Without `make wasm` / `make dylib` the default JS and PHP backends still build and test, and the parity tests are skipped. CI always builds both, so the parity tests always run there.

## Running checks locally

CI runs exactly these commands. Anything that passes here passes there.

```bash
# Rust
cargo fmt --all --check
cargo clippy --workspace --all-targets --all-features --locked -- -D warnings
cargo test --workspace --locked

# Version and URL drift between the three bindings
make version
make urls

# JS (from js/, after make wasm)
npm ci
npm run lint
npm run typecheck
npm test

# PHP (from repo root, after composer install and make dylib)
vendor/bin/pint --test
vendor/bin/phpunit
php -d ffi.enable=0 vendor/bin/phpunit   # the default backend must not need FFI

# Formatting
npx prettier --check .

# Workflows
actionlint

# Markdown
markdownlint-cli2 "**/*.md" '!CHANGELOG.md'
```

To apply autofixable findings instead of just checking: `cargo fmt --all`, `npm run fix` (js/), `vendor/bin/pint`, `npx prettier --write .`.

## Commit style

This project uses [Conventional Commits](https://www.conventionalcommits.org/). Commit messages drive automatic changelog generation and version bumping.

| Prefix      | When to use                                |
| ----------- | ------------------------------------------ |
| `feat:`     | New feature or behavior                    |
| `fix:`      | Bug fix                                    |
| `perf:`     | Performance improvement                    |
| `refactor:` | Code change without behavior change        |
| `test:`     | Tests only                                 |
| `docs:`     | Documentation only                         |
| `style:`    | Formatting, no logic change                |
| `build:`    | Build tooling and development dependencies |
| `ci:`       | Workflows and CI configuration             |
| `chore:`    | Maintenance that fits nothing above        |

Scope names the area, not the file: `core`, `ffi`, `wasm`, `js`, `php`, `release`, `deps`.

Breaking changes must include `BREAKING CHANGE:` in the commit footer.

## Pull requests

- Keep PRs focused on a single concern.
- Reference any related issue in the PR description.
- All CI checks must pass before merging.

## Reporting bugs

Open an [issue](https://github.com/wielorzeczownik/pepito-client/issues) and include:

- What you did
- What you expected
- What actually happened
- Which binding (Rust, JS, PHP) and version
- For JS and PHP, which backend: the default one, or wasm / FFI

> For security issues, read [SECURITY.md](SECURITY.md) before opening a public issue.

## License

By contributing you agree that your changes will be licensed under the [MIT License](LICENSE).
