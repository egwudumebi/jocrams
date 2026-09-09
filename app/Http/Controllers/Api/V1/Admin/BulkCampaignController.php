<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulkCampaign;
use App\Services\Communications\AudienceResolverService;
use App\Services\Communications\BulkMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BulkCampaignController extends Controller
{
    public function __construct(
        private readonly BulkMessagingService $bulkMessaging,
        private readonly AudienceResolverService $audienceResolver,
    ) {}

    public function index(): JsonResponse
    {
        $campaigns = BulkCampaign::query()
            ->with('creator')
            ->latest()
            ->paginate(20);

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience_filter' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $campaign = $this->bulkMessaging->createDraft($request->user(), $data);

        return response()->json(['data' => $campaign], 201);
    }

    public function show(BulkCampaign $campaign): JsonResponse
    {
        return response()->json([
            'data' => $campaign->load(['creator', 'recipients.user']),
        ]);
    }

    public function previewAudience(Request $request): JsonResponse
    {
        $request->validate(['audience_filter' => ['required', 'array']]);

        return response()->json([
            'count' => $this->audienceResolver->count($request->input('audience_filter')),
        ]);
    }

    public function schedule(BulkCampaign $campaign): JsonResponse
    {
        $campaign = $this->bulkMessaging->schedule($campaign);

        return response()->json(['data' => $campaign]);
    }

    public function dispatch(BulkCampaign $campaign): JsonResponse
    {
        $campaign = $this->bulkMessaging->dispatchNow($campaign);

        return response()->json(['data' => $campaign]);
    }

    public function cancel(BulkCampaign $campaign): JsonResponse
    {
        $campaign = $this->bulkMessaging->cancel($campaign);

        return response()->json(['data' => $campaign]);
    }
}
