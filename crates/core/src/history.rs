//! The archive from Clement87/Pepito-data reduced to
//! the same `Sighting` values as the live stream, plus statistics.

use chrono::{DateTime, Datelike, Timelike};
use serde::{Deserialize, Serialize};

use crate::types::{Sighting, Way};

#[derive(Deserialize)]
struct Tweet {
  full_text: String,
  way: String,
  created_at: String,
  #[serde(default)]
  media: String,
}

/// Parses Twitter's own format.
#[must_use]
pub fn parse_twitter_time(text: &str) -> Option<i64> {
  DateTime::parse_from_str(text, "%a %b %d %H:%M:%S %z %Y")
    .ok()
    .map(|parsed| parsed.timestamp())
}

/// Parses the tweet archive into sightings, sorted by time
///
/// Entries with an unknown direction or a broken date are skipped rather than
/// failing the whole file.
///
/// # Errors
///
/// Returns the serde error when the input is not a JSON array of tweets. It
/// carries the line and column, which a stringly-typed error would throw away.
pub fn parse_history(json: &str) -> Result<Vec<Sighting>, serde_json::Error> {
  let tweets: Vec<Tweet> = serde_json::from_str(json)?;
  let mut sightings: Vec<Sighting> = tweets
    .into_iter()
    .filter_map(|tweet| {
      Some(Sighting {
        way: tweet.way.parse().ok()?,
        time: parse_twitter_time(&tweet.created_at)?,
        img: if tweet.media.is_empty() {
          None
        } else {
          Some(tweet.media)
        },
        text: Some(tweet.full_text),
      })
    })
    .collect();
  sightings.sort_by_key(|sighting| sighting.time);
  Ok(sightings)
}

#[derive(Debug, Clone, Copy, Serialize)]
pub struct Outing {
  pub out_at: i64,
  pub in_at: i64,
  pub secs: i64,
}

#[derive(Debug, Clone, Serialize)]
pub struct Stats {
  pub total: usize,
  pub ins: usize,
  pub outs: usize,
  pub first: Option<i64>,
  pub last: Option<i64>,
  pub outings: usize,
  /// Departures with no return, in other words holes in the data.
  pub unpaired: usize,
  pub avg_outing_secs: i64,
  pub median_outing_secs: i64,
  pub longest: Option<Outing>,
  pub shortest: Option<Outing>,
  pub by_hour_out: [u32; 24],
  pub by_hour_in: [u32; 24],
  /// 0 = Sunday
  pub by_weekday_out: [u32; 7],
}

/// Pairs every `in` with the last `out` before it
///
/// Two `out` events in a row (and the archive has a fair few) overwrite each
/// other, because they mean a return went missing.
#[must_use]
pub fn stats(sightings: &[Sighting]) -> Stats {
  let mut stats = Stats {
    total: sightings.len(),
    ins: 0,
    outs: 0,
    first: sightings.first().map(|sighting| sighting.time),
    last: sightings.last().map(|sighting| sighting.time),
    outings: 0,
    unpaired: 0,
    avg_outing_secs: 0,
    median_outing_secs: 0,
    longest: None,
    shortest: None,
    by_hour_out: [0; 24],
    by_hour_in: [0; 24],
    by_weekday_out: [0; 7],
  };
  let mut pending: Option<i64> = None;
  let mut durations: Vec<i64> = Vec::new();

  for sighting in sightings {
    let utc = DateTime::from_timestamp(sighting.time, 0);
    let hour = utc.map_or(0, |moment| moment.hour() as usize);
    match sighting.way {
      Way::Out => {
        stats.outs += 1;
        stats.by_hour_out[hour] += 1;
        if let Some(moment) = utc {
          stats.by_weekday_out[moment.weekday().num_days_from_sunday() as usize] += 1;
        }
        if pending.replace(sighting.time).is_some() {
          stats.unpaired += 1;
        }
      }
      Way::In => {
        stats.ins += 1;
        stats.by_hour_in[hour] += 1;
        if let Some(out_at) = pending.take() {
          let outing = Outing {
            out_at,
            in_at: sighting.time,
            secs: sighting.time - out_at,
          };
          durations.push(outing.secs);
          if stats
            .longest
            .is_none_or(|longest| outing.secs > longest.secs)
          {
            stats.longest = Some(outing);
          }
          if stats
            .shortest
            .is_none_or(|shortest| outing.secs < shortest.secs)
          {
            stats.shortest = Some(outing);
          }
        }
      }
    }
  }
  if pending.is_some() {
    stats.unpaired += 1;
  }
  stats.outings = durations.len();
  if !durations.is_empty() {
    let count = i64::try_from(durations.len()).unwrap_or(i64::MAX);
    stats.avg_outing_secs = durations.iter().sum::<i64>() / count;
    durations.sort_unstable();
    stats.median_outing_secs = durations[durations.len() / 2];
  }
  stats
}
