//! The networking the bindings wrap (feature `net`)

use std::future::Future;
use std::time::Duration;

use futures_util::StreamExt;
use futures_util::future::{Either, select};

use crate::{REST_URL, SSE_URL, Tracker, Update};

const HEARTBEAT_TIMEOUT_SECS: u64 = crate::HEARTBEAT_TIMEOUT as u64;

/// How [`watch`] follows the stream
#[derive(Debug, Clone)]
pub struct WatchOptions {
  pub url: String,
  /// How long a silent stream is allowed to stay open before it is dropped
  pub idle_timeout: Duration,
  /// Where the doubling wait between reconnects stops growing
  pub max_backoff: Duration,
}

impl Default for WatchOptions {
  fn default() -> Self {
    Self {
      url: SSE_URL.to_owned(),
      idle_timeout: Duration::from_secs(HEARTBEAT_TIMEOUT_SECS + 15),
      max_backoff: Duration::from_secs(60),
    }
  }
}

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
pub async fn refresh(
  client: Option<&reqwest::Client>,
  tracker: &mut Tracker,
) -> reqwest::Result<Option<Update>> {
  let body = client
    .cloned()
    .unwrap_or_default()
    .get(REST_URL)
    .send()
    .await?
    .error_for_status()?
    .text()
    .await?;

  Ok(tracker.feed_rest(&body))
}

/// Runs one SSE connection, returning when the stream dies or the heartbeat goes quiet
///
/// # Errors
///
/// Returns the reqwest error when the connection fails or a chunk cannot be read.
pub async fn watch_once<F: FnMut(Update, &Tracker)>(
  client: Option<&reqwest::Client>,
  tracker: &mut Tracker,
  options: &WatchOptions,
  on_update: &mut F,
) -> reqwest::Result<()> {
  let mut stream = client
    .cloned()
    .unwrap_or_default()
    .get(&options.url)
    .header(reqwest::header::ACCEPT, "text/event-stream")
    .send()
    .await?
    .error_for_status()?
    .bytes_stream();
  while let Some(Some(chunk)) = with_timeout(options.idle_timeout, stream.next()).await {
    for update in tracker.feed_chunk(&String::from_utf8_lossy(&chunk?)) {
      on_update(update, tracker);
    }
  }
  Ok(())
}

/// Follows the stream forever, reconnecting with a growing backoff
///
/// `should_stop` is consulted between reconnects, which is how a binding asks
/// the loop to wind down without killing the task from the outside. Every
/// reconnect is a `log` warning.
pub async fn watch<F, S>(
  client: Option<&reqwest::Client>,
  tracker: &mut Tracker,
  options: &WatchOptions,
  mut on_update: F,
  mut should_stop: S,
) where
  F: FnMut(Update, &Tracker),
  S: FnMut() -> bool,
{
  // Built once, so the reconnects reuse its pool.
  let client = client.cloned().unwrap_or_default();
  let mut backoff = Duration::from_secs(1);
  while !should_stop() {
    match watch_once(Some(&client), tracker, options, &mut on_update).await {
      Ok(()) => {
        backoff = Duration::from_secs(1);
        log::warn!("pepito: stream went quiet, reconnecting in {backoff:?}");
      }
      Err(error) => {
        backoff = (backoff * 2).min(options.max_backoff);
        log::warn!("pepito: stream broke ({error}), retrying in {backoff:?}");
      }
    }
    if should_stop() {
      return;
    }
    futures_timer::Delay::new(backoff).await;
  }
}
