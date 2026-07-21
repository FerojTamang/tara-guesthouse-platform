<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Reports invalid or incomplete application configuration.
 */
final class ConfigurationException extends RuntimeException
{
}
