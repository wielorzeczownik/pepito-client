/**
 * Base of every error this package throws.
 */
export class PepitoError extends Error {
  constructor(message: string) {
    super(message);
    this.name = new.target.name;
  }
}

/**
 * The wasm build was used before `await init()` from
 * `@wielorzeczownik/pepito-client/wasm` resolved.
 */
export class NotInitializedError extends PepitoError {}

/**
 * The tracker was used after `close()`.
 */
export class TrackerClosedError extends PepitoError {}

/**
 * The archive handed to `historyStats()` or `historyParse()` is not a JSON
 * array of tweets.
 */
export class InvalidArchiveError extends PepitoError {}

/**
 * The live stream or the REST call answered with an error, or the stream
 * stayed broken.
 *
 * From `watch()` this one arrives through the `error` event rather than as a
 * throw, because `watch()` keeps reconnecting instead of giving up.
 */
export class StreamError extends PepitoError {
  readonly status: number | undefined;

  constructor(message: string, status?: number) {
    super(message);
    this.status = status;
  }
}
