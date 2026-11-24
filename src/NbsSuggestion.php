<?php

declare(strict_types=1);

namespace Stackin;

/**
 * One row of NFS-e Anexo VIII for a service code: an NBS with the cIndOp and
 * cClassTrib the annex pairs with it. Every field but `nbs` is null when the
 * annex lists none.
 */
final class NbsSuggestion
{
    public function __construct(
        public readonly string $nbs,
        public readonly ?string $description,
        public readonly ?string $operationIndicator,
        public readonly ?string $ibsCbsClassification,
        public readonly ?string $classificationName,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): ?string => isset($data[$key])
            ? (string) $data[$key]
            : null;

        return new self(
            nbs: (string) ($data['nbs'] ?? ''),
            description: $text('description'),
            operationIndicator: $text('operation_indicator'),
            ibsCbsClassification: $text('ibs_cbs_classification'),
            classificationName: $text('classification_name'),
        );
    }
}
