<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 422 with detail.error company_required: the credential covers
 * several companies and the request named none. Pick one of
 * $companies and pass it as company, on the client or on the call.
 *
 * Never retried: the same request fails the same way until a company
 * is named.
 */
final class CompanyRequiredError extends ApiError
{
    /**
     * @param list<array<string, mixed>> $companies
     */
    public function __construct(
        int $statusCode,
        string $detail,
        public readonly array $companies,
    ) {
        parent::__construct($statusCode, $detail);
    }
}
