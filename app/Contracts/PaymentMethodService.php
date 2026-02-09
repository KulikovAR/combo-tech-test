<?php

namespace App\Contracts;

use App\Data\PaymentData;
use App\Data\PaymentProcessResult;
use App\Enums\PaymentMethod;

interface PaymentMethodService
{
    public function method(): PaymentMethod;

    public function handle(PaymentData $data): PaymentProcessResult;
}

