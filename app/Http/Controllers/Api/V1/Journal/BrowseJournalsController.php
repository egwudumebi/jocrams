<?php

namespace App\Http\Controllers\Api\V1\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalDocumentAccessResolver;
use App\Services\Journal\JournalSubmissionRepository;
use App\Services\Journal\JournalSubmissionUrls;
use App\Support\Auth\ProfileImageUrl;
use App\Support\Http\ListPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrowseJournalsController extends Controller
{
    public function __invoke(
        Request $request,
        JournalDocumentAccessResolver $accessResolver,
    ): JsonResponse {
        $access = $accessResolver->contextFromUser($request->user());
        $isGuest = $access['requester_id'] === null;
        $perPage = ListPagination::perPage($request);

        $query = DB::table('journal_submissions')
            ->leftJoin('users as authors', 'journal_submissions.author_id', '=', 'authors.uuid')
            ->leftJoin('user_profiles as author_profiles', 'authors.uuid', '=', 'author_profiles.user_id')
            ->select(
                'journal_submissions.*',
                'authors.name as author_user_name',
                'authors.email as author_user_email',
                'author_profiles.profile_image_path as author_profile_image_path',
            )
            ->where('journal_submissions.status', 'approved')
            ->orderByDesc('journal_submissions.created_at');

        if ($isGuest) {
            $query->where('journal_submissions.visibility', 'all');
        }

        $paginator = $query->paginate($perPage)->through(function ($model) use ($access, $accessResolver): array {
            $visibility = (string) ($model->visibility ?? 'all');
            $canAccess = $accessResolver->canAccessDocument(
                authorId: (string) $model->author_id,
                status: (string) $model->status,
                visibility: $visibility,
                requesterId: $access['requester_id'],
                canManageJournal: $access['can_manage_journal'],
                isMember: $access['is_member'],
            );

            return [
                'id' => (string) $model->uuid,
                'slug' => $model->slug ? (string) $model->slug : null,
                'author_id' => (string) $model->author_id,
                'author_user_name' => $model->author_user_name ? (string) $model->author_user_name : null,
                'author_user_email' => $model->author_user_email ? (string) $model->author_user_email : null,
                'author_profile_image' => ProfileImageUrl::forUser(
                    (string) $model->author_id,
                    $model->author_profile_image_path ? (string) $model->author_profile_image_path : null,
                ),
                'title' => (string) $model->title,
                'author_name' => (string) $model->author_name,
                'author_email' => (string) $model->author_email,
                'abstract' => (string) $model->abstract,
                'category' => (string) $model->category,
                'keywords' => $model->keywords ? (string) $model->keywords : null,
                'mins_read' => $model->mins_read ? (int) $model->mins_read : null,
                'references' => $model->references ? (array) json_decode((string) $model->references, true) : null,
                'status' => (string) $model->status,
                'visibility' => $visibility,
                'can_access' => $canAccess,
                'document_url' => $canAccess
                    ? JournalSubmissionUrls::document((string) $model->uuid, $model->slug ? (string) $model->slug : null)
                    : null,
                'date_submitted' => $model->created_at,
                'date_reviewed' => $model->reviewed_at,
            ];
        });

        return ListPagination::fromPaginator($paginator);
    }
}
