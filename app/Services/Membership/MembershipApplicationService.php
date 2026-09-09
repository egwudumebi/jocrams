<?php

namespace App\Services\Membership;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalStatus;
use App\Enums\MemberStatus;
use App\Models\Approval;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipApplicationDocument;
use App\Models\MembershipTier;
use App\Models\User;
use App\Notifications\MembershipApprovedNotification;
use App\Notifications\MembershipRejectedNotification;
use App\Notifications\MembershipSubmittedNotification;
use App\Services\Credentials\CredentialGenerationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipApplicationService
{
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly CredentialGenerationService $credentialService,
    ) {}

    public function createApplication(User $user, MembershipTier $tier, array $formData): MembershipApplication
    {
        return MembershipApplication::query()->create([
            'user_id' => $user->id,
            'membership_tier_id' => $tier->id,
            'status' => ApplicationStatus::Draft,
            'form_data' => $formData,
            'applicant_email' => $user->email,
            'applicant_name' => $user->name,
            'applicant_phone' => $user->phone,
        ]);
    }

    public function attachDocument(
        MembershipApplication $application,
        UploadedFile $file,
        string $documentType,
        User $user,
    ): MembershipApplicationDocument {
        if (! in_array($application->status, [ApplicationStatus::Draft, ApplicationStatus::Submitted], true)) {
            throw ValidationException::withMessages([
                'application' => ['Documents cannot be added to this application.'],
            ]);
        }

        $mediaFile = $this->mediaUploadService->store(
            file: $file,
            collection: 'application_documents',
            uploadedBy: $user,
        );

        return MembershipApplicationDocument::query()->create([
            'membership_application_id' => $application->id,
            'media_file_id' => $mediaFile->id,
            'document_type' => $documentType,
        ]);
    }

    public function submit(MembershipApplication $application): MembershipApplication
    {
        if ($application->status !== ApplicationStatus::Draft) {
            throw ValidationException::withMessages([
                'application' => ['Only draft applications can be submitted.'],
            ]);
        }

        $tier = $application->tier;
        $documentCount = $application->documents()->count();

        if ($documentCount < $tier->min_documents_required) {
            throw ValidationException::withMessages([
                'documents' => ["At least {$tier->min_documents_required} document(s) are required."],
            ]);
        }

        return DB::transaction(function () use ($application, $tier) {
            $application->update([
                'status' => $tier->requires_approval
                    ? ApplicationStatus::UnderReview
                    : ApplicationStatus::Approved,
                'submitted_at' => now(),
            ]);

            if ($tier->requires_approval) {
                Approval::query()->create([
                    'approvable_type' => MembershipApplication::class,
                    'approvable_id' => $application->id,
                    'type' => 'membership_application',
                    'submitted_by' => $application->user_id,
                    'status' => ApprovalStatus::Pending,
                ]);

                if ($application->user) {
                    $application->user->notify(new MembershipSubmittedNotification($application));
                }
            } else {
                $this->activateFromApplication($application, null);
            }

            return $application->fresh(['documents', 'tier', 'approval']);
        });
    }

    public function approve(MembershipApplication $application, User $reviewer, ?string $notes = null): Member
    {
        if (! in_array($application->status, [ApplicationStatus::Submitted, ApplicationStatus::UnderReview], true)) {
            throw ValidationException::withMessages([
                'application' => ['This application cannot be approved.'],
            ]);
        }

        return DB::transaction(function () use ($application, $reviewer, $notes) {
            $application->update([
                'status' => ApplicationStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'notes' => $notes,
            ]);

            $application->approval?->update([
                'status' => ApprovalStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'notes' => $notes,
            ]);

            $member = $this->activateFromApplication($application, $reviewer);

            if ($application->user) {
                $application->user->notify(new MembershipApprovedNotification($member));
            }

            return $member;
        });
    }

    public function reject(MembershipApplication $application, User $reviewer, string $reason): MembershipApplication
    {
        if (! in_array($application->status, [ApplicationStatus::Submitted, ApplicationStatus::UnderReview], true)) {
            throw ValidationException::withMessages([
                'application' => ['This application cannot be rejected.'],
            ]);
        }

        return DB::transaction(function () use ($application, $reviewer, $reason) {
            $application->update([
                'status' => ApplicationStatus::Rejected,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $reason,
            ]);

            $application->approval?->update([
                'status' => ApprovalStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            if ($application->user) {
                $application->user->notify(new MembershipRejectedNotification($application, $reason));
            }

            return $application->fresh();
        });
    }

    private function activateFromApplication(MembershipApplication $application, ?User $approver): Member
    {
        $user = $application->user ?? User::query()->where('email', $application->applicant_email)->firstOrFail();

        $member = Member::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'membership_tier_id' => $application->membership_tier_id,
                'membership_number' => $this->generateMembershipNumber(),
                'status' => MemberStatus::Active,
                'joined_at' => now(),
                'expires_at' => now()->addYear(),
                'approved_at' => now(),
                'approved_by' => $approver?->id,
            ],
        );

        $this->credentialService->issueMembershipCard($member);

        return $member->fresh(['tier', 'user', 'credentials']);
    }

    private function generateMembershipNumber(): string
    {
        do {
            $number = 'MEM-'.strtoupper(Str::random(8));
        } while (Member::query()->where('membership_number', $number)->exists());

        return $number;
    }
}
