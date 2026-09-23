# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

