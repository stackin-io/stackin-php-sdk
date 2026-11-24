<?php

declare(strict_types=1);

namespace Stackin\Br;

use DateTimeInterface;

/**
 * The DPS atvEvento group. `name`, `startDate` and `endDate` are required
 * (a `DateTimeInterface` or `YYYY-MM-DD`); identify the place by `eventId` or
 * by `address`, one of the two.
 */
final class Event
{
    public readonly string $name;

    public readonly string $startDate;

    public readonly string $endDate;

    public readonly ?string $eventId;

    public readonly ?ServiceAddress $address;

    public function __construct(
        string $name,
        DateTimeInterface|string $startDate,
        DateTimeInterface|string $endDate,
        ?string $eventId = null,
        ?ServiceAddress $address = null,
    ) {
        $this->name = Check::required($name, 'nfse.event.name', 1, 255);
        $this->startDate = Check::requiredDay($startDate, 'nfse.event.start_date');
        $this->endDate = Check::requiredDay($endDate, 'nfse.event.end_date');
        $this->eventId = Check::text($eventId, 'nfse.event.event_id', 1, 30);
        $this->address = $address;

        if (Check::has($this->eventId) === ($this->address !== null)) {
            Check::fail('nfse.event', 'takes event_id or address, one of the two');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'event_id' => $this->eventId,
            'address' => $this->address?->toArray(),
        ]);
    }
}
