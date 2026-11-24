<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * exigSusp: `type` is "judicial" or "administrative" and `processNumber` the
 * 30-digit proceeding number.
 */
final class IssSuspension
{
    public readonly string $type;

    public readonly string $processNumber;

    public function __construct(
        string $type,
        string $processNumber,
    ) {
        $this->type = Check::requiredChoice(
            $type,
            'nfse.iss.suspension.type',
            ['judicial', 'administrative'],
        );
        $this->processNumber = Check::requiredPattern(
            $processNumber,
            'nfse.iss.suspension.process_number',
            '/^[0-9]{30}$/',
            '30 digits',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Check::compact([
            'type' => $this->type,
            'process_number' => $this->processNumber,
        ]);
    }
}
