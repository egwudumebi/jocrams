<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\Membership\PublicMemberQueryService;
use App\Support\Membership\PublicMemberPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MemberController extends Controller
{
    public function __construct(private readonly PublicMemberQueryService $publicMemberQuery) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->publicMemberQuery->paginate($filters));
    }

    public function show(Member $member): JsonResponse
    {
        $activeMember = $this->publicMemberQuery->findActive($member->uuid);

        if (! $activeMember) {
            throw new NotFoundHttpException('Member not found.');
        }

        return response()->json(['data' => PublicMemberPresenter::detail($activeMember)]);
    }
}
