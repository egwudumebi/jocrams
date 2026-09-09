<?php

namespace App\Services\Admin;

use App\Exports\GenericExport;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function __construct(private readonly MemberQueryService $memberQuery) {}

    /** @param array<string, mixed> $filters */
    public function exportMembers(array $filters, string $format = 'csv'): StreamedResponse|BinaryFileResponse
    {
        $rows = $this->memberQuery->filter($filters)->get()->map(fn (Member $member) => [
            'membership_number' => $member->membership_number,
            'name' => $member->user->name,
            'email' => $member->user->email,
            'phone' => $member->user->phone,
            'tier' => $member->tier->name,
            'status' => $member->status->value,
            'joined_at' => $member->joined_at?->toDateString(),
            'expires_at' => $member->expires_at?->toDateString(),
        ]);

        return $this->download(
            rows: $rows,
            headings: ['Membership Number', 'Name', 'Email', 'Phone', 'Tier', 'Status', 'Joined At', 'Expires At'],
            filename: 'members-'.now()->format('Y-m-d'),
            format: $format,
        );
    }

    /** @param array<string, mixed> $filters */
    public function exportPayments(array $filters, string $format = 'csv'): StreamedResponse|BinaryFileResponse
    {
        $query = Payment::query()->with(['user', 'member'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $rows = $query->get()->map(fn (Payment $payment) => [
            'reference' => $payment->reference,
            'user' => $payment->user?->email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'purpose' => $payment->purpose->value,
            'gateway' => $payment->gateway->value,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
        ]);

        return $this->download(
            rows: $rows,
            headings: ['Reference', 'User', 'Amount', 'Currency', 'Status', 'Purpose', 'Gateway', 'Paid At'],
            filename: 'payments-'.now()->format('Y-m-d'),
            format: $format,
        );
    }

    /** @param array<string, mixed> $filters */
    public function exportApplications(array $filters, string $format = 'csv'): StreamedResponse|BinaryFileResponse
    {
        $query = MembershipApplication::query()->with(['tier', 'user'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rows = $query->get()->map(fn (MembershipApplication $app) => [
            'uuid' => $app->uuid,
            'applicant_name' => $app->applicant_name,
            'applicant_email' => $app->applicant_email,
            'tier' => $app->tier->name,
            'status' => $app->status->value,
            'submitted_at' => $app->submitted_at?->toDateTimeString(),
        ]);

        return $this->download(
            rows: $rows,
            headings: ['UUID', 'Applicant', 'Email', 'Tier', 'Status', 'Submitted At'],
            filename: 'applications-'.now()->format('Y-m-d'),
            format: $format,
        );
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    /** @param list<string> $headings */
    private function download(Collection $rows, array $headings, string $filename, string $format): StreamedResponse|BinaryFileResponse
    {
        if ($format === 'xlsx') {
            return Excel::download(
                new GenericExport($rows, $headings),
                "{$filename}.xlsx",
            );
        }

        return response()->streamDownload(function () use ($rows, $headings): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv']);
    }
}
