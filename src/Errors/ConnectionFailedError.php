<?php

declare(strict_types=1);

namespace Stackin\Errors;

use Exception;
use Throwable;

/**
 * The API never responded — network, DNS, or timeout failure. The
 * underlying transport error is wrapped, never swallowed.
 */
final class ConnectionFailedError extends Exception
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
