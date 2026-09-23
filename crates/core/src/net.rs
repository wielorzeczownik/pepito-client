//! The networking the bindings wrap (feature `net`)

use std::future::Future;
use std::time::Duration;

use futures_util::StreamExt;
use futures_util::future::{Either, select};

use crate::{REST_URL, Tracker, Update};

/// How long a silent stream is allowed to stay open before it is dropped
const IDLE_TIMEOUT: Duration = Duration::from_secs(HEARTBEAT_TIMEOUT_SECS + 15);

const HEARTBEAT_TIMEOUT_SECS: u64 = crate::HEARTBEAT_TIMEOUT as u64;

/// Resolves to `None` when `work` did not finish within `limit`
async fn with_timeout<F: Future>(limit: Duration, work: F) -> Option<F::Output> {
  let timer = futures_timer::Delay::new(limit);
  futures_util::pin_mut!(work);
  futures_util::pin_mut!(timer);
  match select(work, timer).await {
    Either::Left((output, _)) => Some(output),
    Either::Right(_) => None,
  }
}

/// Fetches `/last-status` and pushes it into the cache
///
/// # Errors
///
/// Returns the reqwest error when the request or the body read fails.
pub async fn refresh(tracker: &mut Tracker) -> reqwest::Result<Option<Update>> {
  let body = reqwest::get(REST_URL).await?.text().await?;

  Ok(tracker.feed_rest(&body))
}

/// Runs one SSE connection, returning when the stream dies or the heartbeat goes quiet
///
/// # Errors
///
/// Returns the reqwest error when the connection fails or a chunk cannot be read.
pub async fn watch_once<F: FnMut(Update, &Tracker)>(
  tracker: &mut Tracker,
  url: &str,
  on_update: &mut F,
) -> reqwest::Result<()> {
  let mut stream = reqwest::get(url).await?.bytes_stream();
  while let Some(Some(chunk)) = with_timeout(IDLE_TIMEOUT, stream.next()).await {
    for update in tracker.feed_chunk(&String::from_utf8_lossy(&chunk?)) {
      on_update(update, tracker);
    }
  }
  Ok(())
}

/// Follows the stream forever, reconnecting with a growing backoff
///
/// `should_stop` is consulted between reconnects, which is how a binding asks
/// the loop to wind down without killing the task from the outside.
pub async fn watch<F, S>(tracker: &mut Tracker, url: &str, mut on_update: F, mut should_stop: S)
where
  F: FnMut(Update, &Tracker),
  S: FnMut() -> bool,
{
  let mut backoff = 1;
  while !should_stop() {
    match watch_once(tracker, url, &mut on_update).await {
      Ok(()) => backoff = 1,
      Err(_) => backoff = (backoff * 2).min(60),
    }
    if should_stop() {
      return;
    }
    futures_timer::Delay::new(Duration::from_secs(backoff)).await;
  }
}
