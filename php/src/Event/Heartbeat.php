<?php

declare(strict_types=1);

namespace Pepito\Event;

/**
 * A heartbeat from the stream. A long gap means the connection hung.
 */
final class Heartbeat extends Update {}
