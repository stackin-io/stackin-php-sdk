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

**One class, `Invoice`** — `issue()`/`consult()`/`cancel()`/`reissue()`, nothing else to instantiate. Each line item is a `Br\Product` — `description`/`amount` apply to any document type; `ncm`/`cfop` (plus everything else on `Product`: `cest`, tax groups, presumed credits...) are Brazil-specific and required per item for NFE, ignored for NFSE.

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
            amount: 112.44,
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
        cityCode: '4205407',
    ),
);
```

The recipient `Address` passed to `issue()` is the buyer's address — **required for NFE** and ignored for NFSE. Every field is required, `cityCode` (the 7-digit IBGE municipality code) included: it becomes `enderDest` on the wire and the SEFAZ rejects a partial one. `state` is also what resolves `idDest` — a buyer in another state is emitted as an interstate operation automatically. A missing or incomplete address throws an `InvoiceError` locally, before the request goes out.

## Errors

- `Stackin\Errors\ApiError` — the API responded with a non-2xx status (`statusCode`/`detail` properties) — a 401 here means `api_key` is missing, wrong, or was rotated.
- `Stackin\Errors\ConnectionFailedError` — the API didn't respond (network/DNS/timeout).
- `Stackin\Errors\InvoiceError` — `issue()`'s items is empty, missing `ncm`/`cfop` on an item for NFE, or a missing/incomplete recipient `Address` on NFE.

Building the full fiscal document (issuer data, service code, tax groups, schema-accurate XML) is the API's job — configured once per company, not passed on every call.

## Examples

Runnable end-to-end scripts in [`examples/nfe/`](examples/nfe/) and [`examples/nfse/`](examples/nfse/) — one script per field variant, from the bare minimum to every field filled. `examples/consult_invoice.php`, `examples/cancel_invoice.php`, and `examples/reissue_invoice.php` cover the operations that act on an already-issued document.

Commit convention lives in [`CONTRIBUTING.md`](CONTRIBUTING.md), not here.
