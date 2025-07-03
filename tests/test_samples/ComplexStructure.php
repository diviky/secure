<?php

declare(strict_types=1);

namespace App\Services\Payment;

interface PaymentProcessorInterface
{
    public function processPayment(float $amount, string $currency): bool;
    public function refundPayment(string $transactionId): bool;
}

trait LoggableTrait
{
    private array $logEntries = [];

    protected function log(string $message, string $level = 'info'): void
    {
        $this->logEntries[] = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message
        ];
    }

    public function getLogEntries(): array
    {
        return $this->logEntries;
    }

    protected function clearLogs(): void
    {
        $this->logEntries = [];
    }
}

abstract class BasePaymentProcessor implements PaymentProcessorInterface
{
    use LoggableTrait;

    protected string $apiKey;
    protected bool $sandboxMode;
    protected const MAX_RETRIES = 3;

    public function __construct(string $apiKey, bool $sandbox = false)
    {
        $this->apiKey = $apiKey;
        $this->sandboxMode = $sandbox;
        $this->log('Payment processor initialized');
    }

    abstract protected function makeApiCall(string $endpoint, array $data): array;

    protected function validateAmount(float $amount): bool
    {
        return $amount > 0 && $amount <= 10000;
    }

    protected function validateCurrency(string $currency): bool
    {
        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'CAD'];
        return in_array(strtoupper($currency), $supportedCurrencies);
    }
}

class StripePaymentProcessor extends BasePaymentProcessor
{
    private string $secretKey;
    private int $connectionTimeout;

    public function __construct(string $apiKey, string $secretKey, bool $sandbox = false)
    {
        parent::__construct($apiKey, $sandbox);
        $this->secretKey = $secretKey;
        $this->connectionTimeout = 30;
        $this->log('Stripe processor ready');
    }

    public function processPayment(float $amount, string $currency): bool
    {
        if (!$this->validateAmount($amount) || !$this->validateCurrency($currency)) {
            $this->log('Invalid payment parameters', 'error');
            return false;
        }

        $paymentData = [
            'amount' => $amount * 100, // Convert to cents
            'currency' => strtolower($currency),
            'source' => $this->generatePaymentToken()
        ];

        $response = $this->makeApiCall('/charges', $paymentData);

        if ($response['success']) {
            $this->log("Payment processed: {$response['transaction_id']}", 'success');
            return true;
        }

        $this->log("Payment failed: {$response['error']}", 'error');
        return false;
    }

    public function refundPayment(string $transactionId): bool
    {
        $refundData = [
            'charge' => $transactionId,
            'reason' => 'requested_by_customer'
        ];

        $response = $this->makeApiCall('/refunds', $refundData);

        if ($response['success']) {
            $this->log("Refund processed: {$transactionId}", 'success');
            return true;
        }

        $this->log("Refund failed: {$response['error']}", 'error');
        return false;
    }

    protected function makeApiCall(string $endpoint, array $data): array
    {
        // Simulate API call
        $this->log("Making API call to: {$endpoint}");

        // Mock successful response
        return [
            'success' => true,
            'transaction_id' => 'txn_' . uniqid(),
            'timestamp' => time()
        ];
    }

    private function generatePaymentToken(): string
    {
        return 'tok_' . bin2hex(random_bytes(16));
    }

    public function setTimeout(int $timeout): void
    {
        $this->connectionTimeout = $timeout;
    }

    public function getTimeout(): int
    {
        return $this->connectionTimeout;
    }
}
