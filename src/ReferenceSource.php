<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The official file the reference rows were loaded from: its path in
 * stackin-io/data-source and the date it was published, null when unknown.
 */
final class ReferenceSource
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $date,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            path: (string) ($data['path'] ?? ''),
            date: isset($data['date']) ? (string) $data['date'] : null,
        );
    }
}
