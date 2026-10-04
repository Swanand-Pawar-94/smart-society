<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePayment;
use App\Models\Resident;
use App\Services\Maintenance\PaymentOrderService;
use App\Services\Payment\RazorpayGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly PaymentOrderService $paymentOrderService,
        private readonly RazorpayGatewayService $razorpay,
    ) {}

    /**
     * Creates a Razorpay checkout order.
     * Supports:
     * - Single invoice: { "invoiceId": 42 } or { "maintenance_bill_id": 42 }
     * - Pay all / selected: { "pay_all": true } or { "maintenance_bill_ids": [41, 42] }
     */
    public function createOrder(Request $request): JsonResponse
    {
        $resident = $this->currentResident($request);
        $method = $request->input('payment_method', MaintenancePayment::METHOD_UPI);

        $billId = $request->input('invoiceId') ?? $request->input('maintenance_bill_id');

        if ($billId !== null) {
            $checkoutData = $this->paymentOrderService->createRazorpayOrderForBill(
                $resident,
                (int) $billId,
                $method
            );

            return response()->json([
                'message' => 'Payment order created successfully.',
                'data' => $checkoutData,
            ]);
        }

        $billIds = $request->input('maintenance_bill_ids');
        if (is_array($billIds)) {
            $billIds = array_map('intval', $billIds);
        } else {
            $billIds = null;
        }

        $checkoutData = $this->paymentOrderService->createRazorpayOrderForAll(
            $resident,
            $method,
            $billIds
        );

        return response()->json([
            'message' => 'Payment order created successfully.',
            'data' => $checkoutData,
        ]);
    }

    /**
     * Verifies the Razorpay payment signature and marks invoices as PAID.
     */
    public function verify(Request $request): JsonResponse
    {
        $resident = $this->currentResident($request);

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $result = $this->paymentOrderService->verifyAndReconcileRazorpayPayment(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
            $resident->id
        );

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'success' => $result['success'],
                'payment_id' => $result['payment_id'],
                'order' => $result['order'],
            ],
        ]);
    }

    /**
     * Public Razorpay Webhook Endpoint.
     */
    public function webhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature');
        $rawPayload = $request->getContent();

        if (empty($signature) || ! $this->razorpay->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Razorpay webhook signature verification failed.');
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = $request->input('event');
        $payload = $request->input('payload');

        Log::info('Razorpay webhook received', ['event' => $event]);

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $paymentEntity = $payload['payment']['entity'] ?? [];
            $orderId = $paymentEntity['order_id'] ?? $payload['order']['entity']['id'] ?? null;
            $paymentId = $paymentEntity['id'] ?? null;

            if ($orderId && $paymentId) {
                try {
                    $dummySig = $this->razorpay->generateTestSignature($orderId, $paymentId);
                    $this->paymentOrderService->verifyAndReconcileRazorpayPayment($orderId, $paymentId, $dummySig);
                } catch (\Throwable $e) {
                    Log::error('Webhook reconciliation error: ' . $e->getMessage());
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function currentResident(Request $request): Resident
    {
        $resident = $request->user()?->resident;

        if (! $resident) {
            throw ValidationException::withMessages([
                'resident' => 'Authenticated user is not registered as a resident.',
            ]);
        }

        return $resident;
    }
}
