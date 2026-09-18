<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\PaymentPurpose;
use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Services\Payments\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function __construct(private readonly PaymentProofService $proofs) {}

    public function index(Request $request): JsonResponse
    {
        $items = PaymentProof::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->with(['mediaFile', 'payment', 'related'])
            ->get()
            ->map(fn (PaymentProof $proof) => $this->proofs->present($proof));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', Rule::enum(PaymentPurpose::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payer_reference' => ['nullable', 'string', 'max:120'],
            'member_note' => ['nullable', 'string', 'max:1000'],
            'related_uuid' => ['nullable', 'uuid'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);

        $proof = $this->proofs->submit(
            user: $request->user(),
            purpose: PaymentPurpose::from($data['purpose']),
            amount: (float) $data['amount'],
            receipt: $data['receipt'],
            payerReference: $data['payer_reference'] ?? null,
            memberNote: $data['member_note'] ?? null,
            relatedUuid: $data['related_uuid'] ?? null,
        );

        return response()->json([
            'message' => 'Payment receipt submitted. An administrator will verify it shortly.',
            'data' => $this->proofs->present($proof),
        ], 201);
    }

    public function download(Request $request, PaymentProof $paymentProof): StreamedResponse
    {
        abort_unless((int) $paymentProof->user_id === (int) $request->user()->id, 403);

        return $this->proofs->download($paymentProof);
    }
}
