<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The result of POST /ibs-cbs/calculate. A simulation: nothing is
 * issued or stored. Fields the API adds later are ignored rather than
 * refused.
 */
final class IbsCbsCalculation
{
    /**
     * @param list<IbsCbsCalculatedItem> $items
     */
    public function __construct(
        public readonly bool $simulated,
        public readonly string $notice,
        public readonly string $documentType,
        public readonly string $operationDate,
        public readonly array $items,
        public readonly IbsCbsAmounts $total,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];
        $rows = $data['items'] ?? [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $items[] = IbsCbsCalculatedItem::fromArray($row);
            }
        }
        $total = $data['total'] ?? [];

        return new self(
            simulated: (bool) ($data['simulated'] ?? true),
            notice: (string) ($data['notice'] ?? ''),
            documentType: (string) ($data['document_type'] ?? ''),
            operationDate: (string) ($data['operation_date'] ?? ''),
            items: $items,
            total: IbsCbsAmounts::fromArray(is_array($total) ? $total : []),
        );
    }
}
