<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentProofStatus;
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
        $query = PaymentProof::query()
            ->with(['user', 'member', 'mediaFile', 'payment', 'reviewer', 'related'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->string('purpose'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('payer_reference', 'like', $search)
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
            });
        }

        $paginator = $query->paginate(20)->through(
            fn (PaymentProof $proof) => $this->proofs->present($proof),
        );

        return response()->json($paginator);
    }

    public function show(PaymentProof $paymentProof): JsonResponse
    {
        return response()->json(['data' => $this->proofs->present($paymentProof)]);
    }

    public function approve(Request $request, PaymentProof $paymentProof): JsonResponse
    {
        $proof = $this->proofs->approve($paymentProof, $request->user());

        return response()->json([
            'message' => 'Payment proof approved and recorded.',
            'data' => $this->proofs->present($proof),
        ]);
    }

    public function reject(Request $request, PaymentProof $paymentProof): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', Rule::enum(PaymentProofStatus::class)],
        ]);

        $proof = $this->proofs->reject($paymentProof, $request->user(), $data['reason']);

        return response()->json([
            'message' => 'Payment proof rejected.',
            'data' => $this->proofs->present($proof),
        ]);
    }

    public function download(PaymentProof $paymentProof): StreamedResponse
    {
        return $this->proofs->download($paymentProof);
    }

    public function pendingCount(): JsonResponse
    {
        return response()->json([
            'data' => [
                'pending' => PaymentProof::query()->where('status', PaymentProofStatus::Pending)->count(),
            ],
        ]);
    }
}
