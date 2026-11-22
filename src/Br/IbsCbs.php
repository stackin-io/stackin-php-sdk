<?php

declare(strict_types=1);

namespace Stackin\Br;

use Stackin\Errors\InvoiceError;

/**
 * The item's IBS/CBS group (Reforma Tributária), sent under `br.ibs_cbs`.
 *
 * `cst` is the 3-digit CST of IBS/CBS, `classification` the 6-digit
 * cClassTrib and the rates are percentages: `rateState` (pIBSUF),
 * `rateCity` (pIBSMun) and `rateFederal` (pCBS), left off the request when
 * null. `base` (vBC) defaults to the item amount when left out.
 * `operationIndicator` is the 6-digit cIndOp.
 *
 * An NF-e sends the three rates; an NFS-e sends only `cst`,
 * `classification` and `operationIndicator`. Which of them a document type
 * requires is decided by the API.
 *
 * `reductionIbs` is the pRedAliq of the IBS (state and municipality) and
 * `reductionCbs` the pRedAliq of the CBS, both percentages from 0 to 100:
 * the cClassTrib's pRedIBS and pRedCBS in the official classification
 * table. The API asks for them on CST 011, 200 and 515 and refuses them on
 * any other. Every optional field is left off the request when null.
 */
final class IbsCbs
{
    public function __construct(
        public readonly string $cst,
        public readonly string $classification,
        public readonly ?float $rateState = null,
        public readonly ?float $rateCity = null,
        public readonly ?float $rateFederal = null,
        public readonly ?float $base = null,
        public readonly ?float $reductionIbs = null,
        public readonly ?float $reductionCbs = null,
        public readonly ?string $operationIndicator = null,
    ) {
        if ($operationIndicator !== null && preg_match('/^\d{6}$/', $operationIndicator) !== 1) {
            throw new InvoiceError('operationIndicator must be 6 digits');
        }
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
                'cst' => $this->cst,
                'classification' => $this->classification,
                'base' => $this->base,
                'rate_state' => $this->rateState,
                'rate_city' => $this->rateCity,
                'rate_federal' => $this->rateFederal,
                'reduction_ibs' => $this->reductionIbs,
                'reduction_cbs' => $this->reductionCbs,
                'operation_indicator' => $this->operationIndicator,
            ],
            static fn (string|float|null $value): bool => $value !== null,
        );
    }
}
