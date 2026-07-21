<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Reports database infrastructure failures without exposing connection details.
 */
final class DatabaseException extends RuntimeException
{
}
