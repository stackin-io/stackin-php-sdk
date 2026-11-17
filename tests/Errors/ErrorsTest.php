<?php

declare(strict_types=1);

namespace Stackin\Tests\Errors;

use Exception;
use PHPUnit\Framework\TestCase;
use Stackin\Errors\ApiError;
use Stackin\Errors\CompanyNotInScopeError;
use Stackin\Errors\CompanyRequiredError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InsufficientPermissionError;
use Stackin\Errors\InvoiceError;

final class ErrorsTest extends TestCase
{
    public function testApiErrorCarriesStatusCodeAndDetail(): void
    {
        $error = new ApiError(404, 'not found');

        $this->assertSame(404, $error->statusCode);
        $this->assertSame('not found', $error->detail);
        $this->assertSame('[404] not found', $error->getMessage());
    }

    public function testConnectionFailedErrorWrapsPrevious(): void
    {
        $previous = new Exception('network down');
        $error = new ConnectionFailedError('could not connect', $previous);

        $this->assertSame('could not connect', $error->getMessage());
        $this->assertSame($previous, $error->getPrevious());
    }

    public function testConnectionFailedErrorWithoutPrevious(): void
    {
        $error = new ConnectionFailedError('could not connect');

        $this->assertNull($error->getPrevious());
    }

    public function testInvoiceErrorIsAnException(): void
    {
        $error = new InvoiceError('boom');

        $this->assertSame('boom', $error->getMessage());
        $this->assertInstanceOf(Exception::class, $error);
    }

    public function testCompanyRequiredErrorCarriesTheCompanies(): void
    {
        $companies = [['company_id' => 'c1', 'name' => 'ACME']];
        $error = new CompanyRequiredError(422, '{"error":"company_required"}', $companies);

        $this->assertSame(422, $error->statusCode);
        $this->assertSame($companies, $error->companies);
        $this->assertSame('[422] {"error":"company_required"}', $error->getMessage());
        $this->assertInstanceOf(ApiError::class, $error);
    }

    public function testCompanyNotInScopeErrorCarriesTheCompanies(): void
    {
        $companies = [['company_id' => 'c1', 'name' => 'ACME']];
        $error = new CompanyNotInScopeError(403, 'not in scope', $companies);

        $this->assertSame(403, $error->statusCode);
        $this->assertSame($companies, $error->companies);
        $this->assertInstanceOf(ApiError::class, $error);
    }

    public function testInsufficientPermissionErrorCarriesTheDescription(): void
    {
        $error = new InsufficientPermissionError(403, 'denied', 'needs a, has b, company=c');

        $this->assertSame(403, $error->statusCode);
        $this->assertSame('needs a, has b, company=c', $error->description);
        $this->assertInstanceOf(ApiError::class, $error);
    }
}
