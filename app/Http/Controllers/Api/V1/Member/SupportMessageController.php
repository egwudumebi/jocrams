<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\SupportMessageSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Models\ContactInquiry;
use App\Services\Support\SupportInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportMessageController extends Controller
{
    public function __construct(private readonly SupportInboxService $supportInboxService) {}

    public function index(Request $request): JsonResponse
    {
        $messages = ContactInquiry::query()
            ->where('user_id', $request->user()->id)
            ->where('source', SupportMessageSource::Member)
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }

    public function store(StoreSupportMessageRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['branch_id']);

        $message = $this->supportInboxService->create(
            $data,
            $request->user(),
            SupportMessageSource::Member,
        );

        return response()->json([
            'message' => 'Support message submitted.',
            'message_id' => $message->uuid,
            'data' => $message,
        ], 201);
    }

    public function show(Request $request, ContactInquiry $inquiry): JsonResponse
    {
        abort_unless(
            $inquiry->user_id === $request->user()->id
            && $inquiry->source === SupportMessageSource::Member,
            404,
        );

        return response()->json(['data' => $inquiry]);
    }
}
