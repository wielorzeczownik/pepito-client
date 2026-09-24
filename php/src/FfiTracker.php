<?php

declare(strict_types=1);

namespace Pepito;

use FFI;
use FFI\CData;
use Pepito\Exception\InvalidArchiveException;
use Pepito\Exception\LibraryNotFoundException;

/**
 * The same surface as Tracker and History, backed by libpepito through FFI.
 * Opt-in through Client::withFfi(); nothing falls back to it or from it.
 *
 * @internal Use Client, which picks the backend.
 */
final class FfiTracker
{
    private static ?FFI $ffi = null;

    /** Pointer to the tracker inside libpepito. */
    private CData $tracker;

    public function __construct(?string $snapshot = null)
    {
        $ffi = self::lib();
        $this->tracker = $snapshot === null ? $ffi->pepito_new() : $ffi->pepito_restore($snapshot);
    }

    public function __destruct()
    {
        self::$ffi->pepito_drop($this->tracker);
    }

    /**
     * Loads libpepito once per process. The path can be forced by hand.
     */
    public static function lib(?string $path = null): FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }
        if (! \extension_loaded('ffi')) {
            throw new LibraryNotFoundException('the ffi extension is not loaded, which the FFI backend needs');
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
                try {
                    return self::$ffi = FFI::cdef($cdef, $candidate);
                } catch (FFI\Exception $error) {
                    throw new LibraryNotFoundException('libpepito could not be loaded (is ffi.enable=1?): '.$error->getMessage(), previous: $error);
                }
            }
        }

        throw new LibraryNotFoundException('libpepito not found, build it with: make dylib');
    }

    /**
     * Takes a JSON string from libpepito, frees that memory right away and decodes it.
     */
    private static function take(CData $pointer): mixed
    {
        $text = FFI::string($pointer);
        self::$ffi->pepito_free($pointer);

        return json_decode($text, true);
    }

    /** @return list<array<string, mixed>> */
    public function feed(string $chunk): array
    {
        return self::take(self::$ffi->pepito_feed($this->tracker, $chunk));
    }

    /** @return array<string, mixed>|null */
    public function feedRest(string $json): ?array
    {
        return self::take(self::$ffi->pepito_feed_rest($this->tracker, $json));
    }

    /** @return array<string, mixed> */
    public function status(int $now): array
    {
        return self::take(self::$ffi->pepito_state($this->tracker, $now));
    }

    public function snapshot(): string
    {
        $pointer = self::$ffi->pepito_snapshot($this->tracker);
        $text = FFI::string($pointer);
        self::$ffi->pepito_free($pointer);

        return $text;
    }

    /** @return array<string, mixed> */
    public static function historyStats(string $json): array
    {
        return self::archive(self::lib()->pepito_history_stats($json));
    }

    /** @return list<array<string, mixed>> */
    public static function historyParse(string $json): array
    {
        return self::archive(self::lib()->pepito_history_parse($json));
    }

    /** libpepito reports a broken archive as `{"error": "..."}`. */
    private static function archive(CData $pointer): array
    {
        $result = self::take($pointer);
        if (isset($result['error'])) {
            throw new InvalidArchiveException($result['error']);
        }

        return $result;
    }
}
