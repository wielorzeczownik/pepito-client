//! cargo run --example watch --features rustls

use pepito_client::{Tracker, Update, net};

#[tokio::main]
async fn main() {
  let mut tracker = Tracker::new();
  net::refresh(None, &mut tracker).await.expect("REST");
  println!("start: {:?}", tracker.state());

  net::watch(
    None,
    &mut tracker,
    &net::WatchOptions::default(),
    |update, tracker| match update {
      Update::Sighting {
        sighting,
        changed: true,
      } => {
        println!(
          "{} at {} -> {:?}",
          sighting.way.as_str(),
          sighting.time,
          tracker.state()
        );
      }
      Update::Heartbeat { time } => println!("heartbeat {time}"),
      _ => {}
    },
    || false,
  )
  .await;
}
