<?php

namespace App\Services\Payment;

use App\Contracts\PaymentMethodService;
use App\Data\PaymentData;
use App\Data\PaymentResult;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class PaymentManager
{
    /**
     * @var array<string, PaymentMethodService>
     */
    private array $methods = [];

    /**
     * @param iterable<PaymentMethodService> $services
     */
    public function __construct(
        private PaymentRepository $payments,
        private Dispatcher        $events,
        private LoggerInterface   $logger,
        iterable                  $services
    )
    {
        foreach ($services as $service) {
            $this->methods[$service->method()->value] = $service;
        }
    }

    public function handle(PaymentData $data): PaymentResult
    {
        $service = $this->methods[$data->method->value] ?? null;

        if ($service === null) {
            throw new InvalidArgumentException("Unsupported payment method: {$data->method->value}");
        }

        $this->logger->info('Processing payment', [
            'user_id' => $data->userId,
            'method' => $data->method->value,
            'amount_cents' => $data->amountCents,
        ]);

        $result = $service->handle($data);

        $payment = DB::transaction(function () use ($data, $result): Payment {
            return $this->payments->store($data, $result->status);
        });

        $this->events->dispatch("payment.{$data->method->value}", [
            'id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount_cents' => $payment->amount_cents,
            'status' => $payment->status,
        ]);

        return new PaymentResult($payment, $result->message);
    }
}

