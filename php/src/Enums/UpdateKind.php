<?php

declare(strict_types=1);

namespace Pepito\Enums;

/**
 * The `kind` field on every event. Mirrors the Rust `Update`.
 */
enum UpdateKind: string
{
    case Heartbeat = 'heartbeat';

    case Sighting = 'sighting';

    case Duplicate = 'duplicate';
}
