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
