<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Support\Payments\PaymentReturnResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class PaymentVerifyController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentReturnResolver $returnResolver,
    ) {}

    public function __invoke(Request $request, string $reference): JsonResponse
    {
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();
        $user = $this->resolveAuthenticatedUser($request);

        if ($payment->user_id && (! $user || $user->id !== $payment->user_id)) {
            abort(403, 'You are not authorized to verify this payment.');
        }

        $payment = $this->paymentService->verify($payment);
        $payment->load(['transactions', 'payable.event']);

        return response()->json([
            'message' => $payment->isSuccessful()
                ? 'Payment verified successfully.'
                : 'Payment could not be verified.',
            'data' => $payment,
            'registration' => $this->presentRegistration($payment),
            'redirect_to' => $this->returnResolver->resolve($payment),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function presentRegistration(Payment $payment): ?array
    {
        $registration = $payment->payable;

        if (! $registration instanceof EventRegistration) {
            return null;
        }

        $registration->loadMissing('event');

        return [
            'registration_number' => $registration->registration_number,
            'registrant_name' => $registration->registrant_name,
            'registrant_email' => $registration->registrant_email,
            'status' => $registration->status,
            'event_title' => $registration->event?->title,
            'event_date' => $registration->event?->starts_at?->toDayDateTimeString(),
            'event_location' => $registration->event?->location ?? 'TBA',
        ];
    }

    private function resolveAuthenticatedUser(Request $request): ?\App\Models\User
    {
        if ($request->user()) {
            return $request->user();
        }

        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        return PersonalAccessToken::findToken($token)?->tokenable;
    }
}
