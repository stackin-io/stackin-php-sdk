<?php

declare(strict_types=1);

namespace Stackin\Tests\Errors;

use Exception;
use PHPUnit\Framework\TestCase;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
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
}
