<?php

declare(strict_types=1);

namespace Stackin;

/**
 * What the NBS route answers: `serviceCode` is the six-digit cTribNac and
 * `suggestions` every NBS, cIndOp and cClassTrib combination Anexo VIII
 * lists for its subitem, empty when it lists none.
 */
final class NbsSuggestions
{
    /**
     * @param list<NbsSuggestion> $suggestions
     */
    public function __construct(
        public readonly string $serviceCode,
        public readonly array $suggestions,
        public readonly ReferenceSource $source,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rows = is_array($data['suggestions'] ?? null) ? $data['suggestions'] : [];
        $source = is_array($data['source'] ?? null) ? $data['source'] : [];

        return new self(
            serviceCode: (string) ($data['service_code'] ?? ''),
            suggestions: array_values(array_map(
                static fn (array $row): NbsSuggestion => NbsSuggestion::fromArray($row),
                array_values(array_filter($rows, 'is_array')),
            )),
            source: ReferenceSource::fromArray($source),
        );
    }
}
