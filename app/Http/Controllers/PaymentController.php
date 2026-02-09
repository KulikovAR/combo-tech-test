<?php

namespace App\Http\Controllers;

use App\Data\PaymentResult;
use App\Http\Requests\PayRequest;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\JsonResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private PaymentManager $payments)
    {
    }

    public function __invoke(PayRequest $request): JsonResponse
    {
        try {
            $result = $this->payments->handle($request->dto());

            return response()->json([
                'success' => true,
                'status' => $result->payment->status,
                'message' => $result->message,
                'amount' => $result->payment->amount,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}

