<?php

declare(strict_types=1);

namespace Pepito\Enums;

/**
 * Direction the cat took through the flap. Mirrors the Rust `Way`.
 */
enum Way: string
{
    case In = 'in';

    case Out = 'out';
}
