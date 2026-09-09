<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\InitiateEventPaymentRequest;
use App\Http\Requests\Events\RegisterEventRequest;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventRegistrationController extends Controller
{
    public function __construct(private readonly EventRegistrationService $registrationService) {}

    public function index(Request $request): JsonResponse
    {
        $registrations = $this->registrationsForUser($request->user())
            ->with('event')
            ->latest()
            ->paginate(20);

        return response()->json($registrations);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        abort_if($event->status !== 'published', 404);

        $registration = $this->registrationsForUser($request->user())
            ->with(['payment', 'latestPayment'])
            ->where('event_id', $event->id)
            ->latest()
            ->first();

        return response()->json(['data' => $registration]);
    }

    public function register(RegisterEventRequest $request, Event $event): JsonResponse
    {
        abort_if($event->status !== 'published', 404);

        try {
            $registration = $this->registrationService->registerMember(
                $request->user(),
                $event,
                $request->validated(),
            );
        } catch (ValidationException $e) {
            if ($e->errors()['payment'] ?? false) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'requires_payment' => true,
                    'fee' => $e->errors()['fee'][0] ?? null,
                    'member_type' => $e->errors()['member_type'][0] ?? null,
                ], 422);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Registered for event.',
            'data' => $registration,
        ], 201);
    }

    public function initializePayment(InitiateEventPaymentRequest $request, Event $event): JsonResponse
    {
        abort_if($event->status !== 'published', 404);

        $payment = $this->registrationService->initiateMemberPayment(
            user: $request->user(),
            event: $event,
            gateway: PaymentGateway::from($request->input('gateway')),
            idempotencyKey: $request->input('idempotency_key'),
            memberType: $request->input('member_type'),
            name: $request->input('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
        );

        return response()->json(['data' => $payment], 201);
    }

    public function verifyPayment(string $reference): JsonResponse
    {
        $payment = $this->registrationService->verifyPayment($reference);

        abort_if($payment->user_id && auth()->id() !== $payment->user_id, 403);

        return response()->json([
            'message' => $payment->isSuccessful() ? 'Payment verified.' : 'Payment verification failed.',
            'data' => $payment->load('payable'),
        ]);
    }

    private function registrationsForUser(User $user): Builder
    {
        return EventRegistration::query()->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhere('registrant_email', $user->email);
        });
    }
}
