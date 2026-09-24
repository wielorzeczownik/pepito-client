import { InvalidArchiveError } from './errors.js';
import type { Outing, Sighting, Stats, Status, Update, Way } from './types.js';

/**
 * With no heartbeat for this many seconds the connection counts as dead
 * (the server beats roughly every 10 s).
 */
const HEARTBEAT_TIMEOUT = 30;

const U32_MAX = 4_294_967_295;

type Wire =
  | { event: 'heartbeat'; time: number }
  | { event: 'pepito'; way: Way; time: number; img: string | undefined };

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

const isInt = (value: unknown): value is number => Number.isSafeInteger(value);

const isWay = (value: unknown): value is Way =>
  value === 'in' || value === 'out';

function optionalString(value: unknown): string | undefined | false {
  return value === undefined || value === null
    ? undefined
    : typeof value === 'string' && value;
}

function sighting(
  way: Way,
  time: number,
  img?: string,
  text?: string
): Sighting {
  const result: Sighting = { way, time };
  if (img !== undefined) {
    result.img = img;
  }
  if (text !== undefined) {
    result.text = text;
  }
  return result;
}

function parseWire(json: string): Wire | undefined {
  let raw: unknown;
  try {
    raw = JSON.parse(json);
  } catch {
    return undefined;
  }
  if (!isRecord(raw) || !isInt(raw.time)) {
    return undefined;
  }
  if (raw.event === 'heartbeat') {
    return { event: 'heartbeat', time: raw.time };
  }
  const img = optionalString(raw.img);
  return img !== false && raw.event === 'pepito' && isWay(raw.type)
    ? { event: 'pepito', way: raw.type, time: raw.time, img }
    : undefined;
}

function parseSighting(raw: unknown): Sighting | undefined {
  if (!isRecord(raw) || !isWay(raw.way) || !isInt(raw.time)) {
    return undefined;
  }
  const img = optionalString(raw.img);
  const text = optionalString(raw.text);
  return img === false || text === false
    ? undefined
    : sighting(raw.way, raw.time, img, text);
}

const isCount = (value: unknown): value is number =>
  isInt(value) && value >= 0 && value <= U32_MAX;

/**
 * State, cache, dedup and heartbeat.
 */
export class Tracker {
  #last: Sighting | undefined;
  #lastHeartbeat: number | undefined;
  #countIn = 0;
  #countOut = 0;
  /*
   * Unfinished line carried between stream chunks.
   */
  #buffer = '';

  /**
   * A snapshot that does not parse means a fresh tracker.
   */
  constructor(snapshot?: string | null) {
    if (snapshot === undefined || snapshot === null) {
      return;
    }
    let raw: unknown;
    try {
      raw = JSON.parse(snapshot);
    } catch {
      return;
    }
    if (!isRecord(raw) || !isCount(raw.count_in) || !isCount(raw.count_out)) {
      return;
    }
    const hasLast = raw.last !== undefined && raw.last !== null;
    const last = hasLast ? parseSighting(raw.last) : undefined;
    const beat = raw.last_heartbeat;
    const isBeatValid = beat === undefined || beat === null || isInt(beat);
    if (!isBeatValid || (hasLast && !last)) {
      return;
    }
    this.#last = last;
    this.#lastHeartbeat = isInt(beat) ? beat : undefined;
    this.#countIn = raw.count_in;
    this.#countOut = raw.count_out;
  }

  #feedLine(line: string): Update | undefined {
    const trimmed = line.trim();
    let payload: string;
    if (trimmed.startsWith('data:')) {
      payload = trimmed.slice(5).trimStart();
    } else if (trimmed.startsWith('{')) {
      payload = trimmed;
    } else {
      return undefined;
    }
    const wire = parseWire(payload);
    return wire ? this.#apply(wire) : undefined;
  }

  #apply(wire: Wire): Update {
    if (wire.event === 'heartbeat') {
      this.#lastHeartbeat = Math.max(
        wire.time,
        this.#lastHeartbeat ?? Number.MIN_SAFE_INTEGER
      );
      return { kind: 'heartbeat', time: wire.time };
    }
    const previous = this.#last;
    if (previous?.time === wire.time && previous.way === wire.way) {
      return { kind: 'duplicate', time: wire.time };
    }
    const isChanged = previous?.way !== wire.way;
    if (wire.way === 'in') {
      this.#countIn += 1;
    } else {
      this.#countOut += 1;
    }
    const seen = sighting(wire.way, wire.time, wire.img);
    // An event older than the cache (a late reconnect) still counts, but it
    // must not roll the state backwards.
    if (!previous || wire.time >= previous.time) {
      this.#last = seen;
    }
    return { kind: 'sighting', ...seen, changed: isChanged };
  }

  /**
   * Feeds any slice of the SSE stream, including one cut mid-line.
   */
  feed(chunk: string): Update[] {
    this.#buffer += chunk;
    const updates: Update[] = [];
    let newline: number;
    while ((newline = this.#buffer.indexOf('\n')) !== -1) {
      const line = this.#buffer.slice(0, newline + 1);
      this.#buffer = this.#buffer.slice(newline + 1);
      const update = this.#feedLine(line);
      if (update) {
        updates.push(update);
      }
    }
    return updates;
  }

  /**
   * Feeds the REST last-status response. Returns one event or `undefined`.
   */
  feedRest(json: string): Update | undefined {
    const wire = parseWire(json);
    return wire ? this.#apply(wire) : undefined;
  }

  /**
   * State, cache age and heartbeat health, timed by the local clock.
   */
  get state(): Status {
    return this.status(Math.trunc(Date.now() / 1000));
  }

  status(now: number): Status {
    const last = this.#last;
    const heartbeatAge =
      this.#lastHeartbeat === undefined ? undefined : now - this.#lastHeartbeat;
    let state: Status['state'] = 'unknown';
    if (last) {
      state = last.way === 'in' ? 'home' : 'away';
    }
    return {
      state,
      since: last?.time,
      last: last ? { ...last } : undefined,
      age: last ? now - last.time : undefined,
      heartbeat_age: heartbeatAge,
      healthy: heartbeatAge !== undefined && heartbeatAge <= HEARTBEAT_TIMEOUT,
      count_in: this.#countIn,
      count_out: this.#countOut,
    };
  }

  /**
   * Whether a REST refresh is worth it.
   */
  isStale(maxAge: number): boolean {
    const { age } = this.state;
    return age === undefined || age > maxAge;
  }

  /**
   * The same JSON as the wasm build writes, so either backend can restore it.
   */
  snapshot(): string {
    return JSON.stringify({
      last: this.#last,
      last_heartbeat: this.#lastHeartbeat,
      count_in: this.#countIn,
      count_out: this.#countOut,
    });
  }

  /**
   * Nothing to free here, kept so both backends look the same.
   */
  free(): void {}
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTHS = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
];
const TWITTER_TIME =
  /^(\w{3}) (\w{3}) (\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2}) ([+-])(\d{2}):?(\d{2}) (\d{4})$/;

/**
 * Twitter's own format, `Sun Nov 13 10:47:15 +0000 2011`
 */
export function parseTwitterTime(text: string): number | undefined {
  const match = TWITTER_TIME.exec(text);
  if (!match) {
    return undefined;
  }
  const [, weekday, monthName, ...rest] = match;
  const [day, hour, minute, second, sign, offH, offM, year] = rest.map(
    (part, index) => (index === 4 ? part : Number(part))
  ) as [number, number, number, number, string, number, number, number];
  const month = MONTHS.indexOf(monthName ?? '');
  if (month === -1 || hour > 23 || minute > 59 || second > 59 || offM > 59) {
    return undefined;
  }
  const date = new Date(Date.UTC(year, month, day));
  if (
    date.getUTCDate() !== day ||
    date.getUTCMonth() !== month ||
    WEEKDAYS[date.getUTCDay()] !== weekday
  ) {
    return undefined;
  }
  const offset = (sign === '-' ? -1 : 1) * (offH * 3600 + offM * 60);
  return date.getTime() / 1000 + hour * 3600 + minute * 60 + second - offset;
}

/**
 * The tweet archive as sightings, sorted by time.
 */
export function historyParse(json: string): Sighting[] {
  let tweets: unknown;
  try {
    tweets = JSON.parse(json);
  } catch (error) {
    throw new InvalidArchiveError(String(error));
  }
  if (!Array.isArray(tweets)) {
    throw new InvalidArchiveError('expected an array of tweets');
  }
  const sightings: Sighting[] = [];
  for (const [index, tweet] of tweets.entries()) {
    if (!isRecord(tweet)) {
      throw new InvalidArchiveError(`tweet ${index} is not a tweet`);
    }
    // A missing media means none, but a null one is a broken tweet.
    const { full_text: text, way, created_at: createdAt, media = '' } = tweet;
    if (
      typeof text !== 'string' ||
      typeof way !== 'string' ||
      typeof createdAt !== 'string' ||
      typeof media !== 'string'
    ) {
      throw new InvalidArchiveError(`tweet ${index} is not a tweet`);
    }
    const time = parseTwitterTime(createdAt);
    if (time !== undefined && isWay(way)) {
      sightings.push(sighting(way, time, media || undefined, text));
    }
  }
  return sightings.sort((left, right) => left.time - right.time);
}

/**
 * Pairs every `in` with the last `out` before it. Two `out` events in a row
 * overwrite each other, because they mean a return went missing.
 */
export function stats(sightings: Sighting[]): Stats {
  const result: Stats = {
    total: sightings.length,
    ins: 0,
    outs: 0,
    first: sightings.at(0)?.time,
    last: sightings.at(-1)?.time,
    outings: 0,
    unpaired: 0,
    avg_outing_secs: 0,
    median_outing_secs: 0,
    longest: undefined,
    shortest: undefined,
    by_hour_out: Array.from({ length: 24 }, () => 0),
    by_hour_in: Array.from({ length: 24 }, () => 0),
    by_weekday_out: Array.from({ length: 7 }, () => 0),
  };
  let pending: number | undefined;
  const durations: number[] = [];

  for (const { way, time } of sightings) {
    const moment = new Date(time * 1000);
    const hour = moment.getUTCHours();
    if (way === 'out') {
      result.outs += 1;
      result.by_hour_out[hour] += 1;
      result.by_weekday_out[moment.getUTCDay()] += 1;
      if (pending !== undefined) {
        result.unpaired += 1;
      }
      pending = time;
      continue;
    }
    result.ins += 1;
    result.by_hour_in[hour] += 1;
    if (pending === undefined) {
      continue;
    }
    const outing: Outing = {
      out_at: pending,
      in_at: time,
      secs: time - pending,
    };
    pending = undefined;
    durations.push(outing.secs);
    if (!result.longest || outing.secs > result.longest.secs) {
      result.longest = outing;
    }
    if (!result.shortest || outing.secs < result.shortest.secs) {
      result.shortest = outing;
    }
  }
  if (pending !== undefined) {
    result.unpaired += 1;
  }
  result.outings = durations.length;
  if (durations.length > 0) {
    const sum = durations.reduce((total, secs) => total + secs, 0);
    result.avg_outing_secs = Math.trunc(sum / durations.length);
    durations.sort((left, right) => left - right);
    result.median_outing_secs = durations[Math.floor(durations.length / 2)];
  }
  return result;
}

export function historyStats(json: string): Stats {
  return stats(historyParse(json));
}
