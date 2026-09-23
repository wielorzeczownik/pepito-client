LIB_EXT := $(if $(filter Darwin,$(shell uname -s)),dylib,so)

all: js dylib

# Cargo.toml and package.json can drift apart silently.
version:
	@rust=$$(sed -n 's/^version = "\(.*\)"/\1/p' Cargo.toml | head -1); \
	js=$$(node -p "require('./js/package.json').version"); \
	if [ "$$rust" != "$$js" ]; then \
		echo "version drift: Cargo.toml=$$rust  js/package.json=$$js"; exit 1; \
	fi; \
	echo "version agreed: $$rust"

# Each binding restates the endpoint as its own const. This catches them drifting.
urls:
	@for name in SSE_URL REST_URL ARCHIVE_URL; do \
		rust=$$(grep -A1 "^pub const $$name: &str =" crates/core/src/lib.rs | sed -n 's|.*"\(https[^"]*\)".*|\1|p' | head -1); \
		js=$$(grep -A1 "^export const $$name =" js/src/pepito.ts | sed -n "s|.*'\(https[^']*\)'.*|\1|p" | head -1); \
		php=$$(grep -A1 "public const $$name =" php/src/Client.php | sed -n "s|.*'\(https[^']*\)'.*|\1|p" | head -1); \
		if [ -z "$$rust" ] || [ "$$rust" != "$$js" ] || [ "$$rust" != "$$php" ]; then \
			echo "$$name drift: core=$$rust js=$$js php=$$php"; exit 1; \
		fi; \
		echo "$$name agreed: $$rust"; \
	done

wasm:
	@command -v wasm-bindgen >/dev/null || { echo "no wasm-bindgen: cargo install wasm-bindgen-cli --version 0.2.128"; exit 1; }
	cargo build --release --target wasm32-unknown-unknown -p pepito-wasm
	wasm-bindgen --target web --out-dir js/src/wasm --out-name pepito \
		target/wasm32-unknown-unknown/release/pepito_wasm.wasm
	@ls -lh js/src/wasm/pepito_bg.wasm

js: wasm
	cd js && npm run build
	@ls -lh js/dist/pepito.js js/dist/pepito.d.ts js/dist/wasm/pepito_bg.wasm

# Host architecture only, fine for local dev.
dylib: header
	cargo build --release -p pepito-ffi
	cp target/release/libpepito.$(LIB_EXT) php/lib/
	@file php/lib/libpepito.$(LIB_EXT)
	@ls -lh php/lib/libpepito.$(LIB_EXT)

header:
	@command -v cbindgen >/dev/null || { echo "no cbindgen: cargo install cbindgen"; exit 1; }
	@cd crates/ffi && cbindgen --config ../../cbindgen.toml --crate pepito-ffi --output ../../php/lib/pepito.h 2>/dev/null

clean:
	cargo clean
	rm -rf js/dist js/src/wasm php/lib/libpepito.*

.PHONY: all version urls wasm js dylib header clean
