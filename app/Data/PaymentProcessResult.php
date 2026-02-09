<?php

namespace App\Data;

use App\Enums\PaymentStatus;

class PaymentProcessResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly string        $message
    )
    {
    }
}

