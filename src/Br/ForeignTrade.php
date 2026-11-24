<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * The DPS comExt group, for a service traded with someone abroad. The coded
 * fields take what the layout lists: `serviceMode` 1 to 4, `relationship` 0 to
 * 6 or 9, `providerSupport` 1 to 8, `recipientSupport` 1 to 26 and
 * `temporaryGoods` 1 to 3, where 2 needs `importDeclaration` and 3 needs
 * `exportRegistration`. `currencyCode` is the 3-digit BACEN code.
 * `shareWithMdic` is sent only when true.
 */
final class ForeignTrade
{
    public readonly int $serviceMode;

    public readonly int $relationship;

    public readonly string $currencyCode;

    public readonly string $foreignAmount;

    public readonly int $providerSupport;

    public readonly int $recipientSupport;

    public readonly int $temporaryGoods;

    public readonly ?string $importDeclaration;

    public readonly ?string $exportRegistration;

    public readonly ?bool $shareWithMdic;

    public function __construct(
        int $serviceMode,
        int $relationship,
        string $currencyCode,
        float|string $foreignAmount,
        int $providerSupport,
        int $recipientSupport,
        int $temporaryGoods,
        ?string $importDeclaration = null,
        ?string $exportRegistration = null,
        ?bool $shareWithMdic = null,
    ) {
        $this->serviceMode = Check::requiredInteger(
            $serviceMode,
            'nfse.foreign_trade.service_mode',
            [1, 2, 3, 4],
        );
        $this->relationship = Check::requiredInteger(
            $relationship,
            'nfse.foreign_trade.relationship',
            [0, 1, 2, 3, 4, 5, 6, 9],
        );
        $this->currencyCode = Check::requiredPattern(
            $currencyCode,
            'nfse.foreign_trade.currency_code',
            '/^\d{3}$/',
            '3 digits',
        );
        $this->foreignAmount = Check::requiredDecimal(
            $foreignAmount,
            'nfse.foreign_trade.foreign_amount',
            0,
            true,
            1000000000000000.0,
            2,
        );
        $this->providerSupport = Check::requiredInteger(
            $providerSupport,
            'nfse.foreign_trade.provider_support',
            [1, 2, 3, 4, 5, 6, 7, 8],
        );
        $this->recipientSupport = Check::requiredIntegerRange(
            $recipientSupport,
            'nfse.foreign_trade.recipient_support',
            1,
            26,
        );
        $this->temporaryGoods = Check::requiredInteger(
            $temporaryGoods,
            'nfse.foreign_trade.temporary_goods',
            [1, 2, 3],
        );
        $this->importDeclaration = Check::text(
            $importDeclaration,
            'nfse.foreign_trade.import_declaration',
            1,
            12,
        );
        $this->exportRegistration = Check::text(
            $exportRegistration,
            'nfse.foreign_trade.export_registration',
            1,
            12,
        );
        $this->shareWithMdic = $shareWithMdic;

        $declared = $this->importDeclaration !== null || $this->exportRegistration !== null;
        if ($this->temporaryGoods === 1 && $declared) {
            Check::fail(
                'nfse.foreign_trade',
                'temporary_goods 1 takes no import_declaration or export_registration',
            );
        }
        if ($this->temporaryGoods === 2 && $this->importDeclaration === null) {
            Check::fail('nfse.foreign_trade', 'temporary_goods 2 needs import_declaration');
        }
        if ($this->temporaryGoods === 3 && $this->exportRegistration === null) {
            Check::fail('nfse.foreign_trade', 'temporary_goods 3 needs export_registration');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'service_mode' => $this->serviceMode,
            'relationship' => $this->relationship,
            'currency_code' => $this->currencyCode,
            'foreign_amount' => $this->foreignAmount,
            'provider_support' => $this->providerSupport,
            'recipient_support' => $this->recipientSupport,
            'temporary_goods' => $this->temporaryGoods,
            'import_declaration' => $this->importDeclaration,
            'export_registration' => $this->exportRegistration,
            'share_with_mdic' => $this->shareWithMdic ? true : null,
        ]);
    }
}
