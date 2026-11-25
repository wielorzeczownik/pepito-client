<h1 align="center">pepito-client</h1>

<p align="center">
  <a href="https://crates.io/crates/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/crates/v/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/crates/v/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="crates.io"/></picture></a> <a href="https://docs.rs/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/docsrs/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/docsrs/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/docsrs/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="docs.rs"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/Rust-B7410E?style=flat-square&logo=rust&logoColor=white" alt="Rust"/>
</p>

<p align="center">
  <img src="https://raw.githubusercontent.com/wielorzeczownik/pepito-client/main/assets/logo.svg" alt="pepito-client logo" width="200" />
</p>

A [Pepito API](https://github.com/Clement87/Pepito-API) client. It parses the SSE stream, tracks whether the cat is in or out, drops repeats after a reconnect, caches the status with its age, watches the heartbeat and computes statistics from the [Pepito-data](https://github.com/Clement87/Pepito-data) archive.

The core does no networking, so it runs the same natively and on wasm. You either feed it bytes yourself or turn on a feature that brings reqwest:

```toml
pepito-client = { version = "3", features = ["net"] }    # your own reqwest client and TLS
pepito-client = { version = "3", features = ["rustls"] } # net plus reqwest's rustls
```

```rust
use std::time::Duration;
use pepito_client::{net, Tracker, Update};

// Optional, None uses reqwest's default. For watch(), set read_timeout, not timeout(),
// because timeout() caps the whole stream.
let client = reqwest::Client::builder()
    .user_agent("my-app/1.0")
    .read_timeout(Duration::from_secs(60))
    .build()?;

let mut t = Tracker::new();
net::refresh(Some(&client), &mut t).await?;
println!("{:?}", t.state()); // Away { since: 1790000391 }

let options = net::WatchOptions { max_backoff: Duration::from_secs(30), ..Default::default() };
net::watch(Some(&client), &mut t, &options, |u, t| {
    if let Update::Sighting { sighting, changed: true } = u {
        println!("{} -> {:?}", sighting.way.as_str(), t.state());
    }
}, || false).await
```

`WatchOptions` has `url`, `idle_timeout` and `max_backoff`. Reconnects and backoff happen inside `watch()`.

Without a feature, you read the network however you like and pass the bytes in:

```rust
for update in t.feed_chunk(&bytes) { /* ... */ }
t.is_stale(now, 300); // time to refresh from REST?
t.healthy(now);       // is the heartbeat alive, or time to reconnect?
let json = t.snapshot();
```

## Archive

```rust
let sightings = pepito_client::history::parse_history(&json)?;
let stats = pepito_client::history::stats(&sightings);
// total, outings, avg_outing_secs, median_outing_secs, by_hour_out, by_hour_in, by_weekday_out
```

An outing is the last `out` before an `in`. When the archive has several `out` in a row (a return got lost), the extra ones count as `unpaired`.

This is one of three packages built from the same repository. See the [main README](https://github.com/wielorzeczownik/pepito-client#readme) for how they fit together.
