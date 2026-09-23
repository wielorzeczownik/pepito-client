//! Shared core of the Pepito API client. No IO, no knowledge of any binding.

#![cfg_attr(docsrs, feature(doc_cfg))]

pub mod history;
pub mod tracker;
pub mod types;

#[cfg(feature = "net")]
#[cfg_attr(docsrs, doc(cfg(feature = "net")))]
pub mod net;

pub use history::{Outing, Stats, parse_history, stats};
pub use tracker::{HEARTBEAT_TIMEOUT, Status, Tracker};
pub use types::{Sighting, State, Update, Way};

pub const SSE_URL: &str = "https://api.thecatdoor.com/sse/v1/events";
pub const REST_URL: &str = "https://api.thecatdoor.com/rest/v1/last-status";
pub const ARCHIVE_URL: &str =
  "https://raw.githubusercontent.com/Clement87/Pepito-data/main/tweets.json";

#[cfg(test)]
mod tests {
  use super::*;

  #[test]
  fn sse_stream_drives_state() {
    let mut tracker = Tracker::new();
    assert_eq!(tracker.state(), State::Unknown);
    assert!(tracker.feed_line(": a comment").is_none());
    assert!(tracker.feed_line("event: message").is_none());

    assert_eq!(
      tracker.feed_line(r#"data: {"event":"heartbeat","time":100}"#),
      Some(Update::Heartbeat { time: 100 })
    );
    assert!(tracker.healthy(120));
    assert!(!tracker.healthy(200));

    let out =
      tracker.feed_line(r#"data: {"event":"pepito","type":"out","time":110,"img":"a.jpg"}"#);
    assert!(matches!(out, Some(Update::Sighting { changed: true, .. })));
    assert_eq!(tracker.state(), State::Away { since: 110 });

    // The same frame a second time, after a reconnect.
    assert_eq!(
      tracker.feed_line(r#"data: {"event":"pepito","type":"out","time":110,"img":"a.jpg"}"#),
      Some(Update::Duplicate { time: 110 })
    );
    // Another "out" with no "in": a real event, but the state does not move.
    assert!(matches!(
      tracker.feed_line(r#"data: {"event":"pepito","type":"out","time":150}"#),
      Some(Update::Sighting { changed: false, .. })
    ));

    tracker.feed_line(r#"data: {"event":"pepito","type":"in","time":200}"#);
    assert_eq!(tracker.state(), State::Home { since: 200 });
    assert_eq!((tracker.count_in, tracker.count_out), (1, 2));
    assert!(tracker.is_stale(400, 100) && !tracker.is_stale(250, 100));
  }

  #[test]
  fn chunks_may_split_lines_anywhere() {
    let mut tracker = Tracker::new();
    assert!(tracker.feed_chunk("data: {\"event\":\"pepi").is_empty());
    let updates = tracker.feed_chunk(
      "to\",\"type\":\"in\",\"time\":5}\n\ndata: {\"event\":\"heartbeat\",\"time\":6}\n",
    );
    assert_eq!(updates.len(), 2);
    assert_eq!(tracker.state(), State::Home { since: 5 });
  }

  #[test]
  fn rest_and_snapshot_roundtrip() {
    let mut tracker = Tracker::new();
    tracker.feed_rest(r#"{"event":"pepito","type":"in","time":1753422874,"img":"x.jpg"}"#);
    let restored = Tracker::restore(&tracker.snapshot());
    assert_eq!(
      restored.state(),
      State::Home {
        since: 1_753_422_874
      }
    );
    assert_eq!(Tracker::restore("garbage").state(), State::Unknown);
  }

  /// The JSON shape of `status()` is the contract of both bindings, so renaming
  /// a field has to make a noise right here
  #[test]
  fn status_shape_is_the_binding_contract() {
    let mut tracker = Tracker::new();
    tracker.feed_rest(r#"{"event":"pepito","type":"out","time":1000,"img":"a.jpg"}"#);
    let status = serde_json::to_string(&tracker.status(1300)).unwrap();
    let status: serde_json::Value = serde_json::from_str(&status).unwrap();
    assert_eq!(status["state"], "away");
    assert_eq!(status["since"], 1000);
    assert_eq!(status["age"], 300);
    assert_eq!(status["healthy"], false);
    assert_eq!(status["heartbeat_age"], serde_json::Value::Null);
    assert_eq!(status["count_out"], 1);
    assert_eq!(status["last"]["img"], "a.jpg");
  }

  #[test]
  fn history_parses_and_pairs_outings() {
    let json = r#"[
      {"full_text":"Pepito est sorti (12:47:41)","way":"out","created_at":"Sun Nov 13 10:47:15 +0000 2011","media":""},
      {"full_text":"Pepito est sorti (17:20:30)","way":"out","created_at":"Sun Nov 13 15:20:04 +0000 2011","media":""},
      {"full_text":"Pepito est rentre (17:30:30)","way":"in","created_at":"Sun Nov 13 15:30:04 +0000 2011","media":"m.jpg"},
      {"full_text":"broken","way":"sideways","created_at":"Sun Nov 13 15:30:04 +0000 2011","media":""}
    ]"#;
    let sightings = parse_history(json).unwrap();
    assert_eq!(sightings.len(), 3);
    assert_eq!(sightings[0].time, 1_321_181_235);
    assert_eq!(sightings[2].img.as_deref(), Some("m.jpg"));

    let stats = stats(&sightings);
    assert_eq!(
      (stats.outs, stats.ins, stats.outings, stats.unpaired),
      (2, 1, 1, 1)
    );
    assert_eq!(stats.longest.unwrap().secs, 600);
    assert_eq!(stats.by_hour_out[10], 1);
    assert_eq!(stats.by_weekday_out[0], 2, "13 Nov 2011 was a Sunday");
  }
}
