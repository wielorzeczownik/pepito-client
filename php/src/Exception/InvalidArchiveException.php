<?php

declare(strict_types=1);

namespace Pepito\Exception;

use UnexpectedValueException;

/**
 * The archive handed to historyStats() or historyParse() is not a JSON array of tweets.
 */
final class InvalidArchiveException extends UnexpectedValueException implements InvalidArchiveExceptionInterface {}
