<?php

declare(strict_types=1);

namespace Stackin;

/**
 * One row of NFS-e Anexo VIII for a service code: an NBS with the cIndOp and
 * cClassTrib the annex pairs with it. Every field but `nbs` is null when the
 * annex or the official cClassTrib table lists none.
 */
final class NbsSuggestion
{
    /**
     * @param array<string, string>|null $operationVariants the cIndOp for the same
     *        operation when it is not onerous or the acquirer is abroad:
     *        onerous_domestic, onerous_abroad, free_domestic, free_abroad
     */
    public function __construct(
        public readonly string $nbs,
        public readonly ?string $description,
        public readonly ?string $operationIndicator,
        public readonly ?string $ibsCbsClassification,
        public readonly ?string $classificationName,
        public readonly ?string $classificationCst = null,
        public readonly ?float $reductionIbs = null,
        public readonly ?float $reductionCbs = null,
        public readonly ?string $legalReference = null,
        public readonly ?string $incidence = null,
        public readonly ?array $operationVariants = null,
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
        $number = static fn (string $key): ?float => isset($data[$key]) && is_numeric($data[$key])
            ? (float) $data[$key]
            : null;

        $variants = null;
        if (isset($data['operation_variants']) && is_array($data['operation_variants'])) {
            $variants = [];
            foreach ($data['operation_variants'] as $name => $code) {
                $variants[(string) $name] = (string) $code;
            }
        }

        return new self(
            nbs: (string) ($data['nbs'] ?? ''),
            description: $text('description'),
            operationIndicator: $text('operation_indicator'),
            ibsCbsClassification: $text('ibs_cbs_classification'),
            classificationName: $text('classification_name'),
            classificationCst: $text('classification_cst'),
            reductionIbs: $number('reduction_ibs'),
            reductionCbs: $number('reduction_cbs'),
            legalReference: $text('legal_reference'),
            incidence: $text('incidence'),
            operationVariants: $variants,
        );
    }
}
