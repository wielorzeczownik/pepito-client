//! wasm-bindgen binding over `pepito-client`, the source of the npm package
//!
//! Unlike `pepito-ffi` nothing here travels as JSON: events come back as plain
//! JS objects. Transport (fetch, reconnect, events) stays in `js/src/pepito.ts`,
//! because that is the layer wasm-bindgen does not generate.

use serde::Serialize;
use wasm_bindgen::prelude::*;

/// Maps as objects is required here, not cosmetic: the default serializer
/// hands back Rust maps as a JS `Map`, and `Update` carries `#[serde(flatten)]`,
/// so it would arrive as a `Map` and `update.kind` would read `undefined`.
/// `None` stays `undefined` rather than `null`, the same as the plain TS core.
fn to_js<T: Serialize>(value: &T) -> Result<JsValue, JsValue> {
  value
    .serialize(&serde_wasm_bindgen::Serializer::new().serialize_maps_as_objects(true))
    .map_err(|error| JsValue::from_str(&error.to_string()))
}

/// Unix seconds, the way the Pepito API counts them
///
/// Truncating the milliseconds is the point here, and the value stays far
/// inside `i64` for any date a browser can report.
#[allow(clippy::cast_possible_truncation)]
fn now() -> i64 {
  (js_sys::Date::now() / 1000.0) as i64
}

/// State, cache, dedup and heartbeat. Memory is released by `free()`.
#[wasm_bindgen]
pub struct Tracker(pepito_client::Tracker);

#[wasm_bindgen]
impl Tracker {
  #[wasm_bindgen(constructor)]
  #[must_use]
  pub fn new(snapshot: Option<String>) -> Tracker {
    Tracker(match snapshot {
      Some(json) => pepito_client::Tracker::restore(&json),
      None => pepito_client::Tracker::new(),
    })
  }

  /// Feeds any slice of the SSE stream, including one cut mid-line
  ///
  /// Returns an array of events.
  ///
  /// # Errors
  ///
  /// Returns a `JsValue` error when the events cannot be converted to JS.
  pub fn feed(&mut self, chunk: &str) -> Result<JsValue, JsValue> {
    to_js(&self.0.feed_chunk(chunk))
  }

  /// Feeds the REST last-status response. Returns one event or `undefined`.
  ///
  /// # Errors
  ///
  /// Returns a `JsValue` error when the event cannot be converted to JS.
  #[wasm_bindgen(js_name = feedRest)]
  pub fn feed_rest(&mut self, json: &str) -> Result<JsValue, JsValue> {
    to_js(&self.0.feed_rest(json))
  }

  /// State, cache age and heartbeat health, timed by the browser clock
  ///
  /// # Errors
  ///
  /// Returns a `JsValue` error when the status cannot be converted to JS.
  #[wasm_bindgen(getter)]
  pub fn state(&self) -> Result<JsValue, JsValue> {
    to_js(&self.0.status(now()))
  }

  /// Cache to store wherever you like
  #[must_use]
  pub fn snapshot(&self) -> String {
    self.0.snapshot()
  }

  /// Whether a REST refresh is worth it
  ///
  /// Seconds are taken as `u32` so that the JS side sees a plain number rather
  /// than a `BigInt`.
  #[wasm_bindgen(js_name = isStale)]
  #[must_use]
  pub fn is_stale(&self, max_age: u32) -> bool {
    self.0.is_stale(now(), max_age.into())
  }
}

/// Statistics from the archive (tweets.json from Clement87/Pepito-data)
///
/// # Errors
///
/// Returns a `JsValue` error when the archive cannot be parsed.
#[wasm_bindgen(js_name = historyStats)]
pub fn history_stats(json: &str) -> Result<JsValue, JsValue> {
  let sightings =
    pepito_client::parse_history(json).map_err(|error| JsValue::from_str(&error.to_string()))?;
  to_js(&pepito_client::stats(&sightings))
}

/// The archive as the same events as the live stream. Large, tens of thousands of entries.
///
/// # Errors
///
/// Returns a `JsValue` error when the archive cannot be parsed.
#[wasm_bindgen(js_name = historyParse)]
pub fn history_parse(json: &str) -> Result<JsValue, JsValue> {
  let sightings =
    pepito_client::parse_history(json).map_err(|error| JsValue::from_str(&error.to_string()))?;
  to_js(&sightings)
}
