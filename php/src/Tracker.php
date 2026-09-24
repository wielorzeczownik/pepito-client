<?php

declare(strict_types=1);

namespace Pepito;

/**
 * State, cache, dedup and heartbeat in plain PHP, the default backend of Client.
 *
 * @internal Use Client, which picks the backend.
 */
final class Tracker
{
    /** With no heartbeat for this many seconds the connection counts as dead (the server beats roughly every 10 s). */
    public const HEARTBEAT_TIMEOUT = 30;

    private const U32_MAX = 4294967295;

    /** @var array{way: string, time: int, img?: string, text?: string}|null */
    private ?array $last = null;

    private ?int $lastHeartbeat = null;

    private int $countIn = 0;

    private int $countOut = 0;

    /** Unfinished line carried between stream chunks. */
    private string $buffer = '';

    /** A snapshot that does not parse means a fresh tracker. */
    public function __construct(?string $snapshot = null)
    {
        $raw = $snapshot === null ? null : json_decode($snapshot, true);
        if (! \is_array($raw) || ! self::isCount($raw['count_in'] ?? null) || ! self::isCount($raw['count_out'] ?? null)) {
            return;
        }
        $last = isset($raw['last']) ? self::restoreSighting($raw['last']) : null;
        $beat = $raw['last_heartbeat'] ?? null;
        if ((isset($raw['last']) && $last === null) || ($beat !== null && ! \is_int($beat))) {
            return;
        }
        $this->last = $last;
        $this->lastHeartbeat = $beat;
        $this->countIn = $raw['count_in'];
        $this->countOut = $raw['count_out'];
    }

    /**
     * Feeds any slice of the SSE stream, including one cut mid-line.
     *
     * @return list<array<string, mixed>>
     */
    public function feed(string $chunk): array
    {
        $this->buffer .= $chunk;
        $updates = [];
        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $line = trim(substr($this->buffer, 0, $newline));
            $this->buffer = substr($this->buffer, $newline + 1);
            if (str_starts_with($line, 'data:')) {
                $update = $this->feedRest(ltrim(substr($line, 5)));
            } elseif (str_starts_with($line, '{')) {
                $update = $this->feedRest($line);
            } else {
                continue;
            }
            if ($update !== null) {
                $updates[] = $update;
            }
        }

        return $updates;
    }

    /**
     * Feeds the REST last-status response, which has the same shape as an SSE frame.
     *
     * @return array<string, mixed>|null
     */
    public function feedRest(string $json): ?array
    {
        $raw = json_decode($json, true);
        if (! \is_array($raw) || ! \is_int($raw['time'] ?? null)) {
            return null;
        }
        $time = $raw['time'];
        $event = $raw['event'] ?? null;
        if ($event === 'heartbeat') {
            $this->lastHeartbeat = max($time, $this->lastHeartbeat ?? PHP_INT_MIN);

            return ['kind' => 'heartbeat', 'time' => $time];
        }
        $way = $raw['type'] ?? null;
        $img = $raw['img'] ?? null;
        if ($event !== 'pepito' || ! self::isWay($way) || ($img !== null && ! \is_string($img))) {
            return null;
        }

        $previous = $this->last;
        if ($previous !== null && $previous['time'] === $time && $previous['way'] === $way) {
            return ['kind' => 'duplicate', 'time' => $time];
        }
        $changed = $previous === null || $previous['way'] !== $way;
        if ($way === 'in') {
            $this->countIn++;
        } else {
            $this->countOut++;
        }
        $sighting = self::sighting($way, $time, $img);
        // An event older than the cache (a late reconnect) still counts, but it
        // must not roll the state backwards.
        if ($previous === null || $time >= $previous['time']) {
            $this->last = $sighting;
        }

        return ['kind' => 'sighting', ...$sighting, 'changed' => $changed];
    }

    /**
     * Where the cat is, how stale the cache is, whether the heartbeat is alive.
     *
     * @return array<string, mixed>
     */
    public function status(int $now): array
    {
        $last = $this->last;
        $heartbeatAge = $this->lastHeartbeat === null ? null : $now - $this->lastHeartbeat;
        $state = 'unknown';
        if ($last !== null) {
            $state = $last['way'] === 'in' ? 'home' : 'away';
        }

        return [
            'state' => $state,
            'since' => $last['time'] ?? null,
            'last' => $last,
            'age' => $last === null ? null : $now - $last['time'],
            'heartbeat_age' => $heartbeatAge,
            'healthy' => $heartbeatAge !== null && $heartbeatAge <= self::HEARTBEAT_TIMEOUT,
            'count_in' => $this->countIn,
            'count_out' => $this->countOut,
        ];
    }

    /** The same JSON as the FFI backend writes, so either one restores it. */
    public function snapshot(): string
    {
        return json_encode([
            'last' => $this->last,
            'last_heartbeat' => $this->lastHeartbeat,
            'count_in' => $this->countIn,
            'count_out' => $this->countOut,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * No image or text means no key, not a null one.
     *
     * @return array{way: string, time: int, img?: string, text?: string}
     */
    public static function sighting(string $way, int $time, ?string $img = null, ?string $text = null): array
    {
        return array_filter(
            ['way' => $way, 'time' => $time, 'img' => $img, 'text' => $text],
            static fn (mixed $value): bool => $value !== null,
        );
    }

    public static function isWay(mixed $way): bool
    {
        return $way === 'in' || $way === 'out';
    }

    private static function restoreSighting(mixed $raw): ?array
    {
        if (! \is_array($raw) || ! self::isWay($raw['way'] ?? null) || ! \is_int($raw['time'] ?? null)) {
            return null;
        }
        $img = $raw['img'] ?? null;
        $text = $raw['text'] ?? null;
        if (($img !== null && ! \is_string($img)) || ($text !== null && ! \is_string($text))) {
            return null;
        }

        return self::sighting($raw['way'], $raw['time'], $img, $text);
    }

    private static function isCount(mixed $value): bool
    {
        return \is_int($value) && $value >= 0 && $value <= self::U32_MAX;
    }
}
