<div align="center">

<img src="https://raw.githubusercontent.com/stackin-io/stackin-python-sdk/master/docs/assets/stackin.png" width="120" />

**Integrate once. Issue everywhere.**

[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4?style=flat-square)](composer.json)
[![Packagist](https://img.shields.io/packagist/v/stackin-io/stackin-php-sdk?style=flat-square)](https://packagist.org/packages/stackin-io/stackin-php-sdk)
[![License](https://img.shields.io/badge/license-MIT-informational?style=flat-square)](https://github.com/stackin-io/stackin-php-sdk)

[API Reference](https://docs.stackin.io) · [PHP SDK guide](https://docs.stackin.io/blog/php-sdk)

</div>

---

# stackin

Official PHP SDK for fiscal document issuance — a handful of business fields, nothing about certificates, XML, XSD, signing or SOAP. The API resolves all of that from the issuer's own configuration, identified by `api_key`.

**One class, `Invoice`** — `issue()`/`consult()`/`cancel()`/`reissue()`/`correct()`/`invalidate()`/`pdf()`/`history()`/`submissions()`/`received()`/`syncReceived()`/`manifest()`, nothing else to instantiate. Each line item is a `Br\Product` — `description`/`amount` apply to any document type; `ncm`/`cfop` (plus everything else on `Product`: `cest`, tax groups, presumed credits...) are Brazil-specific and required per item for NFE, ignored for NFSE.

## What a line item is worth

`unitPrice` is the price of **one unit**. `amount` is the **gross total of
the line's products**, before discount, freight, insurance and other
expenses. Send either; sending both asserts that they agree.

```php
// More than one unit — the note's line is 2 x 120.00 = 240.00
new Product(description: 'Teclado', quantity: 2.0, unitPrice: 120.00, unit: 'UN');

// Legacy: amount alone still means the line's gross total
new Product(description: 'Servico', amount: 150.00, quantity: 3.0);
```

Amounts that do not add up are refused before the authorizer sees them,
with a `422` naming the line and both numbers (`ITEM_TOTAL_MISMATCH`).

### Migrating

```text
Before:  quantity: 3.0, amount: 150.00
After:   quantity: 3.0, unitPrice: 50.00
```

Nothing has to migrate. `amount` keeps the meaning it always had and is
not deprecated in this release.

## Install

```shell
composer require stackin-io/stackin-php-sdk
```

## Usage

Get an `api_key` from the [stackin dashboard](https://app.stackin.io) — select the issuing company, then Settings → API key (context `sdk`). One key per issuing company, shown once at creation. The API resolves the issuer (CNPJ, state, address, certificate, environment) entirely from it; nothing about the issuer is ever passed on a call. Defaults to `https://sdk.stackin.io`.

```php
use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Invoice;

$invoice = new Invoice(apiKey: 'COMPANY_API_KEY');

$result = $invoice->issue(
    DocumentType::NFE,
    'Buyer Company Ltd',
    '11222333000181',
    [
        new Product(
            description: 'Rosa Holambra Vermelha',
            unitPrice: 112.44,
            ncm: '06031100',
            cfop: '5102',
        ),
    ],
    new Address(
        street: 'Rua das Palmeiras',
        number: '100',
        neighborhood: 'Centro',
        city: 'Florianopolis',
        state: 'SC',
        zipCode: '88010000',
    ),
    externalReference: 'order-1234',
);

$status = $invoice->consult('ACCESS_KEY...', DocumentType::NFSE);
$invoice->cancel('ACCESS_KEY...', DocumentType::NFSE, 'Typo');

// The PDF, as raw bytes, drawn by the API itself: the DANFE for an
// NF-e, the DANFSe for an NFS-e. The XML stays the legally valid document.
file_put_contents(
    'nota.pdf',
    $invoice->pdf('ACCESS_KEY...', DocumentType::NFSE),
);

// What this company issued, newest first. externalReference matches
// your own id exactly; fields: 'summary' returns a lighter item each.
$page = $invoice->history(externalReference: 'order-1234', fields: 'summary');
```

`externalReference` is your own id for the document — an order number, a ticket. It is stored as sent (up to 255 characters), returned on every read and webhook, and left out of the request when not given.

The recipient `Address` passed to `issue()` is the buyer's address — **required for NFE**. On NFE street, number, neighborhood, city, state and zip code are required: it becomes `enderDest` on the wire and the SEFAZ rejects a partial one. `cityCode` (the 7-digit IBGE municipality code) may be left out — the API resolves it from city and state, and answers `CITY_NOT_FOUND` (422) when it cannot; send it yourself when the city name is ambiguous. `state` is also what resolves `idDest` — a buyer in another state is emitted as an interstate operation automatically. A missing or incomplete address throws an `InvoiceError` locally, before the request goes out.

On NFSE it is the tomador's address: optional, except when any item has `taxRetained: true` (ISSQN retained by the tomador), where the city authorizer refuses the document without it. Then it is required and complete — every field except `cityCode` — and a missing or incomplete one also throws an `InvoiceError` locally.

## Discounts, dates, returns and exports

`issue()` takes a few more optional arguments, after the ones above. Each is left out of the request when not given, so a call that does not pass them sends exactly what it sent before.

| Argument | Wire | What it is |
|---|---|---|
| `discount` | `discount` | A discount on the whole document, in BRL, spread by the API over the lines. |
| `issuedAt` | `issued_at` | The emission moment, a `DateTimeInterface` sent as RFC 3339 with its offset. NF-e: at most 30 days back and 5 minutes ahead; NFS-e: never ahead. |
| `purpose` | `purpose` | NF-e only. `Purpose::NORMAL` (the default), `COMPLEMENTARY`, `ADJUSTMENT` or `RETURN`. |
| `direction` | `direction` | NF-e only. `Direction::OUT` (the default) or `IN`, for a note the company issues for goods it receives. |
| `referencedKeys` | `referenced_keys` | NF-e only. The 44-character access keys this note refers to: exactly one for a complementary note, and the returned note for a return. |
| `foreignId` | `foreign_id` | NF-e to a buyer abroad: their passport or other document, 5 to 20 characters. May be left out. |
| `export` | `export` | NF-e to a buyer abroad: an `Export` saying where the goods leave Brazil. |

A return of goods the buyer sent back:

```php
use Stackin\Direction;
use Stackin\Purpose;

$invoice->issue(
    DocumentType::NFE,
    'Comercial Silva Ltda',
    '11222333000181',
    [new Product(description: 'Notebook', amount: 3500.00, ncm: '84713012', cfop: '1202')],
    $address,
    issuedAt: new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')),
    purpose: Purpose::RETURN,
    direction: Direction::IN,
    referencedKeys: ['35240112345678000190550010000001231234567890'],
);
```

A buyer abroad is `state: 'EX'` on the address. There `zipCode` and `cityCode` may be left out, `countryCode` (the BACEN code, not 1058) and `countryName` name the country, and `taxId` is `null` — the buyer has no CPF or CNPJ:

```php
use Stackin\Export;

$invoice->issue(
    DocumentType::NFE,
    'Importadora Sur S.A.',
    null,
    [new Product(description: 'Notebook', amount: 3500.00, ncm: '84713012', cfop: '7102')],
    new Address(
        state: 'EX',
        street: 'Avenida Corrientes',
        number: '1500',
        neighborhood: 'San Nicolas',
        city: 'Buenos Aires',
        countryCode: '0639',
        countryName: 'Argentina',
    ),
    foreignId: 'AR1234567',
    export: new Export(exitState: 'RS', place: 'Uruguaiana', dispatchPlace: 'Porto Seco'),
);
```

Anywhere else a `null` `taxId` throws an `InvoiceError` locally. The SDK checks the types and nothing more: which combinations the SEFAZ accepts — a complementary note without its key, an export leaving from `EX` — is the API's answer, by name.

`contingency` — NF-e only: `true` sends the document through the UF's contingency authorizer (SVC-AN or SVC-RS, `tpEmis` 6/7) for when its own is down. Left `false`, nothing is sent:

```php
$invoice->issue(DocumentType::NFE, /* ... */, contingency: true);
```

With `contingency: true` it also takes `contingencyStartedAt` (a `DateTimeInterface`, sent as RFC 3339 with its offset and written as `dhCont`; defaults to the emission moment, and may not be later than it) and `contingencyReason` (15 to 256 characters, written as `xJust`; the API has a default reason). Both are left out of the request when `null`, and the API refuses either one without `contingency: true`:

```php
$invoice->issue(
    DocumentType::NFE,
    /* ... */
    contingency: true,
    contingencyStartedAt: new DateTimeImmutable('2026-11-25 09:00:00', new DateTimeZone('-03:00')),
    contingencyReason: 'SEFAZ authorizer unavailable since 09:00',
);
```

A fuel sold under a fuel CFOP needs the layout's `comb` group, as a `Fuel` on the item. It goes on the wire under `br.fuel`; passing a `comb` through `extraGroups` as well throws an `InvoiceError`.

```php
use Stackin\Br\Fuel;

new Product(
    description: 'Gasolina C comum',
    amount: 619.00,
    quantity: 100.0,
    unit: 'L',
    ncm: '27101259',
    cfop: '5656',
    fuel: new Fuel(
        anpCode: '320102001',
        anpDescription: 'GASOLINA C COMUM',
        consumerState: 'SC',
        biodieselPercent: 0.0,
    ),
);
```

`Fuel` also takes `glpPercent`, `naturalGasNationalPercent`, `naturalGasImportedPercent` and `startingValue` (GLP only), `codif` and `temperatureQuantity`.

An item's IBS/CBS group (Reforma Tributária) is an `IbsCbs` on the item, sent under `br.ibs_cbs`: `cst`, `classification` (cClassTrib), `rateState`, `rateCity` and `rateFederal`, plus `base`, `reductionIbs`, `reductionCbs` and `operationIndicator` (cIndOp, 6 digits). Every field but `cst` and `classification` is optional and left off the request when null. `reductionIbs` is the IBS pRedAliq (state and municipality) and `reductionCbs` the CBS pRedAliq, both in percent (0–100): the cClassTrib's `pRedIBS` and `pRedCBS` in the official classification table. The API asks for them on CST 011, 200 and 515 and refuses them on any other; the SDK leaves that rule to the API. A plain array with the same snake_case keys is still accepted and sent as given.

On an NF-e send the three rates (and, if you need them, `base` and the reductions). On an NFS-e send only `cst`, `classification` and `operationIndicator`, which the API requires on a production NFS-e; which of the fields a document type needs is decided by the API, not checked here. A microempreendedor or EPP also needs `simples_apuracao_ibs_cbs` `3` on the company for the NFS-e group (see `Company::update()`).

```php
new IbsCbs(cst: '000', classification: '000001', operationIndicator: '100301');
```

```php
use Stackin\Br\IbsCbs;

new Product(
    description: 'Medicamento',
    amount: 100.00,
    ncm: '30049099',
    cfop: '5102',
    ibsCbs: new IbsCbs(
        cst: '200',
        classification: '200009',
        rateState: 0.1,
        rateCity: 0.0,
        rateFederal: 0.9,
        reductionIbs: 60.0,
        reductionCbs: 60.0,
    ),
);
```

## Retrying safely

Issuing is the one call you must not repeat blindly. If the response is lost — a
timeout, a dropped connection — the document may well have been authorized, and a
second attempt issues a **second** fiscal document: another credit, another number
burned, and undoing it means cancelling, which has a deadline.

Pass an idempotency key to make the retry safe:

```php
$key = bin2hex(random_bytes(16));

$result = $invoice->issue(
    DocumentType::NFSE,
    'Maria Silva',
    '12345678909',
    [new Product(description: 'Consultoria', unitPrice: 1500.00)],
    idempotencyKey: $key,
);
```

Retry with the **same key and the same body** and you get the first response back,
replayed — no second document, no credit consumed. Reissue takes the same argument.

| Situation | What the API does |
|---|---|
| New key | issues normally, records the response |
| Same key, same body | replays the recorded response |
| Same key, different body | API error 422 |
| Same key, first call still running | API error 409 |
| Previous attempt failed | key is released — the retry issues |
| Key older than 24 hours | treated as new |

Generate the key yourself and keep it for as long as you might retry — one UUID per
business event, not per HTTP call. The SDK never generates one, because a key minted
per call would protect nothing, and because two genuinely separate invoices for the
same customer and amount on the same day are a normal thing to issue.

## Correcting a document

Some mistakes don't need a cancellation. A wrong product name, wrong
transport details, a typo in the extra information — a **CC-e** (carta de
correção) fixes those, and it is free: no new credit, no burned series
number, no reissue.

```php
$result = $invoice->correct(
    '35240912345678000199550010000000011000000017',
    DocumentType::NFE,
    'Transportadora corrigida para Rapido Ltda',
);
```

The correction text is 15 to 1000 characters, checked locally before the call.

What a CC-e **cannot** fix: anything that changes the tax owed (base, rate,
price, quantity, totals), the buyer or the seller, or the issue date. Those
still mean cancelling and reissuing. The API sends the legally fixed wording
that says exactly this, attached to every correction.

The original document does not change — the CC-e is an event attached to it, and
the authorized XML stays as it was. A document accepts at most 20 of them, and
they are numbered for you.

**NF-e only.** NFS-e has no correction letter. The access key already says
which one it is — an NF-e access key is 44 characters, an NFS-e key 50 digits —
so a key that is not an NF-e access key is refused locally, before the call,
with an `InvoiceError`:
`correct is NFe only. NFSe has no correction letter — cancel and reissue instead.`
From July 2026 the issuer CNPJ inside the key may be alphanumeric, so its first
12 characters accept `0-9` and `A-Z` (uppercase); the rest of the key is digits.
What only the API can know is still an `ApiError` with status `409`: an NF-e
key sent with `DocumentType::NFSE`, or a key for a document the API does not hold.

## Invalidating unused numbers

NF-e numbering is sequential and the SEFAZ expects it to have no gaps. A number
gets reserved the moment issuing starts, so a submission that fails afterwards —
a rejection, a timeout — leaves a hole in the series. Reporting that range is how
you close it.

```php
$result = $invoice->invalidate(
    '1',
    10,
    12,
    'Numeracao reservada e nao utilizada por falha no ERP',
);
```

The reason is 15 to 255 characters and the range is inclusive; both are checked
locally, as is `number_end` not being below `number_start`.

A number that already reached the authorizer can't be invalidated. The API checks
its own records first and answers `409` naming the offending numbers, without a
round trip — and the authorizer checks again for what we can't see from here.

**NF-e only**, and it takes no access key: there is no document to point at.

Ranges already reported are listed, a page at a time, with the SEFAZ `status` and
`protocol` of each — read it before reporting the same range twice:

```php
$page = $invoice->invalidations(limit: 20);
```

## Before the first document, and after a refusal

```php
$ready = $invoice->readiness();          // GET /company
if (!$ready['ready']) {
    print_r($ready['missing']);          // certificate, certificate_expired, certificate_unreadable, address, state_registration, plan
    print_r($ready['by_document_type']); // per nfe/nfse: ready, missing (with the fields), warnings
    echo $ready['fix_url'];              // the dashboard screen for the first blocker
}

$why = $invoice->rejection($invoiceId);  // a rejected document, by its local id
echo $why['code'], $why['explanation'], implode("\n", $why['steps']), $why['suggested'];

$stats = $invoice->stats(days: 7);       // totals, by type and status, this week against the last
```

`readiness()` (on every client) says whether the company can issue right now and,
when it cannot, what is missing — per document type in `by_document_type`, with
`fix_url` pointing at the dashboard screen that fixes the first blocker — plus the
certificate's expiry, the tax regime (CRT), the default série per document type and
`suggested_fiscal_fields`, what the CNPJ registry suggests (never applied). `rejection()` reads the authorizer's answer
(`submissions()` has it raw) in plain words, with the field to fix and, when there
is one, a suggested value; a document that is not rejected is an `ApiError` 409.
`stats()` takes `days` from 1 to 365 (the API's default 30), checked locally.

### Fix the issuer

```php
use Stackin\Company;

$company = new Company(apiKey: getenv('STACKIN_API_KEY'));
$now = $company->update(                 // PATCH /company
    address: [
        'street' => 'Av. Paulista',
        'number' => '1000',
        'neighborhood' => 'Bela Vista',
        'municipality_code' => '3550308', // 7-digit IBGE code; the name is filled in
        'zip_code' => '01310-100',        // sent as 01310100
    ],
    fiscalFields: ['tax_regime' => '1'], // the company owner's alone
);
var_dump($now['ready']);                  // readiness() fields, plus address and fiscal_fields
```

`update()` fixes what `readiness()` reports and answers whether the company can issue
now. `$address` takes `street`, `number`, `neighborhood`, `municipality_code`,
`municipality_name` and `zip_code` — no `complement`, and `state` changes only in the
dashboard. `$fiscalFields` takes `tax_regime` (1-4), `simples_nacional` (1-3),
`simples_apuracao` (1-3), `simples_apuracao_ibs_cbs` (1 or 3), `state_registration` and
`service_code`, plus the NFS-e settings `approximate_tax_simples_percent`,
`approximate_tax_federal_percent`, `approximate_tax_state_percent`,
`approximate_tax_municipal_percent`, `iss_rate`, `municipal_registration` and
`special_tax_regime` (percentages with two decimals, a comma accepted; an empty string
clears one of these seven). Anyone but the
owner gets an `ApiError` 403. Unknown keys and malformed codes are an `InvoiceError`
before the call; an OAuth credential needs `company:write`. A 503 means the IBGE
table could not be read: nothing changed, retry after `Retry-After`.

`update(blockActivityMismatch: true)` (owner only, a bool; `false` is sent too) makes the
API refuse an issuance with `MEI_ACTIVITY_NOT_ALLOWED` or `ACTIVITY_NOT_REGISTERED` as a
422 `ApiError` instead of a warning. It counts as a change by itself.

### Activity warnings, `nbs` and `acceptLanguage`

`issue()` returns the result array plus a `warnings` key: always a list, empty when
there are none, each entry an array with `code` (`MEI_ACTIVITY_NOT_ALLOWED`,
`ACTIVITY_NOT_REGISTERED`, `MEI_NO_ICMS_ACTIVITY`), `message` and `field` (`null` when
the finding is not about one field). `reissue()` is unchanged.

```php
$result = $invoice->issue(
    DocumentType::NFSE,
    'Maria Silva',
    '12345678909',
    [new Product(description: 'Consultoria', unitPrice: 1500.00, nbs: '1.1502.10.00')],
    acceptLanguage: 'pt-BR',
);
foreach ($result['warnings'] as $warning) {
    echo $warning['code'], ': ', $warning['message'], "\n";
}
```

`acceptLanguage` (`pt-BR`, `en` or `es`; the API falls back to English) localizes the
warnings' `message` on `issue()` and `validate()`. It goes out as the `Accept-Language`
header only, never in the body, and is left out when unset. `nbs` is the NFS-e service's
NBS code (nine digits or dotted), sent on the line beside `serviceCode`; the API requires
it on a line that carries `ibsCbs` and refuses an unknown code, both as an `ApiError`.
Catalog items take `nbs` too (`$items->upsert(..., nbs: '115021000')`).

`$reference->nbsSuggestions('1.07')` (`GET /service-codes/{service_code}/nbs`, on
`FiscalReference`) lists the NBS codes NFS-e Anexo VIII pairs with a service code, in any
form `serviceCode` takes (`1.07`, `01.07.00`, `010700`). It answers an `NbsSuggestions`
with `serviceCode`, `suggestions` (each an `NbsSuggestion`: `nbs`, `description`,
`operationIndicator` (cIndOp), `ibsCbsClassification` (cClassTrib) and
`classificationName`, null where the annex lists none; and, when the official cClassTrib
table has them, `classificationCst` (for `ibsCbs` `cst`), `reductionIbs` and `reductionCbs`
(percent reductions, as `float`), `legalReference` (the LC 214/2025 article), `incidence`
(where Anexo VIII says the IBS is due) and `operationVariants`, the cIndOp for the same
operation when it is not onerous or the acquirer is abroad, keyed `onerous_domestic`,
`onerous_abroad`, `free_domestic` and `free_abroad`) and `source`. They are suggestions
only: nothing is written into a document unless you send them as the line's `nbs` and its
`ibsCbs`. A code the route cannot read is an `ApiError` 422.

```php
$found = (new FiscalReference(apiKey: getenv('STACKIN_API_KEY')))->nbsSuggestions('1.07');
foreach ($found->suggestions as $row) {
    echo $row->nbs, ' ', $row->operationIndicator, ' ', $row->ibsCbsClassification, "\n";
    echo $row->classificationCst, ' ', $row->reductionIbs, ' ', $row->legalReference, "\n";
    echo $row->operationVariants['free_abroad'] ?? '', "\n";
}
```

### NFS-e fields of the DPS

All optional, NFS-e only (the API refuses them on an NF-e) and left out of the request when
null. Decimals go out as decimal strings and dates as `YYYY-MM-DD` (a `DateTimeInterface`
or the string).

- On a line (`Product`): `nbs`, `conditionalDiscount` (BRL, above 0), `municipalServiceCode`
  (3 digits; refused for an MEI) and `internalCode` (up to 20 letters or digits). `IbsCbs`
  also takes `personalUse`, `government`, `referencedNfse`, `recipient`, `realEstate`,
  `movableGoods`, `regularTaxation`, `deferral` and `presumedCredit`.
- On `issue()` and `validate()`: `recipientMunicipalRegistration` (up to 15 letters or
  digits, masks dropped), `foreignIdMissingReason` (1 or 2) and, on `Address`, `complement`
  and, for a tomador abroad, `countryIso2`, `postalCode` and `province`.
- On `Address`, NF-e only: `stateRegistration`, the recipient's IE (2 to 14 digits, or
  `ISENTO`; spaces, dots, dashes and slashes are dropped). Left out, the recipient is a
  non-contributor; a number marks a contributor and `ISENTO` an exempt one.
- `nfse:` a `NfseDetails` with `approximateTaxes`, `competenceDate`, `serviceCityCode` or
  `serviceCountryCode`, `iss`, `federal`, `deductions`, `substitution`, `construction`,
  `event`, `references`, `provider` (`NfseProvider`: `caepf` of 14 digits, `phone`, `email`),
  `recipientCaepf` (14 digits), `intermediary` and `foreignTrade`. A `PersonAddress` is the
  address of a deduction supplier and of the intermediary. A deduction document also takes
  `otherDfe` (`OtherDfe(key, description)`, key up to 50 characters) as one of its
  identifications; the API reads it on the homologation layout only.

```php
$invoice->issue(
    DocumentType::NFSE,
    'Maria Silva',
    '12345678909',
    [new Product(description: 'Consultoria', unitPrice: 1500.00, serviceCode: '1.07')],
    nfse: new NfseDetails(
        competenceDate: new DateTimeImmutable('2026-11-20'),
        serviceCityCode: '3550308',
        approximateTaxes: new ApproximateTaxes(
            federalPercent: 13.45,
            statePercent: 0.0,
            municipalPercent: 2.0,
        ),
        iss: new IssDetails(rate: 2.5, retention: 'tomador'),
    ),
);
```

The API refuses some groups for an MEI: `deductions` (`DEDUCTIONS_NOT_FOR_MEI`), `federal`
(`FEDERAL_TAXES_NOT_FOR_MEI`), `iss.rate`, a retention and `municipalServiceCode`. An NFS-e
takes one line: more than one is a 422 `NFSE_SINGLE_ITEM`. Findings the API lets through
come back in the `warnings` of the result: `APPROXIMATE_TAXES_MISSING`,
`IBS_CBS_RECOMMENDED`, `RECIPIENT_COMPLEMENT_OMITTED`, `CONSTRUCTION_RECOMMENDED`,
`EVENT_RECOMMENDED` and `FOREIGN_TRADE_RECOMMENDED`. The invoice detail returns the stored
`nfse` and `substituted_by_invoice_id`.

The shapes the API fixes (patterns, lengths, enumerations, ranges, decimal places and the
groups that take exactly one of several members) are checked when each group is built and
raise an `InvoiceError` naming the field. What depends on the company, the service code or
the environment is the API's and comes back by name. The company's own defaults are the
`Company::update()` settings above; a group sent in `nfse` overrides them for that document.

## Suggesting a classification

```php
$suggestion = $invoice->classify(
    'Cerveja artesanal IPA 500ml',
    DocumentType::NFE,
    recipientState: 'SP',
    answers: ['purpose' => 'resale'],    // optional, from missing_information
);
$best = $suggestion['primary_candidate']; // ncm, cfop, cst/csosn, cest, confidence
```

**A suggestion, never a decision.** Every code in a candidate is read from the
published tables, but the NCM and CFOP decide the tax owed: show it, have someone
confirm it, and only then put it on an item. `missing_information` lists what
would settle it; answer through `answers` and ask again. `description` is 3 to
500 characters, checked locally.

`answers` takes `origin` (`'resale'` or `'own_production'`) to settle an NF-e's CFOP: a
resold product is `x102`, the company's own production `x101`. When the description does
not say which, `missing_information` asks for it. `classify()` takes `acceptLanguage`
(`pt-BR`, `en` or `es`), sent as the `Accept-Language` header only.

### Dry run, and an NFS-e whose outcome is unknown

`validate()` takes the arguments of `issue()` (no idempotency key, `acceptLanguage` included) and runs the API's
rules without transmitting, taking a number or charging. It answers a
`ValidationResult` (`valid`, `errors`, `warnings`) both when the rules pass and when
they fail; each finding has `code`, `message` and `field`, plus `sefaz_rejection`,
`expected`, `received` or `fix_url` when the API has them. An unreadable body, an
unknown client or item, a credential or a rate limit still throws `ApiError`.

```php
$check = $invoice->validate(DocumentType::NFE, 'Acme', '12345678000195', $items, $address);
if (!$check->valid) {
    foreach ($check->errors as $finding) {
        echo $finding['field'], ': ', $finding['message'], "\n";
    }
}
```

`consultByDps($dpsId)` reads an NFS-e by the DPS id: the `documentId` of an
`OUTCOME_UNKNOWN` error says whether the authority took the issuance. It returns what
`consult()` returns, a cancelled note carrying `status` `cancelled`; a `400` means the
id is not this company's and a `404` that nothing was issued.

## Documents issued against you

Everything above serves the **issuer**. These two serve the **recipient**: what
suppliers billed to this CNPJ, and the formal answer to it.

Reading the list never calls the SEFAZ: it returns what the API already collected.
Collecting runs on a schedule on the API side (every six hours), and
`syncReceived()` asks for it now. After a read that finds nothing more the SEFAZ
imposes a one-hour wait; a sync inside it throws `RateLimitedError` with
`errorCode` `DISTRIBUTION_COOLDOWN` and `retryAt` set to when the wait ends.

The first 50 received documents a month are included in every plan; after that
R$ 0,05 each is taken from the account credit. Some existing accounts are exempt,
and `usage()->receivedFree` says whether yours is.

Besides `data`, the page carries a `sync` key that says how fresh the list is:

| Key | Meaning |
|---|---|
| `status` | `ok` (the last read worked), `cooling_down` (the SEFAZ asked for a wait), `failing` (reads keep failing) or `never` (nothing read yet: no certificate, or the first sweep is pending) |
| `next_sync_at` | no read is allowed before this instant (UTC) |
| `competing_reader` | `true` when the SEFAZ blocks the reads because another system reads the same CNPJ with the same certificate |
| `hint` | the usual cause and what to do, in the language of `acceptLanguage` (`pt-BR`, `en` or `es`) |

`received()` takes `acceptLanguage` for the `hint`; `last_synced_at`, `failing_since`, `error_code`, `error_message` and `gaps_pending` come in the same array.

```php
use Stackin\Errors\RateLimitedError;
use Stackin\Manifestation;

try {
    $sync = $client->syncReceived();
    echo $sync->stored;
} catch (RateLimitedError $wait) {
    echo $wait->retryAt?->format(DATE_ATOM);
}

$page = $client->received(limit: 20, acceptLanguage: 'en');
echo $page['total'];
echo $page['sync']['status'], ' ', $page['sync']['hint'] ?? '';

$client->manifest($accessKey, Manifestation::CIENCIA);
$client->manifest(
    $accessKey,
    Manifestation::OPERACAO_NAO_REALIZADA,
    'Mercadoria nunca chegou ao endereco',
);
```

`receivedXml($accessKey)` returns the XML (`procNFe`) of a received NF-e as a string of raw
bytes, never billed. While only the summary is stored it throws `ApiError` 409 with
`errorCode` `RECEIVED_XML_NOT_AVAILABLE`; manifest the document to get the full one.
`PaymentRequiredError` with `RECEIVED_PAYMENT_REQUIRED` means the account cannot pay for it.

Before you answer a document the SEFAZ sends only a **summary** (`resNFe`): access
key, issuer, amount, date. The **full document** (`nfeProc`) arrives after a
manifestation, and the `schema` field on each row says which one you hold.

The four answers are `210200` Confirmação da Operação, `210210` Ciência da
Operação, `210220` Desconhecimento da Operação and `210240` Operação não
Realizada. Only the last one takes a reason, and it requires one — both rules are
checked locally, before the request goes out, because a round trip to be told a
fixed rule is a round trip wasted.

## Looking up a code, or who a CNPJ belongs to

Two more clients, for the tables an issuer reads while filling a document. Neither writes anything.

```php
use Stackin\FiscalReference;
use Stackin\Taxpayer;

$ref = new FiscalReference(apiKey: '...');        // country 'BR' by default

$ref->ncm->get('84716052');                       // one code
$ref->ncm->search('teclado', limit: 5);           // a page of matches
$ref->cfop->get('5102');
$ref->kinds();                                    // what this country has
$ref->kind('ibs_cbs_class')->get('000001');       // any kind, named or not
$ref->search('teclado');                          // every kind at once

(new Taxpayer(apiKey: '...'))->get('00000000000191');
```

`cfop`, `ncm`, `cest`, `cst`, `csosn`, `issService`, `icmsFuel` and `ibsCbsClass` have accessors. **`kind($name)` reaches anything else**, including a classification published after this release — ask `kinds()` rather than trusting this list.

`metadata` comes back as the API sends it and differs per kind: `utrib` on an NCM, `ncm_code` on a CEST, `tax_type` on a CST, `null` on an ISS service.

### Simulating the IBS and CBS

`calculateIbsCbs()` (`POST /ibs-cbs/calculate`, scope `invoice:read`) simulates the IBS and CBS of up to 990 items. Nothing is issued or stored, and the answer says so (`simulated`, `notice`):

```php
use Stackin\IbsCbsCalculationItem;
use Stackin\IbsCbsRates;

$result = $ref->calculateIbsCbs(
    items: [
        new IbsCbsCalculationItem(cst: '000', classification: '000001', amount: 100.00),
        ['cst' => '200', 'classification' => '200009', 'base' => 50.00, 'reduction_ibs' => 60.0],
    ],
    operationDate: '2026-06-01',            // YYYY-MM-DD
    documentType: 'nfe',                    // 'nfe' (default), 'nfce' or 'nfse'
    rates: new IbsCbsRates(rateState: 0.1, rateCity: 0.0, rateFederal: 0.9), // optional
);

$result->total->ibs;                        // "0.10": amounts are the API's decimal strings
$result->items[0]->ratesSource;             // 'caller' or 'official_2026'
```

An item takes `cst` (3 digits), `classification` (6 digits), `amount` and/or `base` (above zero, at least one), the three rates and `reductionIbs`/`reductionCbs` (0-100); `items` may be `IbsCbsCalculationItem` objects or arrays with the same snake_case keys. `rates` are the defaults for items that carry none, and an item's own rate wins. Left out in 2026 the official 2026 rates apply; in any other year they are required, and the API answers 422 `IBS_CBS_RATES_REQUIRED` (an `ApiError`). `documentType` only picks the model the cClassTrib is checked for, and the amounts are always the NF-e's: the simulation does not compute NFS-e amounts, which the Sefin Nacional does. These rules are checked before the call: 1 to 990 items, the `cst` and `classification` patterns, amount or base, the 0-100 ranges and the date format.

The result is an `IbsCbsCalculation` (`simulated`, `notice`, `documentType`, `operationDate`, `items`, `total`). Each `IbsCbsCalculatedItem` has `indicators` (`IbsCbsIndicators`: `group`, `reduction`, `deferral`), `hasGroup`, `rates` and `effectiveRates` (`IbsCbsAppliedRates`, or null), `ratesSource`, `reductionIbs`, `reductionCbs`, `deferral` and `amounts`; the `amounts` and the `total` are an `IbsCbsAmounts` (`base`, `ibsState`, `ibsCity`, `ibs`, `cbs`, `deferredIbsState`, `deferredIbsCity`, `deferredCbs`). Fields the API adds later are ignored.

`cities($name, state: null)` lists the municipalities whose name starts with `$name`, at most 20, each with its IBGE `code` — issuing resolves `cityCode` by itself, so this is for a name that matches more than one:

```php
$ref->cities('santa rita', state: 'SP'); // [['country' => 'BR', 'code' => '...', 'name' => '...', 'state' => 'SP']]
```

Three things worth knowing before you loop:

- **These share the invoice read allowance** — 600 calls a minute per key, the same bucket `consult()`, `history()`, `received()` and `pdf()` draw from. One `search()` page beats N `get()` calls.
- **Ordering is fixed** (kind, then code, ascending). Unlike `history()`, `search()` takes no `$sortBy`/`$orderBy`.
- **A 404 from `Taxpayer` does not mean the company does not exist.** That registry reloads monthly, so a recently registered CNPJ is simply not in it yet. Do not build a validation rule on it.

`Taxpayer` has one method and keeps one: the registry holds the names and addresses of real people, so there is no search over it, by design.

## Saved clients

The company keeps the recipients it issues to. A successful issuance saves a new
recipient on its own; `Clients` reads and edits them, and `issue(clientId: ...)` issues
to one without retyping it.

```php
use Stackin\Clients;
use Stackin\Errors\ClientAlreadyExistsError;

$clients = new Clients(apiKey: '...');

$clients->list(search: 'acme', limit: 20);          // active ones, by name
$clients->list(isActive: false);                    // the deactivated ones

try {
    $acme = $clients->create(name: 'ACME Ltda', taxId: '11.222.333/0001-81');
} catch (ClientAlreadyExistsError $error) {
    $acme = $clients->get($error->clientId);        // it was there already
}

$clients->update($acme['id'], ['phone' => '+5511999999999', 'notes' => null]); // null clears
$clients->deactivate($acme['id']);  // kept, hidden from list()
$clients->reactivate($acme['id']);

(new Invoice(apiKey: '...'))->issue(
    documentType: DocumentType::NFSE,
    clientName: null,                               // name, tax id, address from the client
    taxId: null,
    items: [/* ... */],
    clientId: $acme['id'],
);
```

- `list()` filters by `search` (part of the name, the tax id masked or not, or the
  foreign id), `source` (`manual`, `issued`, `issuance`) and `isActive` (the API lists
  active clients when it is null), and pages with `limit`/`offset`. `sortBy` takes
  `name`, `last_used_at`, `created_at` or `uses_count`.
- `update()` takes the changes keyed by their API names (`name`, `tax_id`, `foreign_id`,
  `state_registration`, `municipal_registration`, `email`, `phone`, `address`, `notes`,
  `is_active`) and sends only those; `address` (an `Address` or an array) replaces the
  whole address. The API checks the client as a whole, so clearing a Brazilian client's
  `tax_id` is refused like a create without one.
- On `issue()`, the client fills whichever of `clientName`, `taxId`, `foreignId` and
  `recipientAddress` are null; the ones given win. The client's e-mail and phone likewise
  fill `clientEmail` and `clientPhone` when null. With `clientId` none of those four is
  required by the SDK: the API checks the merged request. A deactivated client is refused
  there (422) until it is reactivated.
- `updateClient: true` on `issue()` rewrites an existing client with that request's
  recipient data. Left false, the issuance only counts one more use of it.
- NF-e sale terms on `issue()` and `validate()`, each left out of the body when null:
  `presence` ("0" to "5", "9"), `intermediary` (`Br\Intermediary(taxId, identifier)`),
  `transport` (`Br\Transport(freightMode, volumes)` of `Br\TransportVolume`), `payments`
  (a list of `Br\Payment(method, amount, description, card)` with `Br\PaymentCard`) and
  `change`. Product `netWeight`/`grossWeight` and the same two on `Items::upsert()`, with
  `ibsCbsCst`/`ibsCbsClass`. An empty `volumes` list is sent as `[]`. The SDK checks code
  sets, weights >= 0 and a description on method "99"; the API answers the fiscal rules.
  `reissue()` takes none of them and reuses the stored terms.
- `clientEmail` and `clientPhone` on `issue()` are contact data for the client, also kept on
  the invoice and sent again on a reissue (which does not touch the client). They are
  written into the fiscal document: NF-e `dest/email` (up to 60 characters) and
  `dest/enderDest/fone` (digits only, up to 14, only with a `recipientAddress`); NFS-e
  `toma/email` (up to 80) and `toma/fone` (up to 20 digits). The phone goes out digits
  only, area code first; a `+55` is dropped unless the recipient is abroad. A value the
  layout cannot carry (too long, an e-mail with characters beyond Latin-1) is left out of
  the document and the issuance goes on. The e-mail must be valid; the phone is 8 to 15 digits with an optional leading `+` (the API drops spaces,
  dots, dashes and parentheses). They are saved on the client created after a successful
  issuance, and on an existing one only with `updateClient: true`, and only the ones sent.
  Left out of the request when null.
- Reading needs `invoice:read`; creating, editing and deactivating need `invoice:issue`.
  `company` works here as on `Invoice`, on the client or per call.

## Webhooks

`Webhooks` registers where the company's events are delivered: `invoice.authorized`,
`invoice.rejected`, `invoice.cancelled`, `invoice.corrected`, and for documents
issued against the company `received_invoice.stored` and `received_invoice.full_document`,
and the panel actions `integration.settings_changed`, `integration.order_retry`,
`integration.order_issue` and `integration.catalog_sync` (`Webhooks::EVENTS`). The API sends
the `integration.*` events only to an endpoint that names them.
An endpoint receives the events of the credential's own environment only.

```php
use Stackin\Webhooks;

$hooks = new Webhooks(apiKey: '...');

$endpoint = $hooks->create(
    'https://shop.example/stackin',
    getenv('STACKIN_WEBHOOK_SECRET'),                 // 16 to 255 characters
    events: ['invoice.authorized', 'invoice.rejected'], // left out: every event
);
$hooks->list();                 // a plain list; secrets are never returned
$hooks->deliveries(limit: 20);  // every attempt: status, attempts, last_error
$hooks->delete($endpoint['id']); // stops delivery, keeps the history
```

The secret signs every delivery and is not returned again, so keep it. An `https://`
URL, the secret's length and the event names are checked locally; the API also
refuses a URL that points at a private network. On an OAuth credential every call
needs `webhook:manage`.

## One credential, several companies

An API key is always one company and needs none of this. An OAuth credential can cover
several companies, each with its own permissions; name the one a request acts for with
`company`, by its id or its CNPJ (punctuation is ignored). It goes out as the
`X-Stackin-Company` header on every request, lookups included.

```php
$invoice = new Invoice(apiKey: '...', company: '00.000.000/0001-91');

$invoice->history();                                    // that company
$invoice->history(company: '7b0c1f9e-3a52-4d8e-9f61-2c4b8a1d5e70'); // this call only

$invoice->companies();
// ['environment' => 'production', 'companies' => [
//     ['company_id' => '...', 'name' => '...', 'tax_id' => '...', 'permissions' => [...]],
// ]]
```

Every method takes `company:` for a single call, and `FiscalReference` and `Taxpayer` take it
too. With no company set no header is sent, so the request is exactly what it was before.
`companies()` (`GET /api/v1/me/companies`) lists what the credential reaches and the ids
`company` accepts.

## Usage and credit

`usage()` (`GET /api/v1/company/usage`, on every client) returns a `Stackin\Usage`:
the plan, issued documents against its allowance (`includedInvoices`/`usedInvoices`,
`overagePriceCents` past it, `null` when the plan stops instead), received documents
(`includedReceived`/`usedReceived`/`receivedPriceCents`/`receivedFree`), the period
(`currentPeriodStart`/`currentPeriodEnd`, `DateTimeImmutable`), the `creditBalance`
in cents and `companiesOnAccount`. Fields the API adds later are ignored.

```php
$usage = $invoice->usage();
echo "{$usage->usedInvoices}/{$usage->includedInvoices}, credit {$usage->creditBalance}";
```

## Catalog items

The company keeps the products and services it sells. `Items` reads and edits that
catalog, and a line of `issue()` names an entry with `itemId` to be filled from it.
An item is a `product` (NF-e) or a `service` (NFS-e); a fiscal field of the other kind
is refused (422). Nothing fiscal is required to save one: an NF-e still refuses a line
without `ncm`/`cfop` when it is issued.

```php
use Stackin\Br\Product;
use Stackin\Items;
use Stackin\Errors\ItemAlreadyExistsError;

$items = new Items(apiKey: '...');

$items->list(kind: 'product', search: 'cafe', limit: 20);   // active ones, by description
$items->list(isActive: false);                              // the deactivated ones

// Upsert: the same externalReference (or code) updates instead of duplicating.
$saved = $items->upsert(
    kind: 'product',
    description: 'Cafe torrado 500g',
    code: 'CAF-500',
    unitPrice: 18.9,
    ncm: '09012100',
    cfop: '5102',
    externalReference: 'ns-variant-1',
);
$saved->created;                                            // true = 201, false = 200
$item = $saved->item;                                       // the item, as an array

$items->get($item['id']);
$items->update($item['id'], ['unit_price' => 20.5, 'notes' => null]); // null clears
$items->deactivate($item['id']);   // kept, hidden from list()
$items->reactivate($item['id']);

(new Invoice(apiKey: '...'))->issue(
    documentType: DocumentType::NFE,
    clientName: 'ACME Ltda',
    taxId: '11222333000181',
    items: [new Product(itemId: $item['id'], amount: 18.9)],  // no description needed
    recipientAddress: $address,
);
```

- `unitPrice` takes a float or a decimal string (up to 10 places) and goes out as a decimal string, so `'18.9000000001'` keeps every digit; a bad string is an `InvoiceError` before the call. `update()` treats a `unit_price` the same way.
- `create()` and `upsert()` send only the arguments that are not null, and both are the API's
  upsert; `create()` answers the item (an array, like `Clients`) and is kept for parity,
  `upsert()` answers an `ItemSaved` (`->item`, `->created`). It updates the company's item
  with the same `externalReference`, or the same `code` when none is sent; the item
  keeps its `source` and active state. `ItemSaved::$created` tells the 201 from the 200.
  A `code` that belongs to another item is an `ItemAlreadyExistsError`.
- `list()` filters by `kind`, `search` (description, code, external reference; digits
  also match `ncm` and `gtin`), `source` (`manual`, `import`, `nuvemshop`, `invoice`)
  and `isActive`, and pages with `limit`/`offset`. `sortBy` takes `description`, `code`,
  `unit_price`, `last_used_at`, `created_at` or `uses_count`. It returns the API's
  envelope. `get()` and `update()` answer the item as the API sends it (snake_case keys).
- `update()` takes the changes keyed by their API names and sends only those. The API
  checks the item as a whole, so turning a product into a service that still has an
  `ncm` is refused.
- On `issue()`, `Product(itemId: ...)` is filled from the catalog with whatever the line
  leaves out (description, unit price, `unit`, `barcode`, `ncm`, `cfop`, `cest`, `tax`,
  service code, `taxRetained`); what the line sends wins. With `itemId`, `description`
  is optional, and `unit` and `taxRetained` are no longer defaulted to `UN` and false, so
  the catalog's apply. A deactivated item is refused (422 `ItemInactiveError`), as is a
  service on an NF-e or a product on an NFS-e (`ItemKindMismatchError`). A catalog tax
  situation that needs a base and a rate (CST 00, CSOSN 101/500/900...) needs the line's
  own `tax` (`ItemTaxRequiredError`), and an item whose CST/CSOSN does not fit the company's
  regime is refused with `ItemTaxRegimeMismatchError`.

## Errors

- `Stackin\Errors\ApiError` — the API responded with a non-2xx status (`statusCode`/`detail` properties) — a 401 here means `api_key` is missing, wrong, or was rotated. It also carries, null when the body has none: `errorCode` (the API's code, e.g. `NCM_INVALID`, `OUTCOME_UNKNOWN`, `539`), `field` (the request field a rule refused), `retryable` (a nullable bool), `documentId` (the id an unanswered NFS-e can be consulted by, see `consultByDps()`), `sefazRejection` and the decoded `body`. When the API sends a rule's `message`, `detail` is that message.
- The company refusals extend `ApiError`, so a `catch (ApiError)` still catches them. None is retried:
  - `Stackin\Errors\CompanyRequiredError` — `422`: the credential covers several companies and the request named none. `companies` lists them.
  - `Stackin\Errors\CompanyNotInScopeError` — `403`: the named company is not covered. `companies` lists the ones that are.
  - `Stackin\Errors\InsufficientPermissionError` — `403`: the credential reaches the company but lacks this permission there. `description` reads `needs X, has Y, company=<id>`.
- `Stackin\Errors\ClientAlreadyExistsError` — `409` (an `ApiError`): `Clients::create()` or `update()` with a tax id or foreign id the company already has on another client. `clientId` names that client.
- `Stackin\Errors\ClientInactiveError` — `422` with code `CLIENT_INACTIVE` (an `ApiError`): `issue()` with the `clientId` of a deactivated client. `clientId` names it; `Clients::reactivate()` brings it back.
- `Stackin\Errors\ClientNotFoundError` — `404` with code `CLIENT_NOT_FOUND` (an `ApiError`): no saved client with that id. `clientId` echoes it.
- The catalog refusals are `ApiError`s too, each with `itemId`:
  - `Stackin\Errors\ItemAlreadyExistsError` — `409` `ITEM_EXISTS`: a `code` or `externalReference` the company already has on another item (a deactivated item still holds its code). `field` is `code` or `external_reference`.
  - `Stackin\Errors\ItemNotFoundError` — `404` `ITEM_NOT_FOUND`: no item of this company with that id.
  - `Stackin\Errors\ItemInactiveError` — `422` `ITEM_INACTIVE`: `issue()` with a deactivated item; `Items::reactivate()` brings it back.
  - `Stackin\Errors\ItemKindMismatchError` — `422` `ITEM_KIND_MISMATCH`: a service on an NF-e or a product on an NFS-e. `index` is the line.
  - `Stackin\Errors\ItemTaxRequiredError` — `422` `ITEM_TAX_REQUIRED`: the line must send its own `tax`.
  - `Stackin\Errors\ItemTaxRegimeMismatchError` — `422` `ITEM_TAX_REGIME_MISMATCH`: the item's CST/CSOSN does not fit the company's regime (a CSOSN is for Simples Nacional, a CST for the rest) or an export CFOP. `index` is the line.
- `Stackin\Errors\PaymentRequiredError` — any `402` (an `ApiError`): the plan's allowance is spent and the credit does not cover the operation. `usage()` shows where the account stands.
- `Stackin\Errors\RateLimitedError` — any `429` (an `ApiError`): `retryAfter` (seconds, from the `Retry-After` header), `retryAt` (`DateTimeImmutable`) and `errorCode` when the API sends them, e.g. `DISTRIBUTION_COOLDOWN` from `syncReceived()`.
- `Stackin\Errors\ConnectionFailedError` — the API didn't respond (network/DNS/timeout).
- `Stackin\Errors\InvoiceError` — a request that would obviously fail server-side, caught before the network call:
  - `issue()` without `clientName` or `taxId` and no `clientId` (`taxId` may be null for an NF-e to a buyer abroad), items is empty, missing `ncm`/`cfop` on an item for NFE, or a missing/incomplete recipient `Address` on NFE (any field but `cityCode`). A `Product` with neither `description` nor `itemId` is refused too, and `ncm`/`cfop` are not required locally on a line with an `itemId`.
  - a `Product` built with an `ncm` that is not the full 8 digits, or `00` for a service line — SEFAZ rejects any other 2-digit chapter with `777`.
  - a `Product` built with a `barcode` (cEAN) that is neither `SEM GTIN` nor 8, 12, 13 or 14 digits, or whose GS1 check digit is wrong.
  - a `Product` built with a `recopiNumber` that is not the 20-digit RECOPI number; the SDK checks its two modulo-11 check digits (MOC §8.5) before sending.
  - an NFE item `description` over 120 characters (the xProd limit; NFSE descriptions are not limited).
  - an `Address` built with a `street` or `neighborhood` under 2 characters after trimming.
  - on NFE, any free-text field (`client_name`, item `description`/`unit`/`purchase_order`/`purchase_order_item`/`observations`, every recipient `Address` field) holding a character the NF-e cannot carry: anything above ISO-8859-1 (U+00FF) — emoji, CJK, Cyrillic… — is refused. Accents, tabs and newlines are fine, and typographic quotes, dashes, ellipsis, bullet, `€` and `™` are converted by the API, so they are let through. NFSE is not checked.
  - an NFSE where any item has `taxRetained: true` (ISSQN retained by the tomador) but the recipient `Address` is missing or lacks any field other than `cityCode`.
  - `correct()` with an access key that is not an NF-e access key (44 characters) — an NFSe has no correction letter; cancel and reissue instead.
  - `stats()` with `days` outside 1–365, `classify()` with a `description` outside 3–500 characters or a `recipientState` that is not two letters, `FiscalReference::cities()` with a `name` outside 2–100 characters or a `state` that is not two letters, and `Webhooks::create()` with a URL that is not `https://`, a secret outside 16–255 characters or an event name not in `Webhooks::EVENTS`.
  - `rejection()`, `submissions()` or `Webhooks::delete()` with an id of `.` or `..`, which would leave its path segment.

Building the full fiscal document (issuer data, service code, tax groups, schema-accurate XML) is the API's job — configured once per company, not passed on every call.

## Examples

Runnable end-to-end scripts in [`examples/nfe/`](examples/nfe/) and [`examples/nfse/`](examples/nfse/) — one script per field variant, from the bare minimum to every field filled. `examples/consult_invoice.php`, `examples/cancel_invoice.php`, and `examples/reissue_invoice.php` cover the operations that act on an already-issued document. `examples/lookup_fiscal_reference.php` and `examples/lookup_taxpayer.php` cover the two read-only clients.

Commit convention lives in [`CONTRIBUTING.md`](CONTRIBUTING.md), not here.
