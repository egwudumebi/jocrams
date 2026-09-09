<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InitiatePaymentRequest;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentReceiptPdfService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentReceiptPdfService $receiptPdfService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }

    public function initiateDues(InitiatePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->initiateDuesPayment(
            $request->user(),
            $request->input('idempotency_key'),
            PaymentGateway::from($request->input('gateway')),
        );

        return response()->json(['data' => $payment], 201);
    }

    public function initiateDonation(InitiatePaymentRequest $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'donor_name' => ['required', 'string'],
            'donor_email' => ['required', 'email'],
        ]);

        $payment = $this->paymentService->initiateDonation(
            user: $request->user(),
            amount: (float) $request->input('amount'),
            idempotencyKey: $request->input('idempotency_key'),
            gateway: PaymentGateway::from($request->input('gateway')),
            donorName: $request->input('donor_name'),
            donorEmail: $request->input('donor_email'),
            isAnonymous: (bool) $request->input('is_anonymous', false),
            message: $request->input('message'),
        );

        return response()->json(['data' => $payment], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        abort_if(auth()->id() !== $payment->user_id, 403);

        return response()->json(['data' => $payment->load('transactions')]);
    }

    public function verifyPayment(string $reference): JsonResponse
    {
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();

        abort_if($payment->user_id !== auth()->id(), 403);

        $payment = $this->paymentService->verify($payment);

        return response()->json([
            'message' => $payment->isSuccessful()
                ? 'Payment verified successfully.'
                : 'Payment could not be verified.',
            'data' => $payment->load('transactions'),
        ]);
    }

    public function receipt(Payment $payment, Request $request): Response
    {
        abort_if($payment->user_id !== $request->user()->id, 403);
        abort_if($payment->status !== PaymentStatus::Successful, 404, 'Receipt is only available for successful payments.');

        return $this->receiptPdfService->downloadResponse($payment);
    }
}
