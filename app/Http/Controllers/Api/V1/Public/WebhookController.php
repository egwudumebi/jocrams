<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Services\Payments\WebhookReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookReconciliationService $webhookService) {}

    public function paystack(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? 'unknown';

        $log = $this->webhookService->handle(
            PaymentGateway::Paystack,
            $event,
            $payload,
            $request->header('x-paystack-signature'),
            $request->getContent(),
        );

        return response()->json(['status' => $log->status]);
    }

    public function flutterwave(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? 'charge.completed';

        $log = $this->webhookService->handle(
            PaymentGateway::Flutterwave,
            $event,
            $payload,
            $request->header('verif-hash'),
            $request->getContent(),
        );

        return response()->json(['status' => $log->status]);
    }
}
