<?php

declare(strict_types=1);

namespace Stackin;

/**
 * One classification, bound to a country.
 */
final class Kind
{
    /**
     * @param callable(string, array<string, string>): array<string, mixed> $call
     */
    public function __construct(
        private $call,
        public readonly string $name,
        public readonly string $country,
    ) {
    }

    /**
     * One code. A code that does not exist is a 404, which arrives as
     * an ApiError — there is no separate not-found type.
     *
     * @return array<string, mixed>
     */
    public function get(string $code, ?string $country = null): array
    {
        $name = FiscalReference::segment($this->name);
        $escaped = FiscalReference::segment($code);

        return ($this->call)(
            "/fiscal-references/{$name}/{$escaped}",
            ['country' => $country ?? $this->country],
        );
    }

    /**
     * A page of this classification, filtered by $term when given.
     *
     * An empty term is no term: the route rejects search='' with a 422,
     * and sending it would invent a failure the caller cannot read.
     *
     * There is no $sortBy or $orderBy. The route accepts both and
     * discards them, so rows always come back ordered by kind then
     * code — unlike Invoice::history(), which does honour them.
     *
     * @return array<string, mixed>
     */
    public function search(
        ?string $term = null,
        ?string $country = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        return ($this->call)(
            '/fiscal-references',
            FiscalReference::searchQuery(
                $this->name,
                $country ?? $this->country,
                $term,
                $limit,
                $offset,
            ),
        );
    }
}
