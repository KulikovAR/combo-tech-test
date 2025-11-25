<?php

namespace App\Data;

use App\Models\Payment;

class PaymentResult
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string  $message
    )
    {
    }
}

