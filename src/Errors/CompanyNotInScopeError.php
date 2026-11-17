<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 403 with detail.error company_not_in_scope: the named company is
 * not one this credential covers. $companies lists the ones it does.
 *
 * Never retried: the same company is refused the same way every time.
 */
final class CompanyNotInScopeError extends ApiError
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
