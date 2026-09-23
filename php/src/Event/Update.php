<?php

declare(strict_types=1);

namespace Pepito\Event;

use Pepito\Enums\UpdateKind;

/**
 * One event from the tracker, dispatched through PSR-14.
 */
class Update
{
    public readonly UpdateKind $kind;

    /** Unix timestamp in seconds, UTC. */
    public readonly int $time;

    /** The raw event, exactly as the core returned it. */
    public readonly array $raw;

    public function __construct(array $raw)
    {
        $this->kind = UpdateKind::from((string) ($raw['kind'] ?? ''));
        $this->time = (int) ($raw['time'] ?? 0);
        $this->raw = $raw;
    }

    /**
     * Builds the event of the right type for an array from the core.
     */
    public static function from(array $raw): self
    {
        return match (UpdateKind::from((string) ($raw['kind'] ?? ''))) {
            UpdateKind::Sighting => new Sighting($raw),
            UpdateKind::Heartbeat => new Heartbeat($raw),
            UpdateKind::Duplicate => new self($raw),
        };
    }
}
