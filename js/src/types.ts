export type Way = 'in' | 'out';

export interface Sighting {
  way: Way;
  /**
   * Unix timestamp in seconds, UTC.
   */
  time: number;
  img?: string;
  text?: string;
}

export type Update =
  | { kind: 'heartbeat'; time: number }
  | ({ kind: 'sighting'; changed: boolean } & Sighting)
  | { kind: 'duplicate'; time: number };

export interface Status {
  state: 'home' | 'away' | 'unknown';
  since: number | null;
  last: Sighting | null;
  age: number | null;
  heartbeat_age: number | null;
  healthy: boolean;
  count_in: number;
  count_out: number;
}

export interface Outing {
  out_at: number;
  in_at: number;
  secs: number;
}

export interface Stats {
  total: number;
  ins: number;
  outs: number;
  first: number | null;
  last: number | null;
  outings: number;
  unpaired: number;
  avg_outing_secs: number;
  median_outing_secs: number;
  longest: Outing | null;
  shortest: Outing | null;
  by_hour_out: number[];
  by_hour_in: number[];
  by_weekday_out: number[];
}

/**
 * Events dispatched by [`Pepito`].
 */
export interface PepitoEventMap {
  update: CustomEvent<Update>;
  heartbeat: CustomEvent<Update>;
  in: CustomEvent<Update>;
  out: CustomEvent<Update>;
  change: CustomEvent<Update>;
  error: CustomEvent<unknown>;
}

export interface PepitoOptions {
  /**
   * Your own fetch. Defaults to the global `fetch`.
   */
  fetch?: typeof fetch;
}

export interface WatchOptions {
  url?: string;
  /**
   * Seconds of silence before the connection counts as hung. Default 45.
   */
  idleTimeout?: number;
  /**
   * Seconds the doubling wait between reconnects stops at. Default 60.
   */
  maxBackoff?: number;
}
