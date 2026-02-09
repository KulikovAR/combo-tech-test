<?php

namespace App\Services\Payment;

use App\Contracts\PaymentClient;
use App\Contracts\PaymentMethodService;
use App\Data\PaymentData;
use App\Data\PaymentProcessResult;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

class CardPaymentService implements PaymentMethodService
{
    public function __construct(private PaymentClient $client)
    {
    }

    public function method(): PaymentMethod
    {
        return PaymentMethod::CARD;
    }

    public function handle(PaymentData $data): PaymentProcessResult
    {
        $success = $this->client->charge($data->userId, $data->amountCents);

        return new PaymentProcessResult(
            $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            $success ? 'Payment successful!' : 'Payment failed, please retry.'
        );
    }
}

