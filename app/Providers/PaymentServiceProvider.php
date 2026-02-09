<?php

namespace App\Providers;

use App\Repositories\PaymentRepository;
use App\Services\Payment\CardPaymentService;
use App\Services\Payment\CryptoPaymentService;
use App\Services\Payment\PaymentManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentManager::class, function ($app) {
            return new PaymentManager(
                $app->make(PaymentRepository::class),
                $app->make(Dispatcher::class),
                $app->make(LoggerInterface::class),
                [
                    $app->make(CardPaymentService::class),
                    $app->make(CryptoPaymentService::class),
                ]
            );
        });
    }
}

