<?php

declare(strict_types=1);

namespace Pepito\Event;

use Pepito\Enums\Way;

/**
 * The cat went through the flap.
 */
final class Sighting extends Update
{
    public readonly Way $way;

    /** Whether this is a real state change, not a repeat of the same direction. */
    public readonly bool $changed;

    public readonly ?string $img;

    public function __construct(array $raw)
    {
        parent::__construct($raw);
        $this->way = Way::from((string) ($raw['way'] ?? ''));
        $this->changed = (bool) ($raw['changed'] ?? false);
        $this->img = $raw['img'] ?? null;
    }
}
