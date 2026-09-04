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
    [new Product(description: 'Consultoria', amount: 1500.00)],
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

**NF-e only.** NFS-e has no correction letter, and asking for one returns
a `409`.

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

## Errors

- `Stackin\Errors\ApiError` — the API responded with a non-2xx status (`statusCode`/`detail` properties) — a 401 here means `api_key` is missing, wrong, or was rotated.
- `Stackin\Errors\ConnectionFailedError` — the API didn't respond (network/DNS/timeout).
- `Stackin\Errors\InvoiceError` — `issue()`'s items is empty, missing `ncm`/`cfop` on an item for NFE, or a missing/incomplete recipient `Address` on NFE.

Building the full fiscal document (issuer data, service code, tax groups, schema-accurate XML) is the API's job — configured once per company, not passed on every call.

## Examples

Runnable end-to-end scripts in [`examples/nfe/`](examples/nfe/) and [`examples/nfse/`](examples/nfse/) — one script per field variant, from the bare minimum to every field filled. `examples/consult_invoice.php`, `examples/cancel_invoice.php`, and `examples/reissue_invoice.php` cover the operations that act on an already-issued document.

Commit convention lives in [`CONTRIBUTING.md`](CONTRIBUTING.md), not here.
