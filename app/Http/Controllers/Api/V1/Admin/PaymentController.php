<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordManualPaymentRequest;
use App\Models\Payment;
use App\Services\Admin\PaymentAdminService;
use App\Services\Admin\PaymentOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentOverrideService $overrideService,
        private readonly PaymentAdminService $paymentAdminService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with(['user', 'member'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->input('purpose'));
        }

        if ($request->filled('gateway')) {
            $query->where('gateway', $request->input('gateway'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->paginate(20));
    }

    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->paymentAdminService->stats()]);
    }

    public function store(RecordManualPaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentAdminService->recordManual(
            $request->validated(),
            $request->user(),
        );

        return response()->json(['data' => $payment], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json(['data' => $payment->load(['transactions', 'user', 'member', 'payable'])]);
    }

    public function markSuccessful(Payment $payment, Request $request): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $payment = $this->overrideService->markSuccessful(
            $payment,
            $request->user(),
            $request->input('reason'),
        );

        return response()->json(['data' => $payment]);
    }

    public function markFailed(Payment $payment, Request $request): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $payment = $this->overrideService->markFailed(
            $payment,
            $request->user(),
            $request->input('reason'),
        );

        return response()->json(['data' => $payment]);
    }
}
