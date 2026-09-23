//! The IO-free core: feed it lines, it hands back events, state and a cache

use serde::{Deserialize, Serialize};

use crate::types::{Sighting, State, Update, Way, Wire};

/// With no heartbeat for this many seconds the connection counts as dead
/// (the server beats roughly every 10 s)
pub const HEARTBEAT_TIMEOUT: i64 = 30;

#[derive(Debug, Clone, Default, Serialize, Deserialize)]
pub struct Tracker {
  /// Last known crossing, which doubles as the status cache
  pub last: Option<Sighting>,
  pub last_heartbeat: Option<i64>,
  pub count_in: u32,
  pub count_out: u32,
  /// Unfinished line carried between stream chunks
  #[serde(skip)]
  buffer: String,
}

impl Tracker {
  #[must_use]
  pub fn new() -> Self {
    Self::default()
  }

  /// Feeds one SSE line or a bare JSON object
  ///
  /// Returns `None` for comments, `event:`/`id:` fields and garbage.
  pub fn feed_line(&mut self, line: &str) -> Option<Update> {
    let line = line.trim_end_matches(['\r', '\n']).trim();
    let payload = match line.strip_prefix("data:") {
      Some(rest) => rest.trim_start(),
      None if line.starts_with('{') => line,
      None => return None,
    };
    let wire: Wire = serde_json::from_str(payload).ok()?;
    Some(self.apply(wire))
  }

  /// Feeds any slice of the stream, including one cut mid-line
  pub fn feed_chunk(&mut self, chunk: &str) -> Vec<Update> {
    self.buffer.push_str(chunk);
    let mut updates = Vec::new();
    while let Some(newline) = self.buffer.find('\n') {
      let line: String = self.buffer.drain(..=newline).collect();
      if let Some(update) = self.feed_line(&line) {
        updates.push(update);
      }
    }
    // ponytail: the buffer only grows to the end of a line; a stream without
    // '\n' would run out of memory first, and such a server is broken anyway.
    updates
  }

  /// Feeds the `/rest/v1/last-status` response, which has the same shape as an SSE frame
  pub fn feed_rest(&mut self, json: &str) -> Option<Update> {
    let wire: Wire = serde_json::from_str(json).ok()?;
    Some(self.apply(wire))
  }

  fn apply(&mut self, wire: Wire) -> Update {
    match wire {
      Wire::Heartbeat { time } => {
        self.last_heartbeat = Some(time.max(self.last_heartbeat.unwrap_or(i64::MIN)));
        Update::Heartbeat { time }
      }
      Wire::Pepito { way, time, img } => {
        if let Some(previous) = &self.last
          && previous.time == time
          && previous.way == way
        {
          return Update::Duplicate { time };
        }
        let changed = self
          .last
          .as_ref()
          .is_none_or(|previous| previous.way != way);
        match way {
          Way::In => self.count_in += 1,
          Way::Out => self.count_out += 1,
        }
        let sighting = Sighting {
          way,
          time,
          img,
          text: None,
        };
        // An event older than the cache (a late reconnect) still counts, but it
        // must not roll the state backwards.
        if self
          .last
          .as_ref()
          .is_none_or(|previous| time >= previous.time)
        {
          self.last = Some(sighting.clone());
        }
        Update::Sighting { sighting, changed }
      }
    }
  }

  /// The whole state in one object: where the cat is, how stale the cache is,
  /// whether the heartbeat is alive
  ///
  /// The shape of this JSON is the public contract of every binding, which is
  /// why it is assembled here once instead of separately in each of them.
  #[must_use]
  pub fn status(&self, now: i64) -> Status {
    Status {
      state: self.state().as_str(),
      since: self.state().since(),
      last: self.last.clone(),
      age: self.age(now),
      heartbeat_age: self.heartbeat_age(now),
      healthy: self.healthy(now),
      count_in: self.count_in,
      count_out: self.count_out,
    }
  }

  #[must_use]
  pub fn state(&self) -> State {
    match &self.last {
      Some(sighting) if sighting.way == Way::In => State::Home {
        since: sighting.time,
      },
      Some(sighting) => State::Away {
        since: sighting.time,
      },
      None => State::Unknown,
    }
  }

  /// Age of the cache in seconds
  #[must_use]
  pub fn age(&self, now: i64) -> Option<i64> {
    self.last.as_ref().map(|sighting| now - sighting.time)
  }

  /// Whether a REST refresh is worth it
  #[must_use]
  pub fn is_stale(&self, now: i64, max_age: i64) -> bool {
    self.age(now).is_none_or(|age| age > max_age)
  }

  #[must_use]
  pub fn heartbeat_age(&self, now: i64) -> Option<i64> {
    self.last_heartbeat.map(|beat| now - beat)
  }

  /// Whether the stream is alive. No heartbeat means it is time to reconnect.
  #[must_use]
  pub fn healthy(&self, now: i64) -> bool {
    self
      .heartbeat_age(now)
      .is_some_and(|age| age <= HEARTBEAT_TIMEOUT)
  }

  /// Cache to persist between processes, which matters for PHP where every
  /// request starts from nothing
  #[must_use]
  pub fn snapshot(&self) -> String {
    serde_json::to_string(self).unwrap_or_else(|_| "{}".into())
  }

  #[must_use]
  pub fn restore(json: &str) -> Tracker {
    serde_json::from_str(json).unwrap_or_default()
  }
}

/// The tracker's state captured at one instant. See [`Tracker::status`].
#[derive(Debug, Clone, Serialize)]
pub struct Status {
  pub state: &'static str,
  pub since: Option<i64>,
  pub last: Option<Sighting>,
  pub age: Option<i64>,
  pub heartbeat_age: Option<i64>,
  pub healthy: bool,
  pub count_in: u32,
  pub count_out: u32,
}
