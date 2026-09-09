<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $query = EventRegistration::query()
            ->with(['user', 'payment', 'latestPayment'])
            ->where('event_id', $event->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $registrations = $query->paginate(50);

        $stats = [
            'total' => EventRegistration::query()->where('event_id', $event->id)->count(),
            'confirmed' => EventRegistration::query()->where('event_id', $event->id)->where('status', 'confirmed')->count(),
            'pending' => EventRegistration::query()->where('event_id', $event->id)->where('status', 'pending')->count(),
            'revenue' => (float) EventRegistration::query()
                ->where('event_registrations.event_id', $event->id)
                ->where('event_registrations.status', 'confirmed')
                ->whereNotNull('event_registrations.payment_id')
                ->join('payments', 'event_registrations.payment_id', '=', 'payments.id')
                ->sum('payments.amount'),
        ];

        return response()->json([
            'event' => $event->only(['uuid', 'title', 'starts_at', 'location']),
            'stats' => $stats,
            'data' => $registrations,
        ]);
    }

    public function verifyPayment(Event $event, EventRegistration $registration): JsonResponse
    {
        abort_if($registration->event_id !== $event->id, 404);

        $registration->loadMissing('latestPayment', 'payment');

        $payment = $registration->payment ?? $registration->latestPayment;

        if (! $payment) {
            return response()->json([
                'message' => 'No payment record is linked to this registration.',
            ], 422);
        }

        $payment = $this->paymentService->verify($payment);
        $registration = $registration->fresh(['payment', 'latestPayment']);

        return response()->json([
            'message' => $payment->isSuccessful()
                ? 'Payment verified and registration confirmed.'
                : 'Payment verification failed.',
            'data' => $registration,
        ]);
    }

    public function export(Event $event): StreamedResponse
    {
        $filename = 'event-registrations-'.$event->uuid.'.csv';

        return response()->streamDownload(function () use ($event): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Member Type', 'Status', 'Amount (NGN)', 'Payment Reference', 'Payment Status', 'Registration Number', 'Registered At']);

            EventRegistration::query()
                ->with(['payment', 'latestPayment'])
                ->where('event_id', $event->id)
                ->orderBy('created_at')
                ->chunk(200, function ($registrations) use ($handle): void {
                    foreach ($registrations as $registration) {
                        $payment = $registration->payment ?? $registration->latestPayment;

                        fputcsv($handle, [
                            $registration->registrant_name,
                            $registration->registrant_email,
                            $registration->registrant_phone,
                            $registration->member_type,
                            $registration->status,
                            $payment?->amount ?? 0,
                            $payment?->reference,
                            $payment?->status?->value ?? $payment?->status,
                            $registration->registration_number,
                            $registration->created_at,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
