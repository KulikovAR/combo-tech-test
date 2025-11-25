<?php

namespace App\Data;

use App\Enums\PaymentMethod;

class PaymentData
{
    public function __construct(
        public readonly int           $userId,
        public readonly int           $amountCents,
        public readonly PaymentMethod $method
    )
    {
    }

    public static function fromValidated(array $data): self
    {
        return new self(
            (int) $data['uid'],
            self::convertToCents($data['sum']),
            PaymentMethod::from($data['method'])
        );
    }

    private static function convertToCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public function formattedAmount(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }
}

