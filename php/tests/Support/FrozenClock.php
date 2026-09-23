<?php

declare(strict_types=1);

namespace Pepito\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** PSR-20: a clock that always reports the same instant. */
final class FrozenClock implements ClockInterface
{
    public function __construct(private readonly int $at) {}

    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable)->setTimestamp($this->at);
    }
}
