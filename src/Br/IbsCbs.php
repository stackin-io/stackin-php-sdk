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
 *
 * The rest are NFS-e only: `personalUse` (indFinal), `government` (tpOper and
 * tpEnteGov), `referencedNfse` (gRefNFSe, 1 to 99 access keys), `recipient`
 * (dest), `realEstate` (imovel), `movableGoods` (bensMoveis, 1 to 1000),
 * `regularTaxation` (gTribRegular), `deferral` (gDif) and `presumedCredit`
 * (cCredPres, two digits).
 */
final class IbsCbs
{
    /** @var list<string>|null */
    private readonly ?array $checkedKeys;

    /** @var list<MovableGood>|null */
    private readonly ?array $checkedGoods;

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
        public readonly ?bool $personalUse = null,
        public readonly ?IbsCbsGovernment $government = null,
        /** @var list<string>|null */
        public readonly ?array $referencedNfse = null,
        public readonly ?IbsCbsRecipient $recipient = null,
        public readonly ?RealEstate $realEstate = null,
        /** @var list<MovableGood>|null */
        public readonly ?array $movableGoods = null,
        public readonly ?IbsCbsRegularTaxation $regularTaxation = null,
        public readonly ?IbsCbsDeferral $deferral = null,
        public readonly ?string $presumedCredit = null,
    ) {
        if ($operationIndicator !== null && preg_match('/^\d{6}$/', $operationIndicator) !== 1) {
            throw new InvoiceError('operationIndicator must be 6 digits');
        }
        Check::pattern(
            $presumedCredit,
            'ibs_cbs.presumed_credit',
            '/^\d{2}$/',
            '2 digits',
        );
        $this->checkedKeys = $referencedNfse === null || $referencedNfse === []
            ? null
            : self::keys($referencedNfse);
        $this->checkedGoods = Check::objects(
            $movableGoods,
            'ibs_cbs.movable_goods',
            1000,
            MovableGood::class,
        );
    }

    /**
     * @param array<array-key, mixed> $keys
     * @return list<string>
     */
    private static function keys(array $keys): array
    {
        if (count($keys) > 99) {
            Check::fail('ibs_cbs.referenced_nfse', 'takes 1 to 99 access keys');
        }
        $list = [];
        foreach (array_values($keys) as $index => $key) {
            $list[] = Check::requiredPattern(
                is_string($key) ? $key : null,
                "ibs_cbs.referenced_nfse[{$index}]",
                '/^[0-9]{6}[0-9A-Z]{14}[0-9]{30}$/',
                'the 50-character access key of an NFS-e',
            );
        }

        return $list;
    }

    /**
     * Returns the group as a plain array, in the API's snake_case.
     *
     * @return array<string, mixed>
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
                'personal_use' => $this->personalUse,
                'government' => $this->government?->toArray(),
                'referenced_nfse' => $this->checkedKeys,
                'recipient' => $this->recipient?->toArray(),
                'real_estate' => $this->realEstate?->toArray(),
                'movable_goods' => Check::arrays($this->checkedGoods),
                'regular_taxation' => $this->regularTaxation?->toArray(),
                'deferral' => $this->deferral?->toArray(),
                'presumed_credit' => $this->presumedCredit,
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
