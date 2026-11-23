<?php

declare(strict_types=1);

namespace Stackin\Br;

use Stackin\Errors\InvoiceError;

/**
 * The marketplace or platform an NF-e sale went through: `taxId` is its
 * CNPJ and `identifier` the seller's user name or profile id there
 * (2 to 60 characters).
 */
final class Intermediary
{
    public function __construct(
        public readonly string $taxId,
        public readonly string $identifier,
    ) {
        $length = mb_strlen(trim($identifier));
        if ($length < 2 || $length > 60) {
            throw new InvoiceError('intermediary identifier must be 2 to 60 characters');
        }
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['tax_id' => $this->taxId, 'identifier' => $this->identifier];
    }
}
