<h1 align="center">pepito-client</h1>

<p align="center">
  <a href="https://crates.io/crates/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="crates.io"/></picture></a> <a href="https://docs.rs/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/docsrs/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/docsrs/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/docsrs/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="docs.rs"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/Rust-B7410E?style=flat-square&logo=rust&logoColor=white" alt="Rust"/>
</p>

[Pepito API](https://github.com/Clement87/Pepito-API) client: SSE parsing, the `Way`/`State`/`Update` enums, the in/out state machine, dedup of repeats after a reconnect, an age-aware status cache, connection heartbeat checks, and statistics from the [Clement87/Pepito-data](https://github.com/Clement87/Pepito-data) archive.

No IO. It runs identically natively and on wasm. Transport (fetch/curl/reqwest) belongs to the binding. This crate only processes whatever it is handed.

```toml
pepito-client = { version = "0.1", features = ["net"] }     # bring your own reqwest client and its TLS
pepito-client = { version = "0.1", features = ["rustls"] }  # net + reqwest's rustls, nothing to set up
```

Without either feature the core has no network dependencies.

```rust
use pepito_client::{net, SSE_URL, Tracker, Update};

// Your client, optional like `setHttpClient()` in PHP (None = reqwest's default).
// For watch() use read_timeout/connect_timeout, not timeout(), which caps the whole stream.
let client = reqwest::Client::builder()
    .user_agent("my-app/1.0")
    .read_timeout(std::time::Duration::from_secs(60))
    .build()?;

let mut t = Tracker::new();
net::refresh(Some(&client), &mut t).await?; // cache from REST
println!("{:?}", t.state());          // Away { since: 1790000391 }

net::watch(Some(&client), &mut t, SSE_URL, |u, t| { // reconnect and backoff handled inside
    if let Update::Sighting { sighting, changed: true } = u {
        println!("{} -> {:?}", sighting.way.as_str(), t.state());
    }
}, || false).await
```

Without the `net` feature you feed the tracker yourself, from whatever reads the network:

```rust
for update in t.feed_chunk(&bytes_from_wherever) { ... }
t.is_stale(now, 300);      // whether to refresh from REST
t.healthy(now);            // whether the heartbeat is alive, i.e. whether to reconnect
let json = t.snapshot();   // cache to persist
```

## Archive

```rust
let sightings = pepito_client::history::parse_history(&json)?;
let stats = pepito_client::history::stats(&sightings);
// total, outings, avg_outing_secs, median_outing_secs, by_hour_out/by_hour_in/by_weekday_out
```

Pairing outings: an outing is the last `out` before an `in`. Consecutive `out` events, which happen in the archive when a return got lost, count as `unpaired`.

More on the architecture of the whole repo (crates + npm + composer together):
see the [README](https://github.com/wielorzeczownik/pepito-client#readme) at the repo root.
