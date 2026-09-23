<?php

declare(strict_types=1);

namespace Pepito\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

/** PSR-14: a dispatcher that only remembers what it was given. */
final class CollectingDispatcher implements EventDispatcherInterface
{
    public array $seen = [];

    public function dispatch(object $event): object
    {
        $this->seen[] = $event;

        return $event;
    }
}
