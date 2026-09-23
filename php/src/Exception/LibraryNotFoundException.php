<?php

declare(strict_types=1);

namespace Pepito\Exception;

use RuntimeException;

/**
 * The native library or its header could not be found.
 */
final class LibraryNotFoundException extends RuntimeException implements LibraryNotFoundExceptionInterface {}
