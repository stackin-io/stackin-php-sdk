<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The ISSQN base deduction or reduction (DPS vDedRed, or vAjusteBC in the
 * homologation layout). `percent` and `amount` are exclusive; `documents`
 * are 1 to 1000. Send at least one of the three.
 */
final class Deductions
{
    public readonly ?string $percent;

    public readonly ?string $amount;

    /** @var list<DeductionDocument>|null */
    public readonly ?array $documents;

    /**
     * @param list<DeductionDocument>|null $documents
     */
    public function __construct(
        float|string|null $percent = null,
        float|string|null $amount = null,
        ?array $documents = null,
    ) {
        $this->percent = Check::decimal($percent, 'nfse.deductions.percent', 0, true, 100, 2);
        $this->amount = Check::decimal(
            $amount,
            'nfse.deductions.amount',
            0,
            true,
            1000000000000000.0,
            2,
        );
        $this->documents = Check::objects(
            $documents,
            'nfse.deductions.documents',
            1000,
            DeductionDocument::class,
        );

        if ($this->percent !== null && $this->amount !== null) {
            Check::fail('nfse.deductions', 'takes percent or amount, not both');
        }
        if ($this->percent === null && $this->amount === null && $this->documents === null) {
            Check::fail('nfse.deductions', 'needs percent, amount or documents');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'percent' => $this->percent,
            'amount' => $this->amount,
            'documents' => Check::arrays($this->documents),
        ]);
    }
}
