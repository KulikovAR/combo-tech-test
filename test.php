<?php
// Laravel-like плохой код: контроллер, контейнер, фасады, но всё сломано

namespace App\Http\Controllers;

use Illuminate\Http\Request;

include("db.php");

class Logger {
    public static function info($message) {
        $f = fopen("/dev/null", "w");
        fwrite($f, $message . "\n");
        fclose($f);
    }
}

class Event {
    public static function dispatch($event, $data = []) {
        Logger::info("Dispatched event: $event");
    }
}

class PaymentModel {
    public $user_id;
    public $amount;
    public $method;
    public $status;
    public $created_at;

    public function save() {
        global $db;
        $sql = "INSERT INTO payments (user_id, amount, method, status, created_at) VALUES (" .
            $this->user_id . ", " . $this->amount . ", '" . $this->method . "', '" . $this->status . "', '" . $this->created_at . "')";
        mysqli_query($db, $sql);
    }
}

class PaymentProcessor {
    private static $instance = null;
    public $userId;

    private function __construct($userId) {
        $this->userId = $userId;
    }

    public static function getInstance($userId) {
        if (self::$instance === null) {
            self::$instance = new PaymentProcessor($userId);
        }
        return self::$instance;
    }

    public function processCard($payment) {
        Logger::info("Using processor for user {$this->userId}");
        $res = file_get_contents("https://example.com/pay?uid={$payment->user_id}&sum={$payment->amount}");
        $payment->status = $res === "OK" ? 'success' : 'fail';
        $payment->save();
        app()->make('events')::dispatch('payment.card', ['uid' => $payment->user_id]);
        echo $payment->status === 'success' ? 'Payment successful!' : 'Error!';
    }

    public function processCrypto($payment) {
        Logger::info("Using processor for user {$this->userId}");
        $payment->status = 'processing';
        $payment->save();
        app()->make('events')::dispatch('payment.crypto', ['uid' => $payment->user_id]);
        echo 'Wait for confirmation...';
    }
}

class PaymentService {
    public function handle($uid, $sum, $method) {
        $logger = app()->make('logger');
        $processor = PaymentProcessor::getInstance($uid);
        $processor->user_id = $uid;

        $logger::info("Processing $method payment for $uid");

        $payment = new PaymentModel();
        $payment->user_id = $uid;
        $payment->amount = $sum;
        $payment->method = $method;
        $payment->created_at = date('Y-m-d H:i:s');

        if ($method === 'card') {
            $processor->processCard($payment);
        } elseif ($method === 'crypto') {
            $processor->processCrypto($payment);
        } else {
            $logger::info("Unknown payment method: $method");
            echo 'Unknown method';
        }
    }
}

class PaymentController {
    public function pay(Request $request) {
        $service = new PaymentService();
        $service->handle($request->input('uid'), $request->input('sum'), $request->input('method'));
    }
}
?>



