import * as core from './core.js';
import { StreamError, TrackerClosedError } from './errors.js';
import type {
  PepitoEventMap,
  PepitoOptions,
  Sighting,
  Stats,
  Status,
  Update,
  WatchOptions,
} from './types.js';

export {
  InvalidArchiveError,
  NotInitializedError,
  PepitoError,
  StreamError,
  TrackerClosedError,
} from './errors.js';
export type {
  Outing,
  PepitoEventMap,
  PepitoOptions,
  Sighting,
  Stats,
  Status,
  Update,
  WatchOptions,
  Way,
} from './types.js';

export const SSE_URL = 'https://api.thecatdoor.com/sse/v1/events';
export const REST_URL = 'https://api.thecatdoor.com/rest/v1/last-status';
export const ARCHIVE_URL =
  'https://raw.githubusercontent.com/Clement87/Pepito-data/main/tweets.json';

/**
 * What a backend has to provide: the plain TS port in `core.ts` by default,
 * the wasm build of the Rust core from `/wasm`.
 */
export interface Backend {
  Tracker: new (snapshot?: string) => {
    feed(chunk: string): Update[];
    feedRest(json: string): Update | undefined;
    readonly state: Status;
    isStale(maxAge: number): boolean;
    snapshot(): string;
    free(): void;
  };
  historyStats(json: string): Stats;
  historyParse(json: string): Sighting[];
}

type Tracker = InstanceType<Backend['Tracker']>;

/**
 * Events: `in`, `out`, `change`, `heartbeat`, `update`, `error`.
 * The payload sits in `event.detail`.
 */
export class Pepito extends EventTarget {
  protected static backend: Backend = core;

  /**
   * Statistics from the archive. See {@link ARCHIVE_URL} for where to get it.
   */
  static historyStats(json: string): Stats {
    return this.backend.historyStats(json);
  }

  /**
   * The archive as the same events as the live stream, see {@link ARCHIVE_URL}.
   * Large, tens of thousands of entries.
   */
  static historyParse(json: string): Sighting[] {
    return this.backend.historyParse(json);
  }

  #tracker: Tracker | undefined;
  #abort: AbortController | undefined;
  #fetch: typeof fetch;

  constructor(snapshot?: string | object, options: PepitoOptions = {}) {
    super();
    // Wrapped, since the browser's fetch throws when called on anything but window.
    this.#fetch = options.fetch ?? ((input, request) => fetch(input, request));
    const json =
      typeof snapshot === 'object' ? JSON.stringify(snapshot) : snapshot;
    this.#tracker = new new.target.backend.Tracker(json);
  }

  /**
   * Throws something readable instead of blowing up on a pointer that
   * `close()` already freed (wasm build).
   */
  get #live(): Tracker {
    if (this.#tracker === undefined) {
      throw new TrackerClosedError('this tracker was closed by close()');
    }
    return this.#tracker;
  }

  #emit(update: Update): void {
    const send = (name: keyof PepitoEventMap): void => {
      this.dispatchEvent(new CustomEvent(name, { detail: update }));
    };

    send('update');
    if (update.kind === 'heartbeat') {
      send('heartbeat');
      return;
    }
    if (update.kind === 'duplicate') {
      return;
    }
    send(update.way);
    if (update.changed) {
      send('change');
    }
  }

  /**
   * Reads one connection until it ends. `onOpen` fires once the response
   * arrives, which is what lets the caller reset its backoff.
   */
  async #readStream(
    url: string,
    idleMs: number,
    stop: AbortSignal,
    onOpen: () => void
  ): Promise<void> {
    const connection = new AbortController();
    const onStop = (): void => connection.abort();
    stop.addEventListener('abort', onStop, { once: true });
    let watchdog = setTimeout(() => connection.abort(), idleMs);
    try {
      const response = await this.#fetch(url, {
        signal: connection.signal,
        headers: { accept: 'text/event-stream' },
      });
      if (!response.ok) {
        throw new StreamError(`HTTP ${response.status}`, response.status);
      }
      if (!response.body) {
        throw new StreamError('response had no body');
      }
      onOpen();
      const reader = response.body.getReader();
      // One decoder per connection: {stream:true} keeps the tail of a
      // multi-byte character between calls and must not be shared.
      const decoder = new TextDecoder();
      for (;;) {
        const { done, value } = await reader.read();
        if (done) {
          break;
        }
        clearTimeout(watchdog);
        watchdog = setTimeout(() => connection.abort(), idleMs);
        this.feed(decoder.decode(value, { stream: true }));
      }
    } finally {
      clearTimeout(watchdog);
      stop.removeEventListener('abort', onStop);
    }
  }

  // Typed listening: `event.detail` knows its own shape without a cast.
  override addEventListener<K extends keyof PepitoEventMap>(
    type: K,
    listener: (event: PepitoEventMap[K]) => void,
    options?: boolean | AddEventListenerOptions
  ): void;
  override addEventListener(
    type: string,
    listener: EventListenerOrEventListenerObject | null,
    options?: boolean | AddEventListenerOptions
  ): void;
  override addEventListener(
    type: string,
    listener: EventListenerOrEventListenerObject | null,
    options?: boolean | AddEventListenerOptions
  ): void {
    super.addEventListener(type, listener, options);
  }

  /**
   * Feeds a slice of the stream, including one cut mid-line.
   */
  feed(chunk: string): Update[] {
    const updates = this.#live.feed(chunk);
    for (const update of updates) {
      this.#emit(update);
    }
    return updates;
  }

  /**
   * One REST shot, so the cache does not start out empty.
   */
  async refresh(): Promise<Update | undefined> {
    const response = await this.#fetch(REST_URL, {
      headers: { accept: 'application/json' },
    });
    if (!response.ok) {
      throw new StreamError(`HTTP ${response.status}`, response.status);
    }
    const body = await response.text();
    const update = this.#live.feedRest(body);
    if (update) {
      this.#emit(update);
    }
    return update;
  }

  /**
   * State, cache age and heartbeat health in one object.
   */
  get state(): Status {
    return this.#live.state;
  }

  /**
   * Whether the cache is older than `maxAge` seconds.
   */
  isStale(maxAge: number): boolean {
    return this.#live.isStale(maxAge);
  }

  /**
   * Cache to store wherever you like.
   */
  snapshot(): string {
    return this.#live.snapshot();
  }

  /**
   * Follows the live stream, reconnecting and watching the heartbeat.
   * It does not finish until you call `stop()`.
   */
  async watch({
    url = SSE_URL,
    idleTimeout = 45,
    maxBackoff = 60,
  }: WatchOptions = {}) {
    const outer = new AbortController();
    this.#abort = outer;
    let backoff = 1;

    while (!outer.signal.aborted) {
      try {
        await this.#readStream(url, idleTimeout * 1000, outer.signal, () => {
          backoff = 1;
        });
      } catch (error) {
        if (!outer.signal.aborted) {
          this.dispatchEvent(new CustomEvent('error', { detail: error }));
        }
      }
      if (outer.signal.aborted) {
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, backoff * 1000));
      backoff = Math.min(backoff * 2, maxBackoff);
    }
  }

  stop(): void {
    this.#abort?.abort();
  }

  /**
   * Stops `watch()` and, on the wasm build, frees the wasm memory, which
   * otherwise lives until the process ends.
   */
  close(): void {
    this.stop();
    this.#tracker?.free();
    this.#tracker = undefined;
  }
}
