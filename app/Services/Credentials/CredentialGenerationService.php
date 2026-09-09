<?php

namespace App\Services\Credentials;

use App\Enums\CredentialType;
use App\Enums\Visibility;
use App\Models\DigitalCredential;
use App\Models\MediaFile;
use App\Models\Member;
use App\Support\Auth\ProfileImageUrl;
use App\Support\Settings\SiteBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CredentialGenerationService
{
    public function issueMembershipCard(Member $member): DigitalCredential
    {
        return $this->issue($member, CredentialType::MembershipCard, 'Membership Card', 'membership_card');
    }

    public function issueCertificate(Member $member, string $title, string $templateKey = 'certificate'): DigitalCredential
    {
        return $this->issue($member, CredentialType::Certificate, $title, $templateKey);
    }

    public function issue(Member $member, CredentialType $type, string $title, string $templateKey): DigitalCredential
    {
        $token = Str::random(64);
        $verifyUrl = url("/api/v1/public/verify/{$token}");
        $publicVerifyUrl = url("/verify/{$token}");

        [$qrImage, $qrMime] = $this->qrCodeDataUri($publicVerifyUrl);

        $pdf = Pdf::loadView('credentials.'.$templateKey, $this->viewData(
            member: $member->load('user.profile', 'tier'),
            title: $title,
            verifyUrl: $verifyUrl,
            publicVerifyUrl: $publicVerifyUrl,
            qrImage: $qrImage,
            qrMime: $qrMime,
            issuedAt: now(),
            expiresAt: $member->expires_at,
        ));

        $filename = Str::uuid().'.pdf';
        $path = "credentials/{$member->uuid}/{$filename}";
        $disk = config('downloads.private_disk', 'local');

        Storage::disk($disk)->put($path, $pdf->output());

        $mediaFile = MediaFile::query()->create([
            'uploaded_by' => $member->user_id,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'original_filename' => "{$title}.pdf",
            'mime_type' => 'application/pdf',
            'size' => Storage::disk($disk)->size($path),
            'collection' => 'credentials',
            'visibility' => Visibility::Private,
            'mediable_type' => Member::class,
            'mediable_id' => $member->id,
        ]);

        return DigitalCredential::query()->create([
            'member_id' => $member->id,
            'type' => $type,
            'title' => $title,
            'template_key' => $templateKey,
            'verification_token' => $token,
            'file_media_id' => $mediaFile->id,
            'issued_at' => now(),
            'expires_at' => $member->expires_at,
            'metadata' => ['verify_url' => $verifyUrl],
        ]);
    }

    public function renderPdf(DigitalCredential $credential): \Barryvdh\DomPDF\PDF
    {
        $credential->loadMissing(['member.user.profile', 'member.tier']);
        $member = $credential->member;
        $publicVerifyUrl = url("/verify/{$credential->verification_token}");

        [$qrImage, $qrMime] = $this->qrCodeDataUri($publicVerifyUrl);

        return Pdf::loadView('credentials.'.$credential->template_key, $this->viewData(
            member: $member,
            title: $credential->title,
            verifyUrl: $credential->metadata['verify_url'] ?? url("/api/v1/public/verify/{$credential->verification_token}"),
            publicVerifyUrl: $publicVerifyUrl,
            qrImage: $qrImage,
            qrMime: $qrMime,
            issuedAt: $credential->issued_at ?? now(),
            expiresAt: $credential->expires_at ?? $member->expires_at,
        ));
    }

    public function downloadResponse(DigitalCredential $credential): \Illuminate\Http\Response
    {
        $filename = Str::slug($credential->title).'.pdf';

        return $this->renderPdf($credential)->download($filename);
    }

    public function refreshStoredFile(DigitalCredential $credential): DigitalCredential
    {
        $credential->loadMissing(['member.user.profile', 'member.tier', 'file']);
        $pdfOutput = $this->renderPdf($credential)->output();

        $media = $credential->file;

        if ($media) {
            Storage::disk($media->disk)->put($media->path, $pdfOutput);
            $media->update(['size' => strlen($pdfOutput)]);

            return $credential->fresh(['file', 'member.tier']);
        }

        return $credential;
    }

    public function verify(string $token, ?string $ip = null, ?string $userAgent = null): array
    {
        $credential = DigitalCredential::query()
            ->where('verification_token', $token)
            ->with(['member.user', 'member.tier'])
            ->first();

        if (! $credential) {
            return ['valid' => false, 'result' => 'not_found', 'credential' => null];
        }

        $valid = $credential->isValid() && $credential->member->isActive();
        $result = $valid ? 'valid' : ($credential->revoked_at ? 'revoked' : 'expired');

        $credential->verifications()->create([
            'result' => $result,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'verified_at' => now(),
        ]);

        return [
            'valid' => $valid,
            'result' => $result,
            'credential' => $credential,
        ];
    }

    /** @return array<string, mixed> */
    private function viewData(
        Member $member,
        string $title,
        string $verifyUrl,
        string $publicVerifyUrl,
        string $qrImage,
        string $qrMime,
        \DateTimeInterface $issuedAt,
        ?\DateTimeInterface $expiresAt,
    ): array {
        return [
            'member' => $member,
            'title' => $title,
            'verifyUrl' => $verifyUrl,
            'publicVerifyUrl' => $publicVerifyUrl,
            'qrImage' => $qrImage,
            'qrMime' => $qrMime,
            'siteName' => SiteBranding::siteName(),
            'logoDataUri' => SiteBranding::logoDataUri(),
            'profileImageDataUri' => ProfileImageUrl::dataUriForUser($member->user),
            'issuedAt' => $issuedAt,
            'expiresAt' => $expiresAt,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function qrCodeDataUri(string $url): array
    {
        if (extension_loaded('imagick')) {
            return [
                base64_encode(QrCode::format('png')->size(160)->margin(1)->generate($url)),
                'image/png',
            ];
        }

        return [
            base64_encode(QrCode::format('svg')->size(160)->margin(1)->generate($url)),
            'image/svg+xml',
        ];
    }
}
