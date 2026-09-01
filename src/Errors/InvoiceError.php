<?php

declare(strict_types=1);

namespace Stackin\Errors;

use Exception;

/**
 * A request would obviously fail server-side and the SDK caught it
 * locally before making the network call — e.g. empty items, or a
 * missing ncm/cfop on an NFE item.
 */
final class InvoiceError extends Exception
{
}
