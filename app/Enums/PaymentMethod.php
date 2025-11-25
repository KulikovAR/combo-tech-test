<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CARD = 'card';
    case CRYPTO = 'crypto';
}

