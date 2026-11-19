<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 422 with detail.code CLIENT_INACTIVE: the saved client named by
 * client_id was deactivated. Clients::reactivate($clientId) brings it
 * back; issuing for it is refused until then.
 */
final class ClientInactiveError extends ApiError
{
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly string $clientId,
    ) {
        parent::__construct($statusCode, $detail);
    }
}
