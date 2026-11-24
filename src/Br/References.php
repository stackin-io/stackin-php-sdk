<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The DPS infoCompl references: `orderNumber` (xPed), `orderItems` (gItemPed,
 * up to 99), `referenceDocument` (docRef) and `technicalDocument` (idDocTec).
 * The free text stays in the line's `observations`.
 */
final class References
{
    public readonly ?string $orderNumber;

    /** @var list<string>|null */
    public readonly ?array $orderItems;

    public readonly ?string $referenceDocument;

    public readonly ?string $technicalDocument;

    /**
     * @param list<string>|null $orderItems
     */
    public function __construct(
        ?string $orderNumber = null,
        ?array $orderItems = null,
        ?string $referenceDocument = null,
        ?string $technicalDocument = null,
    ) {
        $this->orderNumber = Check::text($orderNumber, 'nfse.references.order_number', 1, 60);
        $this->orderItems = Check::strings($orderItems, 'nfse.references.order_items', 99, 1, 60);
        $this->referenceDocument = Check::text(
            $referenceDocument,
            'nfse.references.reference_document',
            1,
            255,
        );
        $this->technicalDocument = Check::text(
            $technicalDocument,
            'nfse.references.technical_document',
            1,
            40,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'order_number' => $this->orderNumber,
            'order_items' => $this->orderItems,
            'reference_document' => $this->referenceDocument,
            'technical_document' => $this->technicalDocument,
        ]);
    }
}
