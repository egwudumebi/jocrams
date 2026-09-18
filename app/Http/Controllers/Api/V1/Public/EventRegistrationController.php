<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\InitiateEventPaymentRequest;
use App\Http\Requests\Events\RegisterPublicEventRequest;
use App\Models\Event;
use App\Services\Events\EventRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EventRegistrationController extends Controller
{
    public function __construct(private readonly EventRegistrationService $registrationService) {}

    public function register(RegisterPublicEventRequest $request, Event $event): JsonResponse
    {
        abort_if($event->status !== 'published', 404);

        try {
            $registration = $this->registrationService->registerGuest($event, $request->validated());
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

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $gateway = PaymentGateway::from($request->input('gateway'));

        if ($gateway === PaymentGateway::Manual) {
            $result = $this->registrationService->beginGuestBankTransfer(
                event: $event,
                name: $request->input('name') ?? $request->input('email'),
                email: $request->input('email'),
                phone: $request->input('phone'),
                memberType: $request->input('member_type'),
                membershipNumber: $request->input('membership_number'),
            );

            return response()->json([
                'message' => 'Registration reserved. Transfer the fee and submit your receipt for verification.',
                'data' => [
                    'bank_transfer' => true,
                    'fee' => $result['fee'],
                    'currency' => $result['currency'],
                    'related_uuid' => $result['registration']->uuid,
                    'registration' => $result['registration'],
                ],
            ], 201);
        }

        $payment = $this->registrationService->initiateGuestPayment(
            event: $event,
            gateway: $gateway,
            idempotencyKey: $request->input('idempotency_key'),
            name: $request->input('name') ?? $request->input('email'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            memberType: $request->input('member_type'),
            membershipNumber: $request->input('membership_number'),
        );

        return response()->json(['data' => $payment], 201);
    }

    public function verifyPayment(string $reference): JsonResponse
    {
        $payment = $this->registrationService->verifyPayment($reference);

        return response()->json([
            'message' => $payment->isSuccessful() ? 'Payment verified.' : 'Payment verification failed.',
            'data' => $payment->load('payable'),
        ]);
    }
}
