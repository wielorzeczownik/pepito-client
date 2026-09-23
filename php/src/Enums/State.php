<?php

declare(strict_types=1);

namespace Pepito\Enums;

/**
 * Where the cat is. Mirrors the Rust `State`.
 */
enum State: string
{
    case Home = 'home';

    case Away = 'away';

    case Unknown = 'unknown';
}
