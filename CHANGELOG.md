# Changelog — stackin (PHP SDK)

## 0.1.0

### Added
- `Invoice` — `issue()`/`consult()`/`cancel()`, HTTP client for the platform. Defaults to `https://sdk.stackin.io`; `STACKIN_BASE_URL` overrides it.
- `Br\Product` — line item (`description`/`amount` universal, `ncm`/`cfop`/tax array Brazil-specific, required for NFE; `serviceCode`/`serviceDiscount`/`taxRetained`/`observations` for NFSE).
- `Address`, `series`/`number` on `issue()` for manual or auto-calculated NFe/NFSe numbering.
- `ApiError`, `ConnectionFailedError`, `InvoiceError`.
- `examples/nfe/` and `examples/nfse/` — one script per field variant.
- Full PHPUnit coverage for `Address`, `Br\Product`, error types, `DocumentType`, and `Invoice`.
