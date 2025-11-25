<?php

namespace App\Services\Payment;

use App\Contracts\PaymentMethodService;
use App\Data\PaymentData;
use App\Data\PaymentProcessResult;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

class CryptoPaymentService implements PaymentMethodService
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::CRYPTO;
    }

    public function handle(PaymentData $data): PaymentProcessResult
    {
        return new PaymentProcessResult(
            PaymentStatus::PROCESSING,
            'Wait for confirmation...'
        );
    }
}

