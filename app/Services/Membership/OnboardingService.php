<?php

namespace App\Services\Membership;

use App\Enums\UserStatus;
use App\Mail\OnboardingOtpMail;
use App\Models\MembershipOnboardingOtp;
use App\Models\MembershipOnboardingSession;
use App\Models\MembershipTier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    private const OTP_TTL_MINUTES = 10;

    public function __construct(
        private readonly MembershipApplicationService $applicationService,
    ) {}

    /** @return array<string, mixed> */
    public function start(array $payload): array
    {
        if (User::query()->where('email', $payload['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account already exists for this email. Please sign in instead.'],
            ]);
        }

        $session = MembershipOnboardingSession::query()->create([
            'id' => (string) Str::uuid(),
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email_verified' => false,
            'state' => 'personal_info_submitted',
            'expires_at' => now()->addHours(24),
        ]);

        $otp = (string) random_int(100000, 999999);

        MembershipOnboardingOtp::query()->create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        Mail::to($session->email)->send(new OnboardingOtpMail(
            $session->first_name,
            $otp,
            self::OTP_TTL_MINUTES,
        ));

        return [
            'session_id' => $session->id,
            'state' => $session->state,
            'otp_expires_in_seconds' => self::OTP_TTL_MINUTES * 60,
            'otp_debug_code' => app()->environment('local', 'testing') ? $otp : null,
        ];
    }

    /** @return array<string, mixed> */
    public function verifyEmail(string $sessionId, string $otp): array
    {
        $session = $this->findActiveSession($sessionId);

        $otpRow = MembershipOnboardingOtp::query()
            ->where('session_id', $sessionId)
            ->whereNull('verified_at')
            ->latest('created_at')
            ->first();

        if (! $otpRow) {
            throw ValidationException::withMessages(['otp' => ['No active OTP exists for this session.']]);
        }

        if ($otpRow->attempts >= 5) {
            throw ValidationException::withMessages(['otp' => ['Too many invalid OTP attempts.']]);
        }

        if (now()->greaterThan($otpRow->expires_at)) {
            throw ValidationException::withMessages(['otp' => ['OTP has expired.']]);
        }

        if (! Hash::check($otp, $otpRow->otp_hash)) {
            $otpRow->increment('attempts');
            throw ValidationException::withMessages(['otp' => ['Invalid OTP.']]);
        }

        $otpRow->update(['verified_at' => now()]);
        $session->update([
            'email_verified' => true,
            'state' => 'email_verified',
        ]);

        return [
            'session_id' => $session->id,
            'state' => 'email_verified',
        ];
    }

    /** @return array<string, mixed> */
    public function saveProfessionalDetails(string $sessionId, array $payload, array $files = []): array
    {
        $session = $this->findActiveSession($sessionId);

        if (! $session->email_verified) {
            throw ValidationException::withMessages(['session_id' => ['Email verification is required first.']]);
        }

        $storedDocuments = $this->storeSupportingDocuments($session->id, $files);

        $session->update([
            'professional_payload' => [
                'membership_tier_id' => (int) $payload['membership_tier_id'],
                'institution' => $payload['institution'],
                'qualification' => $payload['qualification'],
                'years_experience' => (int) $payload['years_experience'],
                'supporting_documents' => $storedDocuments,
            ],
            'state' => 'professional_details_completed',
        ]);

        return [
            'session_id' => $session->id,
            'state' => 'professional_details_completed',
        ];
    }

    /** @return array<string, mixed> */
    public function submit(string $sessionId): array
    {
        $session = $this->findActiveSession($sessionId);

        if (! $session->email_verified) {
            throw ValidationException::withMessages(['session_id' => ['Email verification is required first.']]);
        }

        $professional = $session->professional_payload;
        if (! is_array($professional) || empty($professional['membership_tier_id'])) {
            throw ValidationException::withMessages(['session_id' => ['Professional details are required first.']]);
        }

        $tier = MembershipTier::query()->findOrFail($professional['membership_tier_id']);

        return DB::transaction(function () use ($session, $professional, $tier) {
            [$user, $created] = $this->findOrCreateUser($session);

            $application = $this->applicationService->createApplication($user, $tier, [
                'institution' => $professional['institution'] ?? null,
                'qualification' => $professional['qualification'] ?? null,
                'years_experience' => $professional['years_experience'] ?? null,
                'source' => 'public_onboarding',
            ]);

            foreach ($professional['supporting_documents'] ?? [] as $document) {
                $uploadedFile = $this->toUploadedFile($document['path'], $document['original_name']);
                $this->applicationService->attachDocument(
                    $application,
                    $uploadedFile,
                    $document['document_type'] ?? 'supporting_document',
                    $user,
                );
            }

            $application = $this->applicationService->submit($application->fresh(['documents', 'tier']));

            $session->update([
                'state' => 'application_submitted',
                'submitted_at' => now(),
            ]);

            if ($created) {
                Password::sendResetLink(['email' => $user->email]);
            }

            return [
                'session_id' => $session->id,
                'application_uuid' => $application->uuid,
                'state' => 'application_submitted',
                'password_reset_sent' => $created,
            ];
        });
    }

    private function findActiveSession(string $sessionId): MembershipOnboardingSession
    {
        $session = MembershipOnboardingSession::query()->find($sessionId);

        if (! $session) {
            throw ValidationException::withMessages(['session_id' => ['Invalid onboarding session.']]);
        }

        if ($session->submitted_at !== null) {
            throw ValidationException::withMessages(['session_id' => ['This onboarding session has already been submitted.']]);
        }

        if ($session->isExpired()) {
            throw ValidationException::withMessages(['session_id' => ['This onboarding session has expired.']]);
        }

        return $session;
    }

    /** @return array{0: User, 1: bool} */
    private function findOrCreateUser(MembershipOnboardingSession $session): array
    {
        $existing = User::query()->where('email', $session->email)->first();
        if ($existing) {
            return [$existing, false];
        }

        $user = User::query()->create([
            'name' => $session->fullName(),
            'email' => $session->email,
            'phone' => $session->phone,
            'password' => Str::random(40),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'has_changed_password' => false,
        ]);

        return [$user, true];
    }

    /** @param list<UploadedFile> $files
     * @return list<array{path: string, original_name: string, document_type: string}>
     */
    private function storeSupportingDocuments(string $sessionId, array $files): array
    {
        $stored = [];

        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("onboarding/{$sessionId}", 'local');
            $stored[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'document_type' => $index === 0 ? 'national_id' : 'supporting_document',
            ];
        }

        return $stored;
    }

    /** @return array{path: string, original_name: string} */
    private function toUploadedFile(string $storagePath, string $originalName): UploadedFile
    {
        $absolutePath = Storage::disk('local')->path($storagePath);

        return new UploadedFile(
            $absolutePath,
            $originalName,
            mime_content_type($absolutePath) ?: 'application/octet-stream',
            null,
            true,
        );
    }
}
