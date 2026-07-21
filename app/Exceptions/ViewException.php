<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Reports a view-rendering failure without exposing template details.
 */
final class ViewException extends RuntimeException
{
}
