<?php

declare(strict_types=1);

namespace Stackin\Br;

use DateTimeInterface;

/**
 * NFS-e document-level fields beyond the lines, sent as `nfse`. Every group is
 * optional and left out when null; NFS-e only, refused by the API on an NF-e.
 * `competenceDate` (dCompet) is a `DateTimeInterface` or `YYYY-MM-DD`.
 * `serviceCityCode` and `serviceCountryCode` are exclusive, and the country is
 * never BR.
 */
final class NfseDetails
{
    public readonly ?ApproximateTaxes $approximateTaxes;

    public readonly ?string $competenceDate;

    public readonly ?string $serviceCityCode;

    public readonly ?string $serviceCountryCode;

    public readonly ?IssDetails $iss;

    public readonly ?FederalTaxes $federal;

    public readonly ?Deductions $deductions;

    public readonly ?Substitution $substitution;

    public readonly ?Construction $construction;

    public readonly ?Event $event;

    public readonly ?References $references;

    public readonly ?NfseIntermediary $intermediary;

    public readonly ?ForeignTrade $foreignTrade;

    public readonly ?NfseProvider $provider;

    public readonly ?string $recipientCaepf;

    public function __construct(
        ?ApproximateTaxes $approximateTaxes = null,
        DateTimeInterface|string|null $competenceDate = null,
        ?string $serviceCityCode = null,
        ?string $serviceCountryCode = null,
        ?IssDetails $iss = null,
        ?FederalTaxes $federal = null,
        ?Deductions $deductions = null,
        ?Substitution $substitution = null,
        ?Construction $construction = null,
        ?Event $event = null,
        ?References $references = null,
        ?NfseIntermediary $intermediary = null,
        ?ForeignTrade $foreignTrade = null,
        ?NfseProvider $provider = null,
        ?string $recipientCaepf = null,
    ) {
        $this->approximateTaxes = $approximateTaxes;
        $this->competenceDate = Check::day($competenceDate, 'nfse.competence_date');
        $this->serviceCityCode = Check::pattern(
            $serviceCityCode,
            'nfse.service_city_code',
            '/^\d{7}$/',
            'the 7-digit IBGE code',
        );
        $this->serviceCountryCode = Check::pattern(
            $serviceCountryCode,
            'nfse.service_country_code',
            '/^[A-Z]{2}$/',
            'an ISO 3166 alpha-2 code',
        );
        $this->iss = $iss;
        $this->federal = $federal;
        $this->deductions = $deductions;
        $this->substitution = $substitution;
        $this->construction = $construction;
        $this->event = $event;
        $this->references = $references;
        $this->intermediary = $intermediary;
        $this->foreignTrade = $foreignTrade;
        $this->provider = $provider;
        $this->recipientCaepf = Check::pattern(
            $recipientCaepf,
            'nfse.recipient_caepf',
            '/^\d{14}$/',
            '14 digits',
        );

        if (Check::has($this->serviceCityCode) && Check::has($this->serviceCountryCode)) {
            Check::fail('nfse', 'takes service_city_code or service_country_code, not both');
        }
        if ($this->serviceCountryCode === 'BR') {
            Check::fail(
                'nfse.service_country_code',
                'is for a service performed abroad and cannot be BR',
            );
        }
        if (Check::has($this->serviceCountryCode)) {
            if ($this->construction?->address !== null) {
                Check::fail(
                    'nfse.construction.address',
                    'is an address in Brazil, and a service performed abroad takes its ' .
                    'address abroad: identify the construction by its code instead',
                );
            }
            if ($this->event?->address !== null) {
                Check::fail(
                    'nfse.event.address',
                    'is an address in Brazil, and a service performed abroad takes its ' .
                    'address abroad: identify the event by its code instead',
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'approximate_taxes' => $this->approximateTaxes?->toArray(),
            'competence_date' => $this->competenceDate,
            'service_city_code' => $this->serviceCityCode,
            'service_country_code' => $this->serviceCountryCode,
            'iss' => $this->iss?->toArray(),
            'federal' => $this->federal?->toArray(),
            'deductions' => $this->deductions?->toArray(),
            'substitution' => $this->substitution?->toArray(),
            'construction' => $this->construction?->toArray(),
            'event' => $this->event?->toArray(),
            'references' => $this->references?->toArray(),
            'intermediary' => $this->intermediary?->toArray(),
            'foreign_trade' => $this->foreignTrade?->toArray(),
            'provider' => $this->provider?->toArray(),
            'recipient_caepf' => $this->recipientCaepf,
        ]);
    }
}
