<?php

use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendEmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\UpdateProfileController;
use App\Http\Controllers\Api\V1\Auth\DeleteProfileImageController;
use App\Http\Controllers\Api\V1\Auth\UploadProfileImageController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\ApprovalController as AdminApprovalController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\BatchController as AdminBatchController;
use App\Http\Controllers\Api\V1\Admin\BulkCampaignController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\ContentOverviewController;
use App\Http\Controllers\Api\V1\Admin\DownloadController as AdminDownloadController;
use App\Http\Controllers\Api\V1\Admin\EventRegistrationController as AdminEventRegistrationController;
use App\Http\Controllers\Api\V1\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\V1\Admin\ExportController;
use App\Http\Controllers\Api\V1\Admin\MediaLibraryController as AdminMediaLibraryController;
use App\Http\Controllers\Api\V1\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Api\V1\Admin\NewsArticleController as AdminNewsArticleController;
use App\Http\Controllers\Api\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Api\V1\Admin\NotificationTemplateController;
use App\Http\Controllers\Api\V1\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\SettingsLogoController;
use App\Http\Controllers\Api\V1\Admin\SettingsUserRoleController;
use App\Http\Controllers\Api\V1\Admin\SupportMessageController as AdminSupportMessageController;
use App\Http\Controllers\Api\V1\Member\AuthController as MemberAuthController;
use App\Http\Controllers\Api\V1\Member\CredentialController as MemberCredentialController;
use App\Http\Controllers\Api\V1\Member\DashboardController as MemberDashboardController;
use App\Http\Controllers\Api\V1\Member\DownloadController as MemberDownloadController;
use App\Http\Controllers\Api\V1\Member\EventRegistrationController as MemberEventRegistrationController;
use App\Http\Controllers\Api\V1\Member\MembershipApplicationController;
use App\Http\Controllers\Api\V1\Member\NotificationController as MemberNotificationController;
use App\Http\Controllers\Api\V1\Member\PaymentController as MemberPaymentController;
use App\Http\Controllers\Api\V1\Member\PaymentProofController as MemberPaymentProofController;
use App\Http\Controllers\Api\V1\Member\SupportMessageController as MemberSupportMessageController;
use App\Http\Controllers\Api\V1\Public\BranchController;
use App\Http\Controllers\Api\V1\Public\ContactController;
use App\Http\Controllers\Api\V1\Public\CredentialVerificationController;
use App\Http\Controllers\Api\V1\Public\DownloadController as PublicDownloadController;
use App\Http\Controllers\Api\V1\Public\EventBannerController;
use App\Http\Controllers\Api\V1\Public\EventRegistrationController as PublicEventRegistrationController;
use App\Http\Controllers\Api\V1\Public\EventController;
use App\Http\Controllers\Api\V1\Public\MediaCatalogController;
use App\Http\Controllers\Api\V1\Public\MemberController as PublicMemberController;
use App\Http\Controllers\Api\V1\Public\MembershipTierController;
use App\Http\Controllers\Api\V1\Public\NewsController;
use App\Http\Controllers\Api\V1\Public\OpenJournalCallsController;
use App\Http\Controllers\Api\V1\Public\OrgInfoController;
use App\Http\Controllers\Api\V1\Public\OnboardingController;
use App\Http\Controllers\Api\V1\Public\PageController as PublicPageController;
use App\Http\Controllers\Api\V1\Public\PaymentVerifyController;
use App\Http\Controllers\Api\V1\Public\ProfileImageController;
use App\Http\Controllers\Api\V1\Public\FeeCatalogController as PublicFeeCatalogController;
use App\Http\Controllers\Api\V1\Public\ListPublicEditorialBoardController;
use App\Http\Controllers\Api\V1\Public\SiteBannerController;
use App\Http\Controllers\Api\V1\Public\SiteBrandingController;
use App\Http\Controllers\Api\V1\Public\SicamaProfileController;
use App\Http\Controllers\Api\V1\Public\VerifyMembershipNumberController;
use App\Http\Controllers\Api\V1\Public\WebhookController;
use App\Http\Controllers\Api\V1\Journal\AddEditorialCommentController;
use App\Http\Controllers\Api\V1\Journal\AssignReviewerController;
use App\Http\Controllers\Api\V1\Journal\BrowseJournalsController;
use App\Http\Controllers\Api\V1\Journal\CreateReviewerController;
use App\Http\Controllers\Api\V1\Journal\DownloadJournalDocumentController;
use App\Http\Controllers\Api\V1\Journal\ListAllJournalSubmissionsController;
use App\Http\Controllers\Api\V1\Journal\ListEditorialCommentsController;
use App\Http\Controllers\Api\V1\Journal\ListJournalSubmissionsController;
use App\Http\Controllers\Api\V1\Journal\ListMyJournalSubmissionsController;
use App\Http\Controllers\Api\V1\Journal\ListPublicJournalSubmissionsController;
use App\Http\Controllers\Api\V1\Journal\ListReviewerQueueController;
use App\Http\Controllers\Api\V1\Journal\ListReviewersController;
use App\Http\Controllers\Api\V1\Journal\ListSubmissionTimelineController;
use App\Http\Controllers\Api\V1\Journal\RequestRevisionController;
use App\Http\Controllers\Api\V1\Journal\ResubmitRevisionController;
use App\Http\Controllers\Api\V1\Journal\ReviewJournalSubmissionController;
use App\Http\Controllers\Api\V1\Journal\SetJournalVisibilityController;
use App\Http\Controllers\Api\V1\Journal\SubmitJournalController;
use App\Http\Controllers\Api\V1\Journal\RespondToReviewerAssignmentController;
use App\Http\Controllers\Api\V1\Journal\UploadProductionDocumentController;
use App\Http\Controllers\Api\V1\Journal\ListOpenJournalCallsController;
use App\Http\Controllers\Api\V1\Journal\ShowJournalCallController;
use App\Http\Controllers\Api\V1\Journal\InitiateJournalSubmissionPaymentController;
use App\Http\Controllers\Api\V1\Admin\FeeCatalogController;
use App\Http\Controllers\Api\V1\Admin\JournalCallForPapersController;
use App\Http\Controllers\Api\V1\Admin\EditorialBoardController;
use App\Http\Controllers\Api\V1\Admin\JournalSubmissionCategoryController;
use App\Http\Controllers\Api\V1\Admin\JournalVolumeController;
use App\Http\Controllers\Api\V1\Admin\JournalIssueController;
use App\Http\Controllers\Api\V1\Journal\ListJournalSubmissionCategoriesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/password/forgot', ForgotPasswordController::class)->middleware('throttle:auth');
    Route::post('auth/password/reset', ResetPasswordController::class)->middleware('throttle:auth');

    Route::prefix('public')->middleware('throttle:public')->group(function (): void {
        Route::get('site-banner', SiteBannerController::class);
        Route::get('site-branding', SiteBrandingController::class);
        Route::get('org-info', OrgInfoController::class);
        Route::get('sicama', SicamaProfileController::class);
        Route::get('payments/verify/{reference}', PaymentVerifyController::class)
            ->middleware('throttle:verify');
        Route::get('news', [NewsController::class, 'index']);
        Route::get('news/{article:uuid}', [NewsController::class, 'show']);
        Route::get('events', [EventController::class, 'index']);
        Route::get('events/payments/verify/{reference}', [PublicEventRegistrationController::class, 'verifyPayment']);
        Route::post('events/{event:uuid}/register', [PublicEventRegistrationController::class, 'register'])
            ->middleware('throttle:event-guest-register');
        Route::post('events/{event:uuid}/payments/initialize', [PublicEventRegistrationController::class, 'initializePayment'])
            ->middleware('throttle:event-guest-pay');
        Route::get('events/{event:uuid}/banner', EventBannerController::class);
        Route::get('events/{event:uuid}', [EventController::class, 'show']);
        Route::get('branches', [BranchController::class, 'index']);
        Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact');
        Route::get('membership-tiers', [MembershipTierController::class, 'index']);
        Route::get('fee-catalog', PublicFeeCatalogController::class);
        Route::get('journal/calls/open', OpenJournalCallsController::class);
        Route::get('members', [PublicMemberController::class, 'index']);
        Route::get('members/{member:uuid}', [PublicMemberController::class, 'show']);
        Route::post('members/verify-number', VerifyMembershipNumberController::class)
            ->middleware('throttle:verify');
        Route::prefix('onboarding')->middleware('throttle:auth')->group(function (): void {
            Route::post('start', [OnboardingController::class, 'start']);
            Route::post('verify-email', [OnboardingController::class, 'verifyEmail']);
            Route::post('professional-details', [OnboardingController::class, 'saveProfessionalDetails']);
            Route::post('submit', [OnboardingController::class, 'submit']);
        });
        Route::get('verify/{token}', [CredentialVerificationController::class, 'verify'])
            ->middleware('throttle:verify');
        Route::get('downloads', [PublicDownloadController::class, 'index']);
        Route::get('downloads/{download:uuid}', [PublicDownloadController::class, 'show']);
        Route::get('downloads/{download:uuid}/file', [PublicDownloadController::class, 'serve'])
            ->name('downloads.serve');
        Route::get('users/{userUuid}/profile-image', ProfileImageController::class);
        Route::get('pages', [PublicPageController::class, 'index']);
        Route::get('pages/{page:uuid}', [PublicPageController::class, 'show']);
        Route::get('media-catalog', [MediaCatalogController::class, 'index']);
        Route::get('media-catalog/{mediaFile:uuid}', [MediaCatalogController::class, 'show']);
        Route::get('media-catalog/{mediaFile:uuid}/view', [MediaCatalogController::class, 'view']);
        Route::get('media-catalog/{mediaFile:uuid}/file', [MediaCatalogController::class, 'file']);
    });

    Route::prefix('webhooks')->middleware('throttle:webhooks')->group(function (): void {
        Route::post('paystack', [WebhookController::class, 'paystack']);
        Route::post('flutterwave', [WebhookController::class, 'flutterwave']);
    });

    Route::prefix('member')->group(function (): void {
        Route::post('auth/register', [MemberAuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('auth/login', [MemberAuthController::class, 'login'])->middleware('throttle:auth');

        Route::middleware(['auth:sanctum', 'throttle:member'])->group(function (): void {
            Route::post('auth/logout', [MemberAuthController::class, 'logout']);
            Route::get('auth/me', [MemberAuthController::class, 'me']);
            Route::post('auth/password/change', ChangePasswordController::class)
                ->middleware('throttle:password-change');
            Route::put('auth/profile', UpdateProfileController::class);
            Route::post('auth/profile/image', UploadProfileImageController::class)
                ->middleware('throttle:uploads');
            Route::delete('auth/profile/image', DeleteProfileImageController::class)
                ->middleware('throttle:uploads');
            Route::post('auth/email/verification-notification', SendEmailVerificationNotificationController::class)
                ->middleware('throttle:6,1');

            Route::get('support/messages', [MemberSupportMessageController::class, 'index']);
            Route::post('support/messages', [MemberSupportMessageController::class, 'store'])
                ->middleware('throttle:support');
            Route::get('support/messages/{inquiry:uuid}', [MemberSupportMessageController::class, 'show']);
            Route::get('dashboard', MemberDashboardController::class);
            Route::get('notifications/my', [MemberNotificationController::class, 'index']);
            Route::get('notifications/unread-count', [MemberNotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [MemberNotificationController::class, 'markAllRead']);
            Route::post('notifications/{notificationLog}/read', [MemberNotificationController::class, 'markRead']);

            Route::get('payment-proofs', [MemberPaymentProofController::class, 'index']);
            Route::post('payment-proofs', [MemberPaymentProofController::class, 'store'])
                ->middleware('throttle:uploads');
            Route::get('payment-proofs/{paymentProof:uuid}/download', [MemberPaymentProofController::class, 'download']);
        });

        Route::get('auth/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
            ->name('verification.member.verify');

        Route::get('credentials/{credential:uuid}/download', [MemberCredentialController::class, 'download'])
            ->middleware(['signed', 'throttle:public'])
            ->name('credentials.download');

        Route::middleware(['auth:sanctum', 'throttle:member'])->group(function (): void {
            Route::apiResource('applications', MembershipApplicationController::class)
                ->only(['index', 'store', 'show']);
            Route::post('applications/{application}/documents', [MembershipApplicationController::class, 'uploadDocument'])
                ->middleware('throttle:uploads');
            Route::post('applications/{application}/submit', [MembershipApplicationController::class, 'submit']);

            Route::get('events/registrations', [MemberEventRegistrationController::class, 'index']);
            Route::get('events/{event:uuid}/registration', [MemberEventRegistrationController::class, 'show']);
            Route::post('events/{event:uuid}/register', [MemberEventRegistrationController::class, 'register']);
            Route::post('events/{event:uuid}/payments/initialize', [MemberEventRegistrationController::class, 'initializePayment'])
                ->middleware('throttle:payments');
            Route::get('events/payments/verify/{reference}', [MemberEventRegistrationController::class, 'verifyPayment']);

            Route::middleware('member.active')->group(function (): void {
                Route::get('payments', [MemberPaymentController::class, 'index']);
                Route::get('payments/verify/{reference}', [MemberPaymentController::class, 'verifyPayment']);
                Route::post('payments/dues', [MemberPaymentController::class, 'initiateDues'])
                    ->middleware('throttle:payments');
                Route::post('payments/donation', [MemberPaymentController::class, 'initiateDonation'])
                    ->middleware('throttle:payments');
                Route::get('payments/{payment:uuid}', [MemberPaymentController::class, 'show']);
                Route::get('payments/{payment:uuid}/receipt', [MemberPaymentController::class, 'receipt']);

                Route::get('downloads', [MemberDownloadController::class, 'index']);
                Route::get('downloads/{download:uuid}/signed-url', [MemberDownloadController::class, 'signedUrl']);

                Route::get('credentials', [MemberCredentialController::class, 'index']);
                Route::get('membership-card', [MemberCredentialController::class, 'membershipCard']);
                Route::get('credentials/{credential:uuid}', [MemberCredentialController::class, 'show']);
                Route::get('credentials/{credential:uuid}/file', [MemberCredentialController::class, 'downloadAuthenticated']);
                Route::post('credentials/{credential:uuid}/refresh', [MemberCredentialController::class, 'refreshCredential']);
                Route::post('credentials/certificate', [MemberCredentialController::class, 'requestCertificate']);
                Route::post('credentials/membership-card/regenerate', [MemberCredentialController::class, 'regenerateMembershipCard']);
            });
        });
    });

    Route::prefix('admin')->group(function (): void {
        Route::post('auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:auth');

        Route::middleware(['auth:sanctum', 'admin', 'throttle:admin'])->group(function (): void {
            Route::post('auth/logout', [AdminAuthController::class, 'logout']);
            Route::get('auth/me', [AdminAuthController::class, 'me']);
            Route::post('auth/password/change', ChangePasswordController::class)
                ->middleware('throttle:password-change');
            Route::post('auth/email/verification-notification', SendEmailVerificationNotificationController::class)
                ->middleware('throttle:6,1');

            Route::get('dashboard', [DashboardController::class, 'index']);

            Route::get('reports/overview', [AdminReportsController::class, 'overview'])
                ->middleware('permission:reports.view');
            Route::get('reports/recent-activities', [AdminReportsController::class, 'recentActivities'])
                ->middleware('permission:reports.view');

            Route::get('notifications/my', [AdminNotificationController::class, 'index']);
            Route::get('notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllRead']);
            Route::post('notifications/{notificationLog}/read', [AdminNotificationController::class, 'markRead']);

            Route::get('events/member-types', [AdminEventController::class, 'memberTypes'])
                ->middleware('permission:events.manage');
            Route::get('events', [AdminEventController::class, 'index'])
                ->middleware('permission:events.manage');
            Route::post('events', [AdminEventController::class, 'store'])
                ->middleware('permission:events.manage');
            Route::get('events/{event:uuid}', [AdminEventController::class, 'show'])
                ->middleware('permission:events.manage');
            Route::put('events/{event:uuid}', [AdminEventController::class, 'update'])
                ->middleware('permission:events.manage');
            Route::delete('events/{event:uuid}', [AdminEventController::class, 'destroy'])
                ->middleware('permission:events.manage');
            Route::get('events/{event:uuid}/registrations', [AdminEventRegistrationController::class, 'index'])
                ->middleware('permission:events.manage');
            Route::post('events/{event:uuid}/registrations/{registration:uuid}/verify-payment', [AdminEventRegistrationController::class, 'verifyPayment'])
                ->middleware('permission:events.manage');
            Route::get('events/{event:uuid}/registrations/export', [AdminEventRegistrationController::class, 'export'])
                ->middleware(['permission:events.manage', 'throttle:exports']);
            Route::get('downloads', [AdminDownloadController::class, 'index'])
                ->middleware('permission:content.manage');
            Route::post('downloads', [AdminDownloadController::class, 'store'])
                ->middleware(['permission:content.manage', 'throttle:uploads']);
            Route::get('downloads/{download:uuid}', [AdminDownloadController::class, 'show'])
                ->middleware('permission:content.manage');
            Route::put('downloads/{download:uuid}', [AdminDownloadController::class, 'update'])
                ->middleware('permission:content.manage');
            Route::delete('downloads/{download:uuid}', [AdminDownloadController::class, 'destroy'])
                ->middleware('permission:content.manage');

            Route::get('content/overview', ContentOverviewController::class)
                ->middleware('permission:content.manage');
            Route::get('media-library', [AdminMediaLibraryController::class, 'index'])
                ->middleware('permission:content.manage');
            Route::post('media-library', [AdminMediaLibraryController::class, 'store'])
                ->middleware(['permission:content.manage', 'throttle:uploads']);
            Route::delete('media-library/{mediaFile:uuid}', [AdminMediaLibraryController::class, 'destroy'])
                ->middleware('permission:content.manage');
            Route::apiResource('pages', AdminPageController::class)
                ->middleware('permission:content.manage');

            Route::get('support/messages', [AdminSupportMessageController::class, 'index'])
                ->middleware('permission:support.manage');
            Route::post('support/messages/{inquiry:uuid}/respond', [AdminSupportMessageController::class, 'respond'])
                ->middleware('permission:support.manage');
            Route::post('support/messages/{inquiry:uuid}/resolve', [AdminSupportMessageController::class, 'resolve'])
                ->middleware('permission:support.manage');

            Route::get('settings/groups', [AdminSettingsController::class, 'groups'])
                ->middleware('permission:settings.manage');
            Route::post('settings/user-roles', [SettingsUserRoleController::class, 'store'])
                ->middleware('permission:settings.manage');
            Route::post('settings/general/logo', [SettingsLogoController::class, 'store'])
                ->middleware(['permission:settings.manage', 'throttle:uploads']);
            Route::delete('settings/general/logo', [SettingsLogoController::class, 'destroy'])
                ->middleware('permission:settings.manage');
            Route::get('settings/{group}', [AdminSettingsController::class, 'show'])
                ->middleware('permission:settings.manage');
            Route::post('settings/{group}', [AdminSettingsController::class, 'update'])
                ->middleware('permission:settings.manage');

            Route::get('members', [AdminMemberController::class, 'index'])
                ->middleware('permission:members.view');
            Route::post('members', [AdminMemberController::class, 'store'])
                ->middleware('permission:members.approve');
            Route::get('members/{member:uuid}', [AdminMemberController::class, 'show'])
                ->middleware('permission:members.view');
            Route::post('members/{member:uuid}/deactivate', [AdminMemberController::class, 'deactivate'])
                ->middleware('permission:members.approve');
            Route::post('members/{member:uuid}/reactivate', [AdminMemberController::class, 'reactivate'])
                ->middleware('permission:members.approve');

            Route::get('payments', [AdminPaymentController::class, 'index'])
                ->middleware('permission:payments.view');
            Route::get('payments/stats', [AdminPaymentController::class, 'stats'])
                ->middleware('permission:payments.view');
            Route::post('payments', [AdminPaymentController::class, 'store'])
                ->middleware('permission:payments.override');
            Route::get('payment-proofs/pending-count', [AdminPaymentProofController::class, 'pendingCount'])
                ->middleware('permission:payments.view');
            Route::get('payment-proofs', [AdminPaymentProofController::class, 'index'])
                ->middleware('permission:payments.view');
            Route::get('payment-proofs/{paymentProof:uuid}', [AdminPaymentProofController::class, 'show'])
                ->middleware('permission:payments.view');
            Route::get('payment-proofs/{paymentProof:uuid}/download', [AdminPaymentProofController::class, 'download'])
                ->middleware('permission:payments.view');
            Route::post('payment-proofs/{paymentProof:uuid}/approve', [AdminPaymentProofController::class, 'approve'])
                ->middleware('permission:payments.override');
            Route::post('payment-proofs/{paymentProof:uuid}/reject', [AdminPaymentProofController::class, 'reject'])
                ->middleware('permission:payments.override');
            Route::get('payments/{payment:uuid}', [AdminPaymentController::class, 'show'])
                ->middleware('permission:payments.view');
            Route::post('payments/{payment:uuid}/mark-successful', [AdminPaymentController::class, 'markSuccessful'])
                ->middleware('permission:payments.override');
            Route::post('payments/{payment:uuid}/mark-failed', [AdminPaymentController::class, 'markFailed'])
                ->middleware('permission:payments.override');

            Route::get('exports/members', [ExportController::class, 'members'])
                ->middleware(['permission:members.export', 'throttle:exports']);
            Route::get('exports/payments', [ExportController::class, 'payments'])
                ->middleware(['permission:payments.view', 'throttle:exports']);
            Route::get('exports/applications', [ExportController::class, 'applications'])
                ->middleware(['permission:members.export', 'throttle:exports']);

            Route::post('batch/applications/approve', [AdminBatchController::class, 'approveApplications'])
                ->middleware('permission:members.approve');
            Route::post('batch/applications/reject', [AdminBatchController::class, 'rejectApplications'])
                ->middleware('permission:members.approve');
            Route::post('batch/members/status', [AdminBatchController::class, 'updateMemberStatus'])
                ->middleware('permission:members.approve');

            Route::get('approvals', [AdminApprovalController::class, 'index'])
                ->middleware('permission:members.approve');
            Route::post('applications/{application}/approve', [AdminApprovalController::class, 'approveMembership'])
                ->middleware('permission:members.approve');
            Route::post('applications/{application}/reject', [AdminApprovalController::class, 'rejectMembership'])
                ->middleware('permission:members.approve');

            Route::apiResource('news-articles', AdminNewsArticleController::class)
                ->middleware('permission:content.manage');

            Route::apiResource('notification-templates', NotificationTemplateController::class)
                ->middleware('permission:communications.templates.manage');

            Route::get('bulk-campaigns', [BulkCampaignController::class, 'index'])
                ->middleware('permission:communications.broadcast');
            Route::post('bulk-campaigns', [BulkCampaignController::class, 'store'])
                ->middleware('permission:communications.broadcast');
            Route::get('bulk-campaigns/{campaign:uuid}', [BulkCampaignController::class, 'show'])
                ->middleware('permission:communications.broadcast');
            Route::post('bulk-campaigns/preview-audience', [BulkCampaignController::class, 'previewAudience'])
                ->middleware('permission:communications.broadcast');
            Route::post('bulk-campaigns/{campaign:uuid}/schedule', [BulkCampaignController::class, 'schedule'])
                ->middleware('permission:communications.broadcast');
            Route::post('bulk-campaigns/{campaign:uuid}/dispatch', [BulkCampaignController::class, 'dispatch'])
                ->middleware('permission:communications.broadcast');
            Route::post('bulk-campaigns/{campaign:uuid}/cancel', [BulkCampaignController::class, 'cancel'])
                ->middleware('permission:communications.broadcast');

            Route::get('activity-logs', [ActivityLogController::class, 'index'])
                ->middleware('permission:admin.roles.manage');
        });

        Route::get('auth/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['auth:sanctum', 'admin', 'signed', 'throttle:6,1'])
            ->name('verification.admin.verify');
    });

    Route::prefix('journal')->middleware('throttle:journal')->group(function (): void {
        Route::get('submissions/public', ListPublicJournalSubmissionsController::class);
        Route::get('editorial-board', ListPublicEditorialBoardController::class);
        Route::get('submissions', ListAllJournalSubmissionsController::class);
        Route::get('submissions/{submissionId}/document', DownloadJournalDocumentController::class);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('submissions/browse', BrowseJournalsController::class);
            Route::get('categories', ListJournalSubmissionCategoriesController::class)->middleware('journal.ability:journal.submit');
            Route::get('calls/open', ListOpenJournalCallsController::class)->middleware('journal.ability:journal.submit');
            Route::get('calls/{callForPaper:uuid}', ShowJournalCallController::class)->middleware('journal.ability:journal.submit');
            Route::post('submissions', SubmitJournalController::class)
                ->middleware(['journal.ability:journal.submit', 'throttle:uploads']);
            Route::post('submissions/{submissionId}/payments/initialize', InitiateJournalSubmissionPaymentController::class)
                ->middleware(['journal.ability:journal.submit', 'throttle:payments']);
            Route::get('submissions/my', ListMyJournalSubmissionsController::class)->middleware('journal.ability:journal.submit');
            Route::get('submissions/review', ListAllJournalSubmissionsController::class)->middleware('journal.ability:journal.review');
            Route::get('queue', ListReviewerQueueController::class)->middleware('journal.ability:journal.review');
            Route::post('admin/reviewers', CreateReviewerController::class)->middleware('journal.ability:journal.assign');
            Route::get('admin/reviewers', ListReviewersController::class)->middleware('journal.ability:journal.assign');
            Route::get('admin/calls-for-papers', [JournalCallForPapersController::class, 'index'])->middleware('journal.ability:journal.assign');
            Route::post('admin/calls-for-papers', [JournalCallForPapersController::class, 'store'])->middleware('journal.ability:journal.assign');
            Route::get('admin/calls-for-papers/{callForPaper:uuid}', [JournalCallForPapersController::class, 'show'])->middleware('journal.ability:journal.assign');
            Route::put('admin/calls-for-papers/{callForPaper:uuid}', [JournalCallForPapersController::class, 'update'])->middleware('journal.ability:journal.assign');
            Route::delete('admin/calls-for-papers/{callForPaper:uuid}', [JournalCallForPapersController::class, 'destroy'])->middleware('journal.ability:journal.assign');
            Route::get('admin/editorial-board', [EditorialBoardController::class, 'index'])->middleware('journal.ability:journal.assign');
            Route::post('admin/editorial-board', [EditorialBoardController::class, 'store'])->middleware('journal.ability:journal.assign');
            Route::put('admin/editorial-board/{editorialBoardMember:uuid}', [EditorialBoardController::class, 'update'])->middleware('journal.ability:journal.assign');
            Route::delete('admin/editorial-board/{editorialBoardMember:uuid}', [EditorialBoardController::class, 'destroy'])->middleware('journal.ability:journal.assign');
            Route::get('admin/categories', [JournalSubmissionCategoryController::class, 'index'])->middleware('journal.ability:journal.assign');
            Route::post('admin/categories', [JournalSubmissionCategoryController::class, 'store'])->middleware('journal.ability:journal.assign');
            Route::put('admin/categories/{category:uuid}', [JournalSubmissionCategoryController::class, 'update'])->middleware('journal.ability:journal.assign');
            Route::delete('admin/categories/{category:uuid}', [JournalSubmissionCategoryController::class, 'destroy'])->middleware('journal.ability:journal.assign');
            Route::get('admin/fee-catalog/meta', [FeeCatalogController::class, 'meta'])->middleware('journal.ability:journal.assign');
            Route::get('admin/fee-catalog', [FeeCatalogController::class, 'index'])->middleware('journal.ability:journal.assign');
            Route::post('admin/fee-catalog', [FeeCatalogController::class, 'store'])->middleware('journal.ability:journal.assign');
            Route::put('admin/fee-catalog/{feeCatalogItem:uuid}', [FeeCatalogController::class, 'update'])->middleware('journal.ability:journal.assign');
            Route::delete('admin/fee-catalog/{feeCatalogItem:uuid}', [FeeCatalogController::class, 'destroy'])->middleware('journal.ability:journal.assign');
            Route::get('admin/volumes/options', [JournalVolumeController::class, 'options'])->middleware('journal.ability:journal.assign');
            Route::get('admin/volumes', [JournalVolumeController::class, 'index'])->middleware('journal.ability:journal.assign');
            Route::post('admin/volumes', [JournalVolumeController::class, 'store'])->middleware('journal.ability:journal.assign');
            Route::put('admin/volumes/{volume:uuid}', [JournalVolumeController::class, 'update'])->middleware('journal.ability:journal.assign');
            Route::delete('admin/volumes/{volume:uuid}', [JournalVolumeController::class, 'destroy'])->middleware('journal.ability:journal.assign');
            Route::get('admin/issues', [JournalIssueController::class, 'index'])->middleware('journal.ability:journal.assign');
            Route::post('admin/issues', [JournalIssueController::class, 'store'])->middleware('journal.ability:journal.assign');
            Route::put('admin/issues/{issue:uuid}', [JournalIssueController::class, 'update'])->middleware('journal.ability:journal.assign');
            Route::delete('admin/issues/{issue:uuid}', [JournalIssueController::class, 'destroy'])->middleware('journal.ability:journal.assign');
            Route::post('submissions/{submissionId}/review', ReviewJournalSubmissionController::class)->middleware('journal.ability:journal.review');
            Route::post('submissions/{submissionId}/assign', AssignReviewerController::class)->middleware('journal.ability:journal.assign');
            Route::post('submissions/{submissionId}/assignment/respond', RespondToReviewerAssignmentController::class)->middleware('journal.ability:journal.review');
            Route::post('submissions/{submissionId}/production-document', UploadProductionDocumentController::class)->middleware('journal.ability:journal.publish');
            Route::get('submissions/{submissionId}/timeline', ListSubmissionTimelineController::class)->middleware('journal.ability:journal.review');
            Route::get('submissions/{submissionId}/comments', ListEditorialCommentsController::class)->middleware('auth:sanctum');
            Route::post('submissions/{submissionId}/comments', AddEditorialCommentController::class)->middleware('auth:sanctum');
            Route::post('submissions/{submissionId}/request-revision', RequestRevisionController::class)->middleware('journal.ability:journal.review');
            Route::post('submissions/{submissionId}/resubmit', ResubmitRevisionController::class)->middleware('journal.ability:journal.submit');
            Route::put('submissions/{submissionId}/visibility', SetJournalVisibilityController::class)->middleware('journal.ability:journal.assign');
        });
    });
});
