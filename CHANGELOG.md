# Changelog

## v0.18.0 — 2026-11-18

### Features
- **invoice:** externalReference and fields filters on history() (#38)
- **invoice:** syncReceived() for POST /received-invoices/sync (#38)
- **client:** usage() with a typed Usage model (#38)
- **errors:** PaymentRequired, RateLimited, ClientInactive and ClientNotFound (#38)

### Docs
- PDF for NF-e and NFS-e, received sync, pricing, usage and errors (#38)

## v0.17.0 — 2026-11-17

### Features
- **clients:** clients resource and client_id on issue (stackin-io/stackin-api#380)

### Docs
- **readme:** saved clients section (stackin-io/stackin-api#380)

## v0.16.0 — 2026-11-17

### Features
- **br:** add IbsCbs group with rate reductions (stackin-io/stackin-api#326)

## v0.15.0 — 2026-11-16

### Features
- **client:** X-Stackin-Company, companies() and typed company errors (#33)

### Docs
- **readme:** company selection, companies() and the company errors (#33)

### Tests
- **client:** company header, companies() and the three company errors (#33)

## v0.14.0 — 2026-11-15

### Features
- **invoice:** discount, issuedAt, purpose, direction, referenced keys and export on issue()
- **product:** fuel group under br.fuel
- **address:** country code and name for a buyer abroad

### Bug Fixes
- **product:** layout groups go under br.extra_groups

### Docs
- **readme:** discount, issuedAt, returns, buyers abroad and fuel

## v0.13.0 — 2026-11-15

### Features
- **product:** validate the RECOPI check digits

### Bug Fixes
- **product:** anchor the RECOPI pattern at the absolute end

## v0.12.0 — 2026-11-15

### Features
- **client:** externalReference on issue(), cityCode optional on an NF-e address

### Docs
- **client:** document externalReference and an optional cityCode

## v0.11.0 — 2026-11-15

### Features
- **invoice:** require recipient address for retained ISS on NFS-e
- **invoice:** require a 44-digit NF-e key on correct()
- **product:** validate the GTIN check digit
- **invoice:** reject non-latin1 text on NF-e before sending
- **validation:** mirror NF-e XSD 4.00 field rules locally

### Bug Fixes
- **invoice:** refuse only text the NF-e cannot carry
- **invoice:** accept an alphanumeric CNPJ in the correct() key
- **invoice:** require a complete address for retained ISS on NFS-e
- **product:** accept only 8-digit NCM or 00

### Docs
- **invoice:** say which correct() cases stay an API 409

### CI
- **lint:** pass src and tests to phpcs explicitly

### Chores
- **platform:** install action-platform

# Changelog — stackin (PHP SDK)

## 0.1.2

### Fixed
- `examples/nfe` sent an incomplete `recipient_address` (state only) — NFE now requires the full address.

### Added
- `examples/nfe/` and `examples/nfse/` — one script per field variant, replacing the two catalog examples.
- Full PHPUnit coverage for `Address`, `Br\Product`, error types, `DocumentType`, and the remaining `Invoice` branches.

## 0.1.1

### Added
- `Br\Product.serviceCode`/`serviceDiscount`/`taxRetained`/`observations` (NFSE-only fields).
- `series`/`number` on `issue()` for manual or auto-calculated NFe/NFSe numbering.

## 0.1.0

### Added
- `Invoice` — `issue()`/`consult()`/`cancel()`, HTTP client for the platform. Defaults to `https://sdk.stackin.io`; `STACKIN_BASE_URL` overrides it.
- `Br\Product` — line item (`description`/`amount` universal, `ncm`/`cfop`/tax array Brazil-specific, required for NFE).
- `Address`.
- `ApiError`, `ConnectionFailedError`, `InvoiceError`.
