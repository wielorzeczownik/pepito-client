//! C ABI over `core`, used by PHP FFI

use std::ffi::{CStr, CString, c_char};

use pepito_client::{Tracker, history};

fn out(text: String) -> *mut c_char {
  CString::new(text).unwrap_or_default().into_raw()
}

/// Reads a C string argument, treating NULL and invalid UTF-8 as empty.
unsafe fn input(pointer: &*const c_char) -> &str {
  let pointer = *pointer;
  if pointer.is_null() {
    return "";
  }
  unsafe { CStr::from_ptr(pointer) }.to_str().unwrap_or("")
}

/// # Safety
///
/// `text` must come from a function of this module returning `*mut c_char`.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_free(text: *mut c_char) {
  if !text.is_null() {
    drop(unsafe { CString::from_raw(text) });
  }
}

#[unsafe(no_mangle)]
pub extern "C" fn pepito_new() -> *mut Tracker {
  Box::into_raw(Box::new(Tracker::new()))
}

/// # Safety
///
/// `tracker` comes from [`pepito_new`] or [`pepito_restore`] and is used here
/// for the last time.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_drop(tracker: *mut Tracker) {
  if !tracker.is_null() {
    drop(unsafe { Box::from_raw(tracker) });
  }
}

/// # Safety
///
/// `json` must be a NUL-terminated C string or NULL.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_restore(json: *const c_char) -> *mut Tracker {
  Box::into_raw(Box::new(Tracker::restore(unsafe { input(&json) })))
}

/// # Safety
///
/// `tracker` must come from [`pepito_new`] or [`pepito_restore`] and must not
/// have been handed to [`pepito_drop`] yet.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_snapshot(tracker: *const Tracker) -> *mut c_char {
  out(unsafe { &*tracker }.snapshot())
}

/// Feeds any slice of the SSE stream. Returns a JSON array of events.
///
/// # Safety
///
/// `tracker` as in [`pepito_snapshot`], `chunk` is a C string or NULL.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_feed(tracker: *mut Tracker, chunk: *const c_char) -> *mut c_char {
  let updates = unsafe { &mut *tracker }.feed_chunk(unsafe { input(&chunk) });
  out(serde_json::to_string(&updates).unwrap_or_else(|_| "[]".into()))
}

/// Feeds the REST last-status response. Returns one event or `null`.
///
/// # Safety
///
/// `tracker` as in [`pepito_snapshot`], `json` is a C string or NULL.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_feed_rest(
  tracker: *mut Tracker,
  json: *const c_char,
) -> *mut c_char {
  let update = unsafe { &mut *tracker }.feed_rest(unsafe { input(&json) });
  out(serde_json::to_string(&update).unwrap_or_else(|_| "null".into()))
}

/// The whole state in one call. Where the cat is, how stale the cache is,
/// whether the heartbeat is alive
///
/// # Safety
///
/// `tracker` as in [`pepito_snapshot`].
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_state(tracker: *const Tracker, now: i64) -> *mut c_char {
  out(serde_json::to_string(&unsafe { &*tracker }.status(now)).unwrap_or_default())
}

/// Statistics computed from the tweet archive
///
/// # Safety
///
/// `json` must be a C string.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_history_stats(json: *const c_char) -> *mut c_char {
  out(match history::parse_history(unsafe { input(&json) }) {
    Ok(sightings) => serde_json::to_string(&history::stats(&sightings)).unwrap_or_default(),
    Err(error) => serde_json::json!({ "error": error.to_string() }).to_string(),
  })
}

/// The archive normalized to the same events as the live stream
///
/// The result is large (hundreds of thousands of entries) and meant for timelines.
///
/// # Safety
///
/// `json` must be a C string.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn pepito_history_parse(json: *const c_char) -> *mut c_char {
  out(match history::parse_history(unsafe { input(&json) }) {
    Ok(sightings) => serde_json::to_string(&sightings).unwrap_or_default(),
    Err(error) => serde_json::json!({ "error": error.to_string() }).to_string(),
  })
}
