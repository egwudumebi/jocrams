<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\JournalSubmission;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InitiateJournalSubmissionPaymentController extends Controller
{
    public function __invoke(
        string $submissionId,
        Request $request,
        PaymentService $paymentService,
    ): JsonResponse {
        $submission = JournalSubmission::query()
            ->where('uuid', $submissionId)
            ->orWhere('slug', $submissionId)
            ->firstOrFail();

        abort_if($submission->author_id !== $request->user()->uuid, 403);
        abort_if($submission->status->value !== 'payment_pending', 422, 'This submission does not require payment.');

        $request->validate([
            'gateway' => ['required', 'in:paystack,flutterwave'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $payment = $paymentService->initiateJournalSubmissionFee(
            user: $request->user(),
            submission: $submission,
            gateway: PaymentGateway::from($request->input('gateway')),
            idempotencyKey: $request->input('idempotency_key'),
        );

        $submission->update(['submission_payment_id' => $payment->id]);

        return response()->json([
            'data' => $payment,
            'authorization_url' => $payment->authorization_url,
        ], 201);
    }
}
