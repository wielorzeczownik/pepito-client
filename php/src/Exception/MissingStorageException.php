<?php

declare(strict_types=1);

namespace Pepito\Exception;

use LogicException;

/**
 * The snapshot has nowhere to go, or nowhere to come from.
 */
final class MissingStorageException extends LogicException implements MissingStorageExceptionInterface {}
