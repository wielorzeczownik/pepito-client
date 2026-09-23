//! cargo run --example watch --features net

use pepito_client::{SSE_URL, Tracker, Update, net};

#[tokio::main]
async fn main() {
  let mut tracker = Tracker::new();
  net::refresh(&mut tracker).await.expect("REST");
  println!("start: {:?}", tracker.state());

  net::watch(
    &mut tracker,
    SSE_URL,
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
