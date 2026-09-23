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
 * A `Pepito` was built before `await init()` resolved.
 */
export class NotInitializedError extends PepitoError {}

/**
 * The tracker was used after `close()` freed its wasm memory.
 */
export class TrackerClosedError extends PepitoError {}

/**
 * The live stream could not be opened or stayed broken.
 *
 * This one arrives through the `error` event rather than as a throw, because
 * `watch()` keeps reconnecting instead of giving up.
 */
export class StreamError extends PepitoError {
  readonly status: number | undefined;

  constructor(message: string, status?: number) {
    super(message);
    this.status = status;
  }
}
