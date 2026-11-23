# Contributing

Every contribution helps, including a typo fix, a bug report, or a question about how something works. For anything bigger than a small fix, opening an issue first is a good idea, so we can agree on the approach before you spend time on it.

## One behavior, three implementations

The tracker and the archive statistics exist three times: `crates/core/src/`, `js/src/core.ts` and `php/src/Tracker.php` with `php/src/History.php`. A change in behavior (parsing, dedup, state, `status()`, stats, snapshot format) has to land in all three in the same PR. The parity tests (`js/tests/pepito.test.ts`, `php/tests/BackendParityTest.php`) feed the same input to a port and to the Rust core and fail on any difference, so extend their fixtures along with the change.

If you only know one of the three languages, that's fine. Open the PR with the part you can do and we'll fill in the rest together.

## Setup

```bash
git clone https://github.com/wielorzeczownik/pepito-client.git
cd pepito-client
cargo install cbindgen
cargo install wasm-bindgen-cli --version 0.2.130   # must match crates/wasm/Cargo.toml down to the patch
composer install
cargo test --workspace --locked
make wasm    # the wasm build for js/, needed by the JS parity tests
make dylib   # libpepito for php/, needed by the PHP parity tests
```

Without `make wasm` and `make dylib` the default JS and PHP backends still build and test, and the parity tests are skipped. CI always builds both, so the parity tests always run there.

## Checks

CI runs these on every pull request. You don't have to run them all locally, since CI will point out anything that's off:

```bash
cargo fmt --all --check
cargo clippy --workspace --all-targets --all-features --locked -- -D warnings
cargo test --workspace --locked

# header, version and URL drift between the three bindings
make header
make version
make urls

# JS (from js/, after make wasm)
npm ci
npm run lint
npm run typecheck
npm test

# PHP (from the repo root, after composer install and make dylib)
composer validate --no-check-publish
vendor/bin/pint --test
vendor/bin/phpunit
php -d ffi.enable=0 vendor/bin/phpunit   # the default backend must not need FFI

shfmt --diff scripts/
shellcheck scripts/*.sh
npx prettier --check .
actionlint
markdownlint-cli2 "**/*.md" '!CHANGELOG.md'
```

`cargo fmt --all`, `npm run fix` (in `js/`), `vendor/bin/pint` and `npx prettier --write .` fix what they can on their own.

## Commits and pull requests

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat(core): ...`, `fix(php): ...`), because the changelog and version bumps are generated from them. The scope names the area, e.g. `core`, `ffi`, `wasm`, `js`, `php`. Don't worry if you get it wrong, it can be fixed before merging.

Smaller PRs that do one thing are easier to review. Tests for behavior changes are very welcome.

Bugs and ideas go to [issues](https://github.com/wielorzeczownik/pepito-client/issues). Security problems go through [SECURITY.md](SECURITY.md).

Contributions are licensed under the [MIT License](LICENSE).
