<?php

declare(strict_types=1);

namespace Stackin\Errors;

use Exception;

/**
 * The API responded with a non-2xx status. A 401 specifically means
 * the api_key is missing, wrong, or was rotated.
 *
 * Not final: the refusals a caller acts on by name extend it, so a
 * catch of ApiError still catches every one of them.
 */
class ApiError extends Exception
{
    /**
     * The API's machine-readable code when the body carries one. Not
     * named $code because Exception already owns that property.
     */
    public ?string $errorCode = null;

    /** The request field a rule refused, as the API names it. */
    public ?string $field = null;

    /** Whether repeating the same call can succeed; null when unsaid. */
    public ?bool $retryable = null;

    /** The document id an unanswered NFS-e issuance can be consulted by. */
    public ?string $documentId = null;

    /** @var array<array-key, mixed>|null The decoded response body, when it was JSON. */
    public ?array $body = null;

    /** @var array<string, mixed>|null The SEFAZ rejection a rule mirrors. */
    public ?array $sefazRejection = null;

    public function __construct(
        public readonly int $statusCode,
        public readonly string $detail,
    ) {
        parent::__construct(sprintf('[%d] %s', $statusCode, $detail));
    }

    /**
     * Sets the fields parsed from the response body; called once by the
     * client when it builds the error.
     *
     * @param array<string, mixed>|null $sefazRejection
     * @param array<array-key, mixed>|null $body
     */
    final public function withFields(
        ?string $errorCode = null,
        ?string $field = null,
        ?bool $retryable = null,
        ?string $documentId = null,
        ?array $sefazRejection = null,
        ?array $body = null,
    ): static {
        $this->errorCode = $errorCode;
        $this->field = $field;
        $this->retryable = $retryable;
        $this->documentId = $documentId;
        $this->sefazRejection = $sefazRejection;
        $this->body = $body;

        return $this;
    }
}
