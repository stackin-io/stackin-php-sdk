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

An item's IBS/CBS group (Reforma Tributária) is an `IbsCbs` on the item, sent under `br.ibs_cbs`: `cst`, `classification` (cClassTrib), `rateState`, `rateCity` and `rateFederal`, plus `base`, `reductionIbs` and `reductionCbs`, which are left off the request when null. `reductionIbs` is the IBS pRedAliq (state and municipality) and `reductionCbs` the CBS pRedAliq, both in percent (0–100): the cClassTrib's `pRedIBS` and `pRedCBS` in the official classification table. The API asks for them on CST 011, 200 and 515 and refuses them on any other; the SDK leaves that rule to the API. A plain array with the same snake_case keys is still accepted and sent as given.

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
`simples_apuracao` (1-3), `state_registration` and `service_code`, and anyone but the
owner gets an `ApiError` 403. Unknown keys and malformed codes are an `InvoiceError`
before the call; an OAuth credential needs `company:write`. A 503 means the IBGE
table could not be read: nothing changed, retry after `Retry-After`.

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

```php
use Stackin\Errors\RateLimitedError;
use Stackin\Manifestation;

try {
    $sync = $client->syncReceived();
    echo $sync->stored;
} catch (RateLimitedError $wait) {
    echo $wait->retryAt?->format(DATE_ATOM);
}

$page = $client->received(limit: 20);
echo $page['total'];

$client->manifest($accessKey, Manifestation::CIENCIA);
$client->manifest(
    $accessKey,
    Manifestation::OPERACAO_NAO_REALIZADA,
    'Mercadoria nunca chegou ao endereco',
);
```

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
  `recipientAddress` are null; the ones given win. With `clientId` none of those four is
  required by the SDK: the API checks the merged request. A deactivated client is refused
  there (422) until it is reactivated.
- `updateClient: true` on `issue()` rewrites an existing client with that request's
  recipient data. Left false, the issuance only counts one more use of it.
- Reading needs `invoice:read`; creating, editing and deactivating need `invoice:issue`.
  `company` works here as on `Invoice`, on the client or per call.

## Webhooks

`Webhooks` registers where the company's events are delivered: `invoice.authorized`,
`invoice.rejected`, `invoice.cancelled` and `invoice.corrected` (`Webhooks::EVENTS`).
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

- `Stackin\Errors\ApiError` — the API responded with a non-2xx status (`statusCode`/`detail` properties) — a 401 here means `api_key` is missing, wrong, or was rotated.
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
