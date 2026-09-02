<?php

declare(strict_types=1);

namespace Stackin\Tests;

use PHPUnit\Framework\TestCase;
use Stackin\DocumentType;

final class DocumentTypeTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('nfe', DocumentType::NFE->value);
        $this->assertSame('nfse', DocumentType::NFSE->value);
    }
}
