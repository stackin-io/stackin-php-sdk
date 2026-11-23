<?php

declare(strict_types=1);

namespace Stackin\Br;

use Stackin\Errors\InvoiceError;

/**
 * The transport group of an NF-e. `freightMode` is modFrete: "0" seller
 * contracts the freight, "1" buyer, "2" a third party, "3" seller's own
 * transport, "4" buyer's own, "9" no transport.
 *
 * `volumes` left null lets the API derive one from the line weights; an
 * empty list is a different request and writes none.
 */
final class Transport
{
    private const FREIGHT_MODES = ['0', '1', '2', '3', '4', '9'];

    public function __construct(
        public readonly string $freightMode,
        /** @var TransportVolume[]|null */
        public readonly ?array $volumes = null,
    ) {
        if (!in_array($freightMode, self::FREIGHT_MODES, true)) {
            throw new InvoiceError(
                "freightMode must be one of 0, 1, 2, 3, 4 or 9, got '{$freightMode}'",
            );
        }
        if ($volumes !== null && count($volumes) > 5000) {
            throw new InvoiceError('transport takes at most 5000 volumes');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['freight_mode' => $this->freightMode];
        if ($this->volumes !== null) {
            $data['volumes'] = array_map(
                static fn (TransportVolume $volume): array => $volume->toArray(),
                array_values($this->volumes),
            );
        }

        return $data;
    }
}
