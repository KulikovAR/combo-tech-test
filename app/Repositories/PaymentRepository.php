<?php

namespace App\Repositories;

use App\Data\PaymentData;
use App\Enums\PaymentStatus;
use App\Models\Payment;

class PaymentRepository
{
    public function store(PaymentData $data, PaymentStatus $status): Payment
    {
        $payment = Payment::create([
            'user_id' => $data->userId,
            'amount_cents' => $data->amountCents,
            'method' => $data->method->value,
            'status' => $status->value,
        ]);

        $payment->amount = $data->formattedAmount();

        return $payment;
    }
}

