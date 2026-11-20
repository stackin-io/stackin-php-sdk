<?php

declare(strict_types=1);

namespace Stackin;

use Stackin\Errors\InvoiceError;

/**
 * Where the company's events are delivered: /webhooks.
 *
 * Company data, so every method takes $company for one call over the
 * client's own. An endpoint receives the events of the credential's own
 * environment only. Every call needs the webhook:manage permission on an
 * OAuth credential; an API key has it.
 */
final class Webhooks extends Client
{
    /**
     * The events an endpoint can subscribe to.
     *
     * @var list<string>
     */
    public const EVENTS = [
        'invoice.authorized',
        'invoice.rejected',
        'invoice.cancelled',
        'invoice.corrected',
    ];

    private const SECRET_MIN = 16;
    private const SECRET_MAX = 255;
    private const URL_MAX = 500;

    /**
     * Registers an HTTPS endpoint for this company's events.
     *
     * $secret (16 to 255 characters) signs every delivery; it is never
     * returned again, so keep it. $events narrows what is sent, from
     * EVENTS; left null, the endpoint receives every event. The API
     * refuses a URL that is not HTTPS or points at a private network.
     *
     * @param list<string>|null $events
     * @return array<string, mixed>
     */
    public function create(
        string $url,
        string $secret,
        ?array $events = null,
        ?string $company = null,
    ): array {
        if (stripos($url, 'https://') !== 0) {
            throw new InvoiceError('url must be an https:// URL');
        }
        if (mb_strlen($url) > self::URL_MAX) {
            throw new InvoiceError('url must be at most ' . self::URL_MAX . ' characters');
        }
        $length = mb_strlen($secret);
        if ($length < self::SECRET_MIN || $length > self::SECRET_MAX) {
            throw new InvoiceError(
                'secret must be ' . self::SECRET_MIN . ' to ' . self::SECRET_MAX . ' characters',
            );
        }

        $payload = ['url' => $url, 'secret' => $secret];
        if ($events !== null) {
            $unknown = array_values(array_unique(array_diff($events, self::EVENTS)));
            sort($unknown);
            if ($unknown !== []) {
                throw new InvoiceError(
                    'unknown events: ' . implode(', ', $unknown)
                    . '. Known: ' . implode(', ', self::EVENTS),
                );
            }
            $payload['events'] = array_values($events);
        }

        return $this->request('POST', '/webhooks', json: $payload, company: $company);
    }

    /**
     * This company's endpoints in the credential's environment.
     *
     * A plain list, not a page. Secrets are never returned.
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $company = null): array
    {
        $rows = $this->request('GET', '/webhooks', company: $company);
        if (!array_is_list($rows)) {
            throw new InvoiceError('unexpected response shape: expected a list');
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * Stops delivery to one endpoint, keeping what was already sent.
     *
     * An id this company does not have is an ApiError 404.
     */
    public function delete(string $endpointId, ?string $company = null): void
    {
        $this->send(
            'DELETE',
            '/webhooks/' . FiscalReference::segment($endpointId),
            company: $company,
        );
    }

    /**
     * Every delivery attempt in the credential's environment, a page.
     *
     * Each row has the event, its status, attempts, the endpoint's
     * response_status, last_error and when it was delivered or will be
     * tried again.
     *
     * @return array<string, mixed>
     */
    public function deliveries(
        ?int $limit = null,
        ?int $offset = null,
        ?string $sortBy = null,
        ?string $orderBy = null,
        ?string $company = null,
    ): array {
        return $this->request(
            'GET',
            '/webhooks/deliveries',
            query: self::pageQuery($limit, $offset, $sortBy, $orderBy),
            company: $company,
        );
    }
}
