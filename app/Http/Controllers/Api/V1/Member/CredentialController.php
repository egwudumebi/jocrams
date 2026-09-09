<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\CredentialType;
use App\Http\Controllers\Controller;
use App\Models\DigitalCredential;
use App\Services\Credentials\CredentialGenerationService;
use App\Services\Library\SignedUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class CredentialController extends Controller
{
    public function __construct(
        private readonly CredentialGenerationService $credentialService,
        private readonly SignedUrlService $signedUrlService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $credentials = DigitalCredential::query()
            ->whereHas('member', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('file')
            ->latest('issued_at')
            ->get();

        return response()->json(['data' => $credentials]);
    }

    public function show(DigitalCredential $credential, Request $request): JsonResponse
    {
        abort_if($credential->member->user_id !== $request->user()->id, 403);

        $downloadUrl = null;

        if ($credential->file) {
            $downloadUrl = URL::temporarySignedRoute(
                'credentials.download',
                now()->addMinutes(config('downloads.signed_url_ttl_minutes', 15)),
                ['credential' => $credential->uuid],
            );
        }

        return response()->json([
            'data' => $credential->load('member.tier'),
            'download_url' => $downloadUrl,
            'verify_url' => $credential->metadata['verify_url'] ?? url("/api/v1/public/verify/{$credential->verification_token}"),
        ]);
    }

    public function download(DigitalCredential $credential, Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        abort_if($credential->revoked_at !== null, 403, 'This credential has been revoked.');
        abort_if(! $credential->isValid(), 403, 'This credential is no longer valid.');

        return $this->credentialService->downloadResponse($credential);
    }

    public function downloadAuthenticated(DigitalCredential $credential, Request $request)
    {
        abort_if($credential->member->user_id !== $request->user()->id, 403);
        abort_if($credential->revoked_at !== null, 403, 'This credential has been revoked.');
        abort_if(! $credential->isValid(), 403, 'This credential is no longer valid.');

        return $this->credentialService->downloadResponse($credential);
    }

    public function requestCertificate(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        abort_if(! $member?->isActive(), 403);

        $request->validate(['title' => ['required', 'string', 'max:255']]);

        $credential = $this->credentialService->issueCertificate(
            $member,
            $request->input('title'),
        );

        return response()->json(['data' => $credential->load('file')], 201);
    }

    public function regenerateMembershipCard(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        abort_if(! $member?->isActive(), 403);

        DigitalCredential::query()
            ->where('member_id', $member->id)
            ->where('type', CredentialType::MembershipCard)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revocation_reason' => 'Replaced by regenerated card']);

        $credential = $this->credentialService->issueMembershipCard($member);

        return response()->json(['data' => $credential->load('file')], 201);
    }

    public function refreshCredential(DigitalCredential $credential, Request $request): JsonResponse
    {
        abort_if($credential->member->user_id !== $request->user()->id, 403);
        abort_if($credential->revoked_at !== null, 403, 'This credential has been revoked.');

        $credential = $this->credentialService->refreshStoredFile($credential);

        return response()->json(['data' => $credential->load('file')]);
    }

    public function membershipCard(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        abort_if(! $member?->isActive(), 403);

        $member->load('tier');

        $credential = DigitalCredential::query()
            ->where('member_id', $member->id)
            ->where('type', CredentialType::MembershipCard)
            ->whereNull('revoked_at')
            ->latest('issued_at')
            ->first();

        $downloadUrl = null;

        if ($credential?->file) {
            $downloadUrl = URL::temporarySignedRoute(
                'credentials.download',
                now()->addMinutes(config('downloads.signed_url_ttl_minutes', 15)),
                ['credential' => $credential->uuid],
            );
        }

        return response()->json([
            'membership_number' => $member->membership_number,
            'tier' => $member->tier?->name,
            'status' => $member->status->value,
            'expires_at' => $member->expires_at,
            'verify_url' => $credential?->metadata['verify_url'] ?? null,
            'credential_uuid' => $credential?->uuid,
            'download_url' => $downloadUrl,
        ]);
    }
}
