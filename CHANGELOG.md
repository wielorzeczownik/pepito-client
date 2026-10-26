# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.2.11](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.10...v3.2.11) - 2026-10-26

### Security

- Update rust crate tokio to v1.54.1 [security] (#59) ([225e3a5](https://github.com/wielorzeczownik/pepito-client/commit/225e3a52c360920cc413c528d316762e98448b24))

### Bug Fixes

- Draft releases before uploading assets (#61) ([ec95b5b](https://github.com/wielorzeczownik/pepito-client/commit/ec95b5b837e6139524ce3d231acd6a6205d20350))

### CI/CD

- Update dtolnay/rust-toolchain digest to b725eda (#62) ([94f8dce](https://github.com/wielorzeczownik/pepito-client/commit/94f8dce56c5b5c69a877770fbe662d13373e3054))
- Cut release candidates on push and stable releases weekly (#60) ([543ecdd](https://github.com/wielorzeczownik/pepito-client/commit/543ecdd36ac9e5199a6482a0beacd8c09ba3847c))

### Dependencies

- Update dependency vite to v8.3.5 (#63) ([147b142](https://github.com/wielorzeczownik/pepito-client/commit/147b1426a3ec39e0f1e29ea8475985f94da5f3af))

## [3.2.10](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.9...v3.2.10) - 2026-10-21

### Bug Fixes

- Match breaking changes by field so they get their own section ([13f9bcf](https://github.com/wielorzeczownik/pepito-client/commit/13f9bcf69256726cae83f49ac2100fb2d4ccf8f8))
- Group fix(deps) updates under dependencies ([bdd1ded](https://github.com/wielorzeczownik/pepito-client/commit/bdd1ded523c331ffcd61e98ce10b9aa7214e06fb))

### Documentation

- Explain how to verify attestations in release notes ([dbe1bf9](https://github.com/wielorzeczownik/pepito-client/commit/dbe1bf937396c367ad4c7be93a0d4ec8dcbdfc09))
- Use shared code of conduct from .github ([01d8726](https://github.com/wielorzeczownik/pepito-client/commit/01d872697d19009387d7ff7fa84c6f1b471e3c4e))

### Build System

- Migrate fileMatch to managerFilePatterns ([c4f17f1](https://github.com/wielorzeczownik/pepito-client/commit/c4f17f157cad9dc789795e6d9edae9f6a430cc83))
- Use built-in manager for workflow tool versions ([8cb02d4](https://github.com/wielorzeczownik/pepito-client/commit/8cb02d4897bd23eaa2be5d4a34ad345b288798ce))
- Drop rules covered by shared preset ([de2d1e4](https://github.com/wielorzeczownik/pepito-client/commit/de2d1e40bc2ccbce3eb77342da1c21e58aea4149))
- Extend shared preset from .github ([32eeed7](https://github.com/wielorzeczownik/pepito-client/commit/32eeed75739593963b0f7facbce539cc92686eb2))
- Open one pull request per dependency (#57) ([6d6cdc4](https://github.com/wielorzeczownik/pepito-client/commit/6d6cdc45511b26e644b13db1df3996900906dd3a))
- Update dependency vitest to v5.0.5 (#55) ([e23aead](https://github.com/wielorzeczownik/pepito-client/commit/e23aead1bcbef276e241e90b4a7475ee5af5a762))

### CI/CD

- Attest the sbom alongside the library archives ([7509fc5](https://github.com/wielorzeczownik/pepito-client/commit/7509fc5d35100097ff49ffe5507e7710c5282b26))
- Attach an spdx sbom to releases ([36f53f9](https://github.com/wielorzeczownik/pepito-client/commit/36f53f961b0550cd26ab41dc26ea3774e8e0020e))
- Move pinned tool versions to workflow env ([45fd6b4](https://github.com/wielorzeczownik/pepito-client/commit/45fd6b444e5bcedbe82023d774187588bc2e9885))

### Dependencies

- Lock file maintenance (#56) ([b9f95f2](https://github.com/wielorzeczownik/pepito-client/commit/b9f95f298fb3e6ec68737b514d8c140574b770ac))

## [3.2.9](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.8...v3.2.9) - 2026-10-18

### Performance

- Stop publishing source maps (#54) ([b50dabd](https://github.com/wielorzeczownik/pepito-client/commit/b50dabdf51a02bb1372280af57aeb37a538e16d6))

## [3.2.8](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.7...v3.2.8) - 2026-10-18

### Performance

- Stop inlining the wasm binary into the bundles (#53) ([b0087c5](https://github.com/wielorzeczownik/pepito-client/commit/b0087c51e21d6d33c45f99c314635d45ce8154ad))

## [3.2.7](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.6...v3.2.7) - 2026-10-18

### Bug Fixes

- Publish unminified bundles with stable file names (#52) ([3191302](https://github.com/wielorzeczownik/pepito-client/commit/3191302a35cd258a359a2eae6af19a79c60f0dd3))

### CI/CD

- Install tools only when releasing and unify job names (#51) ([0d9edae](https://github.com/wielorzeczownik/pepito-client/commit/0d9edaeee90c8759931004c4ae8588867acd66fc))
- Hand dependency fixes to renovate (#50) ([b4b1eef](https://github.com/wielorzeczownik/pepito-client/commit/b4b1eef4943167866f91c2990021cd1c0978eccd))
- Merge light checks into one job and render audit reports ([2fbed8f](https://github.com/wielorzeczownik/pepito-client/commit/2fbed8f54147ebaf75be03cd1000b87b69e1681e))
- Batch updates weekly and skip bot PRs in label/assign ([7a5e153](https://github.com/wielorzeczownik/pepito-client/commit/7a5e15363fd3ee7cdfbadd0f698bc9b50db8e024))
- Update taiki-e/install-action action to v2.87.27 (#48) ([e73bd45](https://github.com/wielorzeczownik/pepito-client/commit/e73bd454e56c774d3fc4cbbc262a270f4a75f73e))

## [3.2.6](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.5...v3.2.6) - 2026-10-17

### Build System

- Update dependency eslint-plugin-unicorn to v78 (#46) ([c54d1ed](https://github.com/wielorzeczownik/pepito-client/commit/c54d1ed491c0b6a8b8db9fd2b28c28c881361e82))
- Update dependency typescript-eslint to v8.71.2 (#45) ([d7b60dc](https://github.com/wielorzeczownik/pepito-client/commit/d7b60dc29b7cba13988211482fa79d344db63671))
- Update dependency vitest to v5.0.4 (#44) ([e21d144](https://github.com/wielorzeczownik/pepito-client/commit/e21d1443829686eb0d8da6d8f31b394eff204746))

### CI/CD

- Update github actions (#42) ([c5cc26d](https://github.com/wielorzeczownik/pepito-client/commit/c5cc26dc878b64bacb2cb84d7ddc7889b37e28f9))

### Dependencies

- Update rust crate tokio to v1.53.3 (#47) ([c1c0a03](https://github.com/wielorzeczownik/pepito-client/commit/c1c0a031ff97f963939931a8ac5883e0e1a46225))

## [3.2.5](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.4...v3.2.5) - 2026-10-14

### Build System

- Update dependency eslint-plugin-unicorn to v77 (#33) ([b014b63](https://github.com/wielorzeczownik/pepito-client/commit/b014b638402efbcf198c3d290ab220801aeff3fa))
- Update dependency prettier to v3.9.10 (#39) ([afcef8e](https://github.com/wielorzeczownik/pepito-client/commit/afcef8e7131595b1c8fd3fdc4129fc61e2ffd648))

### CI/CD

- Update github actions (#26) ([4c333bd](https://github.com/wielorzeczownik/pepito-client/commit/4c333bd9a3efea1e0728a4eac35233380cd271ca))

### Dependencies

- Update rust crate tokio to v1.53.2 (#32) ([c08b93c](https://github.com/wielorzeczownik/pepito-client/commit/c08b93c4442ff1ad9c688f2ccf5a83885f1657f1))

## [3.2.4](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.3...v3.2.4) - 2026-10-14

### Build System

- Update dependency @types/node to v26.6.5 (#38) ([f7f2d0f](https://github.com/wielorzeczownik/pepito-client/commit/f7f2d0f957ef5a2da5cf9b3ac6bbd3d30efba344))
- Update dependency vite to v8.3.4 (#37) ([045ab3f](https://github.com/wielorzeczownik/pepito-client/commit/045ab3f7b73e34934aa4df7344e3b573d1311c98))
- Update dependency vite to v8.3.3 (#36) ([d35509d](https://github.com/wielorzeczownik/pepito-client/commit/d35509d278da3d6aac0d0cf1fc8410c3f153383b))
- Update dependency typescript-eslint to v8.71.1 (#35) ([e4d2b80](https://github.com/wielorzeczownik/pepito-client/commit/e4d2b80307b7078fcf5384f136b842d9df73352c))

### CI/CD

- Update artifact actions (#41) ([b3c4506](https://github.com/wielorzeczownik/pepito-client/commit/b3c4506519e2ab0ec5e08be0734ba2fa1f816306))

### Dependencies

- Update rust crate serde_json to v1.0.154 (#40) ([5e351c0](https://github.com/wielorzeczownik/pepito-client/commit/5e351c013327c8bf8f6cc70e89873b063de05a8c))

## [3.2.3](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.2...v3.2.3) - 2026-10-08

### Build System

- Update dependency eslint to v10.12.0 (#31) ([7df7d54](https://github.com/wielorzeczownik/pepito-client/commit/7df7d54b0857a96c7e630c78cd60f4db6cccadc0))
- Update dependency @types/node to v26.6.4 (#30) ([e7136fc](https://github.com/wielorzeczownik/pepito-client/commit/e7136fc677c3d352ad7f7291a3380f357030234f))
- Update dependency vite to v8.3.2 (#29) ([09c4ed8](https://github.com/wielorzeczownik/pepito-client/commit/09c4ed833797e8725c119bd3f0e39733107fa453))
- Update dependency vitest to v5.0.3 (#28) ([e5ed49d](https://github.com/wielorzeczownik/pepito-client/commit/e5ed49dc0aec3242cee87587f41c0a205a3b7316))
- Update dependency typescript-eslint to v8.71.0 (#27) ([9dc1b5d](https://github.com/wielorzeczownik/pepito-client/commit/9dc1b5d1b7033e574d49a6846cb50184c53e46a0))
- Update dependency eslint-plugin-sonarjs to v4.2.2 (#25) ([f32d22a](https://github.com/wielorzeczownik/pepito-client/commit/f32d22aa11fc933d01a5850e27e1318bd78419ce))

### CI/CD

- Update taiki-e/install-action action to v2.87.19 (#24) ([c3f2f33](https://github.com/wielorzeczownik/pepito-client/commit/c3f2f331a56cb16a457f5d1384d466902c687cc4))
- Update taiki-e/install-action action to v2.87.18 (#23) ([181d5c2](https://github.com/wielorzeczownik/pepito-client/commit/181d5c28ff25d30a00f86a08ac47d364aeca4131))

### Dependencies

- Update dependency phpunit/phpunit to v11.5.57 (#34) ([c4aeb93](https://github.com/wielorzeczownik/pepito-client/commit/c4aeb93a7d1d5ec3302ccfa2db4a5a576f7b9676))
- Resolve npm audit advisories ([daad16e](https://github.com/wielorzeczownik/pepito-client/commit/daad16ea5d41dc17bf7f7bf4351b12099e86ed33))

## [3.2.2](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.1...v3.2.2) - 2026-09-29

### Build System

- Update dependency vitest to v5.0.2 (#21) ([b77facb](https://github.com/wielorzeczownik/pepito-client/commit/b77facbe2919e837e5633cb5d5fe7f30df5eda4c))

### Dependencies

- Update rust crate wasm-bindgen to v0.2.129 (#22) ([b30b500](https://github.com/wielorzeczownik/pepito-client/commit/b30b500d1b11efd5031e85b8eca5d1c116970433))

## [3.2.1](https://github.com/wielorzeczownik/pepito-client/compare/v3.2.0...v3.2.1) - 2026-09-29

### Bug Fixes

- Correct php/typescript version caps (#19) ([4fb6433](https://github.com/wielorzeczownik/pepito-client/commit/4fb64336cfb7bb1333fbac5afad8ae130d512e77))

### Build System

- Update dependency @types/node to v26.6.3 (#20) ([5bfbbb4](https://github.com/wielorzeczownik/pepito-client/commit/5bfbbb41ef2dd48aa99c7bbc201275218ba56358))

### Miscellaneous

- Cap incompatible pint/phpunit, group typescript (#16) ([e1ae405](https://github.com/wielorzeczownik/pepito-client/commit/e1ae40552572d66bbc3dad2eb2867b905159aac9))
- Enable vulnerabilityAlerts (#15) ([e448307](https://github.com/wielorzeczownik/pepito-client/commit/e4483075e8db1c3f0d3a200bfb539300aec64e90))

## [3.2.0](https://github.com/wielorzeczownik/pepito-client/compare/v3.1.0...v3.2.0) - 2026-09-27

### Features

- Add CommonJS build via Vite (#14) ([b8eb621](https://github.com/wielorzeczownik/pepito-client/commit/b8eb621f0df547707012b9acf25a7f366303abc1))

### CI/CD

- Update taiki-e/install-action action to v2.87.17 (#13) ([cecbfff](https://github.com/wielorzeczownik/pepito-client/commit/cecbfffbf7beb338b99baa3fc85b9a598b0166cc))
- Update github actions (#9) ([07d3537](https://github.com/wielorzeczownik/pepito-client/commit/07d3537d28d779957ea7edb0d048bb6beb2b5121))

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

### Breaking Changes

- Plain PHP core by default, FFI as opt-in through withFfi() (#7) ([f1df728](https://github.com/wielorzeczownik/pepito-client/commit/f1df7284ad737c076e7a6715488a0391bdbdf885))

## [2.0.0](https://github.com/wielorzeczownik/pepito-client/compare/v1.0.0...v2.0.0) - 2026-09-24

### Breaking Changes

- Plain TS core by default, wasm as opt-in under /wasm (#6) ([7507fc0](https://github.com/wielorzeczownik/pepito-client/commit/7507fc00305e2f4b2e1946938212ed4e540c2c85))

## [1.0.0](https://github.com/wielorzeczownik/pepito-client/compare/v0.1.2...v1.0.0) - 2026-09-23

### Breaking Changes

- Add WatchOptions and log reconnects in net::watch ([98ffbcd](https://github.com/wielorzeczownik/pepito-client/commit/98ffbcd20e9b7a4f7d34c48b40e4fad367abb84e))
- Take the caller's reqwest client in net, stop forcing a TLS backend ([28367ee](https://github.com/wielorzeczownik/pepito-client/commit/28367ee645a2255ba954e6f9401bc7b911401f97))

### Features

- Take your own fetch, add idleTimeout, check refresh() status ([0e8aa00](https://github.com/wielorzeczownik/pepito-client/commit/0e8aa0088a0bdfc98d3dba5ef46fe2827b4d0a66))

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

