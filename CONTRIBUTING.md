# Contributing to pepito

Thank you for considering a contribution. This document covers everything you need to get started.

## Overview

[Pepito API](https://github.com/Clement87/Pepito-API) client with one Rust core, usable as a crate, as an npm package (wasm), and from PHP (FFI). See [README.md](README.md) for the shape of the repo.

## Project structure

```text
.
├── crates/core/     no-IO core: state, cache, dedup, heartbeat, archive stats   -> crates.io
├── crates/ffi/      C ABI, JSON in / JSON out                                  -> PHP
├── crates/wasm/     wasm-bindgen, JS objects with no JSON round trip           -> npm
├── js/              transport: fetch, reconnect, EventTarget, TS types
├── php/src/         FFI, PHP 8.2+, PSR-4
├── php/lib/         pepito.h generated from crates/ffi + the built library
└── scripts/
    ├── bump-version.sh     determines and applies the next release version from git-cliff output
    └── security-audit.sh   runs cargo/composer/npm audit, fixes what npm can, reports the rest
```

## Development setup

```bash
git clone https://github.com/wielorzeczownik/pepito-client.git
cd pepito
cargo install cbindgen
cargo install wasm-bindgen-cli --version 0.2.128   # must match crates/wasm/Cargo.toml down to the patch
composer install
cargo test --workspace --locked
```

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

# JS (from js/)
npm ci
npm run lint
npm run typecheck
npm test

# PHP (from repo root, after composer install)
vendor/bin/pint --test
vendor/bin/phpunit

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

> For security issues, read [SECURITY.md](SECURITY.md) before opening a public issue.

## License

By contributing you agree that your changes will be licensed under the [MIT License](LICENSE).
