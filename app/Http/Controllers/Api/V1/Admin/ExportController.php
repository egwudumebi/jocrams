<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function members(Request $request): StreamedResponse|BinaryFileResponse
    {
        $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);

        return $this->exportService->exportMembers(
            $request->only([
                'status', 'membership_tier_id', 'search',
                'expires_before', 'expires_after', 'expiring_within_days',
            ]),
            $request->input('format', 'csv'),
        );
    }

    public function payments(Request $request): StreamedResponse|BinaryFileResponse
    {
        $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);

        return $this->exportService->exportPayments(
            $request->only(['status', 'purpose', 'from', 'to']),
            $request->input('format', 'csv'),
        );
    }

    public function applications(Request $request): StreamedResponse|BinaryFileResponse
    {
        $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);

        return $this->exportService->exportApplications(
            $request->only(['status']),
            $request->input('format', 'csv'),
        );
    }
}
