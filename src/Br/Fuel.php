<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The NF-e fuel group (`comb`), for a good the ANP classifies as fuel — a
 * fuel CFOP needs it, or the SEFAZ answers 660.
 *
 * `anpCode` is the 9-digit code from the ANP SIMP table, `anpDescription`
 * its description and `consumerState` the state (UF) where it is consumed.
 * The rest are optional: the three GLP percentages and its starting value,
 * the CODIF, the quantity at 20 °C and the biodiesel percentage.
 */
final class Fuel
{
    public function __construct(
        public readonly string $anpCode,
        public readonly string $anpDescription,
        public readonly string $consumerState,
        public readonly ?float $glpPercent = null,
        public readonly ?float $naturalGasNationalPercent = null,
        public readonly ?float $naturalGasImportedPercent = null,
        public readonly ?float $startingValue = null,
        public readonly ?string $codif = null,
        public readonly ?float $temperatureQuantity = null,
        public readonly ?float $biodieselPercent = null,
    ) {
    }

    /**
     * Returns the group as a plain array, in the API's snake_case.
     *
     * @return array<string, string|float>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'anp_code' => $this->anpCode,
                'anp_description' => $this->anpDescription,
                'consumer_state' => $this->consumerState,
                'glp_percent' => $this->glpPercent,
                'natural_gas_national_percent' => $this->naturalGasNationalPercent,
                'natural_gas_imported_percent' => $this->naturalGasImportedPercent,
                'starting_value' => $this->startingValue,
                'codif' => $this->codif,
                'temperature_quantity' => $this->temperatureQuantity,
                'biodiesel_percent' => $this->biodieselPercent,
            ],
            static fn (string|float|null $value): bool => $value !== null,
        );
    }
}
