<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

enum PaymentMethod: string
{
    case CARD = 'card';
    case CRYPTO = 'crypto';
}

enum PaymentStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PROCESSING = 'processing';
}

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

class PaymentData
{
    public function __construct(
        public readonly int           $userId,
        public readonly int           $amountCents,
        public readonly PaymentMethod $method
    )
    {
    }

    public static function fromValidated(array $data): self
    {
        return new self(
            (int) $data['uid'],
            self::convertToCents($data['sum']),
            PaymentMethod::from($data['method'])
        );
    }

    private static function convertToCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public function formattedAmount(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }
}

class PaymentProcessResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly string        $message
    )
    {
    }
}

class PaymentResult
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string  $message
    )
    {
    }
}

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

interface PaymentClient
{
    public function charge(int $userId, int $amountCents): bool;
}

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

interface PaymentMethodService
{
    public function method(): PaymentMethod;

    public function handle(PaymentData $data): PaymentProcessResult;
}

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

class CryptoPaymentService implements PaymentMethodService
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::CRYPTO;
    }

    public function handle(PaymentData $data): PaymentProcessResult
    {
        return new PaymentProcessResult(
            PaymentStatus::PROCESSING,
            'Wait for confirmation...'
        );
    }
}

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

class PaymentManager
{
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

    /**
     * @var array<string, PaymentMethodService>
     */
    private array $methods = [];

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

?>

