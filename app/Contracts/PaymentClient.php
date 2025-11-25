<?php

namespace App\Contracts;

interface PaymentClient
{
    public function charge(int $userId, int $amountCents): bool;
}

