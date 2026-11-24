<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The DPS subst group: the access key of the authorized NFS-e this one
 * replaces, the `reasonCode` (01 to 05 or 99) and, with 99, a `reason` of 15
 * to 255 Latin-1 characters.
 */
final class Substitution
{
    public readonly string $accessKey;

    public readonly string $reasonCode;

    public readonly ?string $reason;

    public function __construct(
        string $accessKey,
        string $reasonCode,
        ?string $reason = null,
    ) {
        $this->accessKey = Check::requiredPattern(
            $accessKey,
            'nfse.substitution.access_key',
            '/^[0-9]{6}[0-9A-Z]{14}[0-9]{30}$/',
            'the 50-character access key of an NFS-e',
        );
        $this->reasonCode = Check::requiredChoice(
            Check::pad2($reasonCode),
            'nfse.substitution.reason_code',
            ['01', '02', '03', '04', '05', '99'],
        );
        $this->reason = Check::text(Check::oneLine($reason), 'nfse.substitution.reason', 15, 255);

        if ($this->reason !== null && preg_match('/[^\x{0000}-\x{00FF}]/u', $this->reason) === 1) {
            Check::fail('nfse.substitution.reason', 'takes Latin-1 characters only');
        }
        if ($this->reasonCode === '99' && $this->reason === null) {
            Check::fail(
                'nfse.substitution.reason',
                'is required with reason_code 99: 15 to 255 characters',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'access_key' => $this->accessKey,
            'reason_code' => $this->reasonCode,
            'reason' => $this->reason,
        ]);
    }
}
