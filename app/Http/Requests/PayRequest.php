<?php

namespace App\Http\Requests;

use App\Data\PaymentData;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uid' => ['required', 'integer', 'min:1'],
            'sum' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    public function dto(): PaymentData
    {
        return PaymentData::fromValidated($this->validated());
    }
}

