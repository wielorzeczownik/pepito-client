<?php

declare(strict_types=1);

namespace Pepito;

use FFI;
use Pepito\Event\Sighting;
use Pepito\Event\Update;
use Pepito\Exception\InvalidArchiveException;
use Pepito\Exception\LibraryNotFoundException;
use Pepito\Exception\MissingStorageException;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * The Pepito API client, in plain PHP. `withFfi()` swaps in libpepito through
 * FFI instead, with no fallback between the two.
 */
final class Client implements LoggerAwareInterface
{
    public const SSE_URL = 'https://api.thecatdoor.com/sse/v1/events';

    public const REST_URL = 'https://api.thecatdoor.com/rest/v1/last-status';

    public const ARCHIVE_URL = 'https://raw.githubusercontent.com/Clement87/Pepito-data/main/tweets.json';

    private Tracker|FfiTracker $tracker;

    /** PSR-18, used by `refresh()`. */
    private ?ClientInterface $http = null;

    /** PSR-17, goes together with PSR-18. */
    private ?RequestFactoryInterface $requests = null;

    /** PSR-20, the source of "now" for `status()`. */
    private ?ClockInterface $clock = null;

    /** PSR-14, used by `watch()`. */
    private ?EventDispatcherInterface $events = null;

    /** PSR-3. */
    private ?LoggerInterface $logger = null;

    /** PSR-16, used instead of files. */
    private ?CacheInterface $cache = null;

    /** The key under which the snapshot lands in the PSR-16 cache. */
    private string $cacheKey = 'pepito.snapshot';

    public function __construct(?string $snapshot = null)
    {
        $this->tracker = new Tracker($snapshot === '' ? null : $snapshot);
    }

    /**
     * The same client backed by libpepito through FFI, for raw speed. Needs
     * the ffi extension and the library, and throws when either is missing
     * rather than quietly running the plain PHP backend.
     *
     * @throws LibraryNotFoundException
     */
    public static function withFfi(?string $snapshot = null): self
    {
        $client = new self;
        $client->tracker = new FfiTracker($snapshot === '' ? null : $snapshot);

        return $client;
    }

    /**
     * Loads libpepito once per process for `withFfi()`. The path can be forced by hand.
     *
     * @throws LibraryNotFoundException
     */
    public static function lib(?string $path = null): FFI
    {
        return FfiTracker::lib($path);
    }

    /**
     * PSR-18 + PSR-17: your own HTTP client for `refresh()`.
     */
    public function setHttpClient(ClientInterface $http, RequestFactoryInterface $requests): self
    {
        $this->http = $http;
        $this->requests = $requests;

        return $this;
    }

    /**
     * PSR-20: your own source of time, handy in tests.
     */
    public function setClock(ClockInterface $clock): self
    {
        $this->clock = $clock;

        return $this;
    }

    /**
     * PSR-14: events from `watch()` and `refresh()` go here as well.
     */
    public function setEventDispatcher(EventDispatcherInterface $events): self
    {
        $this->events = $events;

        return $this;
    }

    /**
     * PSR-16: a cache instead of files in `save()` and `load()`.
     */
    public function setCache(CacheInterface $cache, string $key = 'pepito.snapshot'): self
    {
        $this->cache = $cache;
        $this->cacheKey = $key;

        return $this;
    }

    /**
     * PSR-3: a logger for diagnostic messages.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function now(): int
    {
        return $this->clock !== null ? $this->clock->now()->getTimestamp() : time();
    }

    /**
     * Dispatches an event through PSR-14, if anyone supplied a dispatcher.
     */
    private function dispatch(Update $update): void
    {
        if ($this->events !== null) {
            $this->events->dispatch($update);
        }
    }

    /**
     * Feeds a slice of the SSE stream, including one cut mid-line.
     *
     * @return list<Update>
     */
    public function feed(string $chunk): array
    {
        $updates = array_map(Update::from(...), $this->tracker->feed($chunk));
        foreach ($updates as $update) {
            $this->dispatch($update);
        }

        return $updates;
    }

    /**
     * One REST shot, so the cache does not start out empty.
     */
    public function refresh(): ?Update
    {
        $body = $this->http !== null ? $this->fetchPsr18() : $this->fetchCurl();

        if ($body === null) {
            return null;
        }
        $raw = $this->tracker->feedRest($body);
        if ($raw === null) {
            return null;
        }
        $update = Update::from($raw);
        $this->dispatch($update);

        return $update;
    }

    private function fetchPsr18(): ?string
    {
        try {
            $response = $this->http->sendRequest(
                $this->requests->createRequest('GET', self::REST_URL)->withHeader('Accept', 'application/json')
            );
        } catch (ClientExceptionInterface $error) {
            $this->log('fetching last-status failed: '.$error->getMessage());

            return null;
        }
        if ($response->getStatusCode() !== 200) {
            $this->log('last-status answered '.$response->getStatusCode());

            return null;
        }

        return (string) $response->getBody();
    }

    private function fetchCurl(): ?string
    {
        $handle = curl_init(self::REST_URL);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($handle);
        $ok = $body !== false && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200;
        if (! $ok) {
            $this->log('fetching last-status over curl failed');
        }

        return $ok ? (string) $body : null;
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->warning($message, ['component' => 'pepito']);
        }
    }

    /**
     * State, cache age, heartbeat health and the counters.
     *
     * @return array{
     *     state: string,
     *     since: int,
     *     last: array{way: string, img?: string},
     *     age: int,
     *     heartbeat_age: int,
     *     healthy: bool,
     *     count_in: int,
     *     count_out: int,
     * }
     */
    public function state(): array
    {
        return $this->tracker->status($this->now());
    }

    public function snapshot(): string
    {
        return $this->tracker->snapshot();
    }

    /** Cache between requests, because every PHP request starts from nothing. */
    public function save(?string $file = null): void
    {
        if ($this->cache !== null) {
            $this->cache->set($this->cacheKey, $this->snapshot());

            return;
        }
        if ($file === null) {
            throw new MissingStorageException('pass a file path, or a PSR-16 cache through setCache()');
        }
        file_put_contents($file, $this->snapshot(), LOCK_EX);
    }

    /** Restores from a file, through FFI with `$ffi`. For PSR-16 use `restoreFromCache()`. */
    public static function load(string $file, bool $ffi = false): self
    {
        $json = is_file($file) ? (string) file_get_contents($file) : null;

        return $ffi ? self::withFfi($json) : new self($json);
    }

    /** Pulls a snapshot out of the PSR-16 cache into an existing client. */
    public function restoreFromCache(): bool
    {
        if ($this->cache === null) {
            throw new MissingStorageException('call setCache() first');
        }
        $json = $this->cache->get($this->cacheKey);
        if (! is_string($json) || $json === '') {
            return false;
        }
        $this->tracker = $this->tracker instanceof FfiTracker ? new FfiTracker($json) : new Tracker($json);

        return true;
    }

    /**
     * Blocking live stream with reconnects. Silence longer than
     * `$idleTimeout` ends the connection. Returning false from the callback
     * breaks the loop.
     *
     * There is deliberately no PSR-18 here: that standard describes one
     * request and one response, and offers no way to ask for a stream. Against
     * an endless SSE feed a buffering implementation simply hangs. All of the
     * HTTP is left to curl: TLS, chunked decoding, splitting off the headers.
     *
     * @param  (callable(Update, self): (bool|void))|null  $onEvent  Called for every event
     */
    public function watch(?callable $onEvent = null, int $idleTimeout = 45, int $maxBackoff = 60, string $url = self::SSE_URL): void
    {
        $backoff = 1;
        $stopped = false;

        while (! $stopped) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_HTTPHEADER => ['Accept: text/event-stream'],
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_LOW_SPEED_LIMIT => 1,
                CURLOPT_LOW_SPEED_TIME => $idleTimeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use ($onEvent, &$stopped): int {
                    foreach ($this->feed($chunk) as $update) {
                        if ($onEvent !== null && $onEvent($update, $this) === false) {
                            $stopped = true;

                            // any value other than the length aborts the transfer
                            return 0;
                        }
                    }

                    return \strlen($chunk);
                },
            ]);
            curl_exec($handle);
            $connected = curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200;

            if ($stopped) {
                return;
            }
            $this->log('stream broke, retrying in '.$backoff.' s');
            $backoff = $connected ? 1 : min($backoff * 2, $maxBackoff);
            sleep($backoff);
        }
    }

    /**
     * Statistics from the archive, see self::ARCHIVE_URL for where to get it.
     * `$ffi` computes them in libpepito instead, for raw speed.
     *
     * @return array{
     *     total: int,
     *     ins: int,
     *     outs: int,
     *     first: int|null,
     *     last: int|null,
     *     outings: int,
     *     unpaired: int,
     *     avg_outing_secs: int,
     *     median_outing_secs: int,
     *     longest: array{out_at: int, in_at: int, secs: int}|null,
     *     shortest: array{out_at: int, in_at: int, secs: int}|null,
     *     by_hour_out: list<int>,
     *     by_hour_in: list<int>,
     *     by_weekday_out: list<int>,
     * }
     *
     * @throws InvalidArchiveException
     */
    public static function historyStats(string $json, bool $ffi = false): array
    {
        return $ffi ? FfiTracker::historyStats($json) : History::stats(History::parse($json));
    }

    /**
     * The archive as the same events as the live stream, see self::ARCHIVE_URL.
     * The raw array of each one also carries the tweet under `text`.
     *
     * @return list<Sighting>
     *
     * @throws InvalidArchiveException
     */
    public static function historyParse(string $json, bool $ffi = false): array
    {
        $sightings = $ffi ? FfiTracker::historyParse($json) : History::parse($json);

        return array_map(static fn (array $raw): Sighting => new Sighting(['kind' => 'sighting', ...$raw]), $sightings);
    }
}
