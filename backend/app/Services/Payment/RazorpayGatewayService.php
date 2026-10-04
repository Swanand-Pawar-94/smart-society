<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class RazorpayGatewayService
{
    private ?string $keyId;
    private ?string $keySecret;
    private ?string $webhookSecret;

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key_id') ?: env('RAZORPAY_KEY_ID');
        $this->keySecret = config('services.razorpay.key_secret') ?: env('RAZORPAY_KEY_SECRET');
        $this->webhookSecret = config('services.razorpay.webhook_secret') ?: env('RAZORPAY_WEBHOOK_SECRET');
    }

    public function getKeyId(): string
    {
        return $this->keyId ?: 'rzp_test_unconfigured';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->keyId)
            && ! empty($this->keySecret)
            && ! str_contains($this->keyId, 'dummy')
            && ! str_contains($this->keySecret, 'dummy');
    }

    /**
     * Create an order in Razorpay (or mock in test/demo mode if unconfigured).
     *
     * @param float $amount In INR
     * @param string $receipt Unique internal receipt/order identifier
     * @param array $notes Additional metadata
     * @return array Order data including id, amount (in paise), currency, receipt, etc.
     */
    public function createOrder(float $amount, string $receipt, array $notes = []): array
    {
        $amountInPaise = (int) round($amount * 100);

        if ($amountInPaise <= 0) {
            throw new RuntimeException('Order amount must be greater than zero.');
        }

        if ($this->isConfigured()) {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountInPaise,
                    'currency' => 'INR',
                    'receipt' => $receipt,
                    'notes' => $notes,
                    'payment_capture' => 1,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Razorpay order creation failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException(
                $response->json('error.description') ?? 'Failed to create Razorpay order.'
            );
        }

        // Test/Demo order generator when using test credentials or offline
        $mockOrderId = 'order_' . strtoupper(Str::random(14));

        return [
            'id' => $mockOrderId,
            'entity' => 'order',
            'amount' => $amountInPaise,
            'amount_paid' => 0,
            'amount_due' => $amountInPaise,
            'currency' => 'INR',
            'receipt' => $receipt,
            'status' => 'created',
            'attempts' => 0,
            'notes' => $notes,
            'created_at' => time(),
        ];
    }

    /**
     * Verifies the Razorpay payment signature returned by checkout.
     * signature = HMAC_SHA256(order_id + "|" + razorpay_payment_id, secret)
     */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (empty($orderId) || empty($paymentId) || empty($signature)) {
            return false;
        }

        $secret = $this->keySecret ?: 'dummy_razorpay_secret_key';
        $payload = $orderId . '|' . $paymentId;
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generates a valid test signature for automated tests.
     */
    public function generateTestSignature(string $orderId, string $paymentId): string
    {
        $secret = $this->keySecret ?: 'dummy_razorpay_secret_key';
        return hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    }

    /**
     * Verifies the Razorpay webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret) || empty($signature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
