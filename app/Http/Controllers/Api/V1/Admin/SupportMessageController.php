<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SupportMessageSource;
use App\Enums\SupportMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\RespondSupportMessageRequest;
use App\Models\ContactInquiry;
use App\Services\Support\SupportInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportMessageController extends Controller
{
    public function __construct(private readonly SupportInboxService $supportInboxService) {}

    public function index(Request $request): JsonResponse
    {
        $query = ContactInquiry::query()
            ->with(['branch', 'user', 'assignee'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $stats = [
            'new' => ContactInquiry::query()->where('status', SupportMessageStatus::New)->count(),
            'responded' => ContactInquiry::query()->where('status', SupportMessageStatus::Responded)->count(),
            'resolved' => ContactInquiry::query()->where('status', SupportMessageStatus::Resolved)->count(),
            'total' => ContactInquiry::query()->count(),
        ];

        $paginator = $query->paginate(25);

        return response()->json([
            ...$paginator->toArray(),
            'stats' => $stats,
        ]);
    }

    public function respond(RespondSupportMessageRequest $request, ContactInquiry $inquiry): JsonResponse
    {
        $inquiry = $this->supportInboxService->respond(
            $inquiry,
            $request->user(),
            $request->validated('response'),
        );

        return response()->json([
            'message' => 'Response sent.',
            'data' => $inquiry,
        ]);
    }

    public function resolve(Request $request, ContactInquiry $inquiry): JsonResponse
    {
        $inquiry = $this->supportInboxService->resolve($inquiry, $request->user());

        return response()->json([
            'message' => 'Message resolved.',
            'data' => $inquiry,
        ]);
    }
}
