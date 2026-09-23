<?php

declare(strict_types=1);

namespace Pepito;

use FFI;
use FFI\CData;
use Pepito\Event\Update;
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
 * The Pepito API client: the same Rust core, loaded from libpepito through FFI.
 */
final class Client implements LoggerAwareInterface
{
    public const SSE_URL = 'https://api.thecatdoor.com/sse/v1/events';

    public const REST_URL = 'https://api.thecatdoor.com/rest/v1/last-status';

    public const ARCHIVE_URL = 'https://raw.githubusercontent.com/Clement87/Pepito-data/main/tweets.json';

    private static ?FFI $ffi = null;

    /** Pointer to the Rust tracker. */
    private ?CData $tracker;

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
        $ffi = self::lib();
        $this->tracker = $snapshot === null || $snapshot === ''
            ? $ffi->pepito_new()
            : $ffi->pepito_restore($snapshot);
    }

    public function __destruct()
    {
        if ($this->tracker !== null) {
            self::$ffi->pepito_drop($this->tracker);
            $this->tracker = null;
        }
    }

    /**
     * Loads libpepito once per process. The path can be forced by hand.
     */
    public static function lib(?string $path = null): FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }
        $lib = \dirname(__DIR__).'/lib';
        $header = $lib.'/pepito.h';
        if (! is_file($header)) {
            throw new LibraryNotFoundException('php/lib/pepito.h is missing, generate it with: make header');
        }
        $cdef = (string) file_get_contents($header);
        $candidates = $path !== null ? [$path] : [];
        foreach (['dylib', 'so', 'dll'] as $ext) {
            $candidates[] = $lib."/libpepito.$ext";
            $candidates[] = $lib."/pepito.$ext";
            $candidates[] = $lib."/../../target/release/libpepito.$ext";
            $candidates[] = $lib."/../../target/release/pepito.$ext";
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return self::$ffi = FFI::cdef($cdef, $candidate);
            }
        }

        throw new LibraryNotFoundException('libpepito not found, build it with: make dylib');
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

    /**
     * Takes a string from Rust and frees that memory right away.
     */
    private static function take(CData $pointer): string
    {
        $text = FFI::string($pointer);
        self::$ffi->pepito_free($pointer);

        return $text;
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
        $raw = json_decode(self::take(self::$ffi->pepito_feed($this->tracker, $chunk)), true);
        $updates = array_map(Update::from(...), $raw);
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
        $raw = json_decode(self::take(self::$ffi->pepito_feed_rest($this->tracker, $body)), true);
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
        return json_decode(self::take(self::$ffi->pepito_state($this->tracker, $this->now())), true);
    }

    public function snapshot(): string
    {
        return self::take(self::$ffi->pepito_snapshot($this->tracker));
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

    /** Restores from a file. For PSR-16 use `restoreFromCache()`. */
    public static function load(string $file): self
    {
        $json = is_file($file) ? (string) file_get_contents($file) : '';

        return new self($json !== '' ? $json : null);
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
        self::$ffi->pepito_drop($this->tracker);
        $this->tracker = self::$ffi->pepito_restore($json);

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
     */
    public static function historyStats(string $json): array
    {
        self::lib();

        return json_decode(self::take(self::$ffi->pepito_history_stats($json)), true);
    }

    /**
     * The archive as the same events as the live stream, see self::ARCHIVE_URL.
     *
     * @return list<Update>
     */
    public static function historyParse(string $json): array
    {
        self::lib();

        $raw = json_decode(self::take(self::$ffi->pepito_history_parse($json)), true);

        return array_map(Update::from(...), $raw);
    }
}
