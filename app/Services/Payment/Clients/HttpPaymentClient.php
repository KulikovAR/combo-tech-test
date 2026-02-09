<?php

namespace app\Services\Payment\Clients;

use App\Contracts\PaymentClient;
use Illuminate\Support\Facades\Http;
use function App\Services\Payment\config;

class HttpPaymentClient implements PaymentClient
{
    public function __construct(
        private ?string $endpoint = null,
        private ?int    $timeout = null
    )
    {
        $this->endpoint ??= config('services.payments.card_endpoint', 'https://example.com/pay');
        $this->timeout ??= config('services.payments.timeout', 5);
    }

    public function charge(int $userId, int $amountCents): bool
    {
        $amount = number_format($amountCents / 100, 2, '.', '');

        $response = Http::timeout($this->timeout)
            ->throw()
            ->get($this->endpoint, [
                'uid' => $userId,
                'sum' => $amount,
            ]);

        return trim($response->body()) === 'OK';
    }
}

