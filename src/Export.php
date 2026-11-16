<?php

declare(strict_types=1);

namespace Stackin;

/**
 * Where the goods of an NF-e to a buyer abroad leave Brazil — the layout's
 * `exporta` group.
 *
 * `exitState` is the Brazilian state of shipment or border crossing (never
 * EX), `place` the place of shipment or crossing and `dispatchPlace` the
 * customs facility of dispatch, which may be left out.
 */
final class Export
{
    public function __construct(
        public readonly string $exitState,
        public readonly string $place,
        public readonly ?string $dispatchPlace = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = [
            'exit_state' => $this->exitState,
            'place' => $this->place,
        ];
        if ($this->dispatchPlace !== null) {
            $data['dispatch_place'] = $this->dispatchPlace;
        }

        return $data;
    }
}
