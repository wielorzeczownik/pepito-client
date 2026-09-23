//! Types shared by the live stream and the historical data

use std::fmt;
use std::str::FromStr;

use serde::{Deserialize, Serialize};

/// Direction the cat took through the flap
#[derive(Debug, Clone, Copy, PartialEq, Eq, Serialize, Deserialize)]
#[serde(rename_all = "lowercase")]
pub enum Way {
  In,
  Out,
}

impl Way {
  #[must_use]
  pub fn as_str(self) -> &'static str {
    match self {
      Way::In => "in",
      Way::Out => "out",
    }
  }
}

impl fmt::Display for Way {
  fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
    formatter.write_str(self.as_str())
  }
}

/// Returned by [`Way::from_str`] for anything other than `"in"`/`"out"`
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub struct ParseWayError;

impl fmt::Display for ParseWayError {
  fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
    formatter.write_str("expected \"in\" or \"out\"")
  }
}

impl std::error::Error for ParseWayError {}

impl FromStr for Way {
  type Err = ParseWayError;

  fn from_str(text: &str) -> Result<Self, Self::Err> {
    match text {
      "in" => Ok(Way::In),
      "out" => Ok(Way::Out),
      _ => Err(ParseWayError),
    }
  }
}

/// A single crossing, either from SSE or from the tweet archive
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct Sighting {
  pub way: Way,
  /// Unix timestamp in seconds, UTC
  pub time: i64,
  #[serde(skip_serializing_if = "Option::is_none", default)]
  pub img: Option<String>,
  /// Original tweet text, historical data only
  #[serde(skip_serializing_if = "Option::is_none", default)]
  pub text: Option<String>,
}

/// Raw payload of one `data:` line from the SSE stream
#[derive(Debug, Clone, Deserialize)]
#[serde(tag = "event")]
pub enum Wire {
  #[serde(rename = "heartbeat")]
  Heartbeat { time: i64 },
  #[serde(rename = "pepito")]
  Pepito {
    #[serde(rename = "type")]
    way: Way,
    time: i64,
    #[serde(default)]
    img: Option<String>,
  },
}

/// Where the cat is. `since` is the time of the last crossing.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Serialize, Deserialize)]
#[serde(tag = "state", rename_all = "lowercase")]
pub enum State {
  Home { since: i64 },
  Away { since: i64 },
  Unknown,
}

impl State {
  #[must_use]
  pub fn as_str(self) -> &'static str {
    match self {
      State::Home { .. } => "home",
      State::Away { .. } => "away",
      State::Unknown => "unknown",
    }
  }
}

impl fmt::Display for State {
  fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
    formatter.write_str(self.as_str())
  }
}

impl State {
  #[must_use]
  pub fn since(self) -> Option<i64> {
    match self {
      State::Home { since } | State::Away { since } => Some(since),
      State::Unknown => None,
    }
  }
}

/// Result of feeding a single line to the tracker
#[derive(Debug, Clone, PartialEq, Eq, Serialize)]
#[serde(tag = "kind", rename_all = "lowercase")]
pub enum Update {
  Heartbeat {
    time: i64,
  },
  Sighting {
    #[serde(flatten)]
    sighting: Sighting,
    /// `true` when this is a real state change, not a repeat of the same direction
    changed: bool,
  },
  /// The exact same event arrived twice (reconnect, Last-Event-ID)
  Duplicate {
    time: i64,
  },
}
