<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'amount_cents',
        'method',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount_cents' => 'integer',
    ];
}

