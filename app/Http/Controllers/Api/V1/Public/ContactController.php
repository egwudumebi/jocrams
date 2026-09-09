<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Services\Support\SupportInboxService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(private readonly SupportInboxService $supportInboxService) {}

    public function store(StoreSupportMessageRequest $request): JsonResponse
    {
        $inquiry = $this->supportInboxService->create($request->validated());

        return response()->json([
            'message' => 'Message received.',
            'message_id' => $inquiry->uuid,
        ], 201);
    }
}
