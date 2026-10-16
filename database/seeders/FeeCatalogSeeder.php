<?php

namespace Database\Seeders;

use App\Enums\FeeCatalogCategory;
use App\Enums\FeeCatalogType;
use App\Models\FeeCatalogItem;
use Illuminate\Database\Seeder;

class FeeCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $membershipFee = (float) config('jocrams.fees.membership_registration', 25000);

        // Retire legacy Associate/Full catalog rows (including soft-deleted).
        FeeCatalogItem::withTrashed()
            ->whereIn('code', ['membership_associate_annual', 'membership_full_annual'])
            ->update(['is_active' => false]);

        $items = [
            [
                'name' => 'SICAMA Membership — Registration & Annual Dues',
                'code' => 'membership_sicama_annual',
                'category' => FeeCatalogCategory::Membership,
                'fee_type' => FeeCatalogType::AnnualDues,
                'amount' => $membershipFee,
                'description' => 'Flat SICAMA membership fee (₦10,000 registration + ₦15,000 annual dues).',
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard Manuscript Submission Fee',
                'code' => 'journal_submission_standard',
                'category' => FeeCatalogCategory::Journal,
                'fee_type' => FeeCatalogType::Submission,
                'amount' => (float) config('jocrams.fees.manuscript_review', 10000),
                'description' => 'Manuscript review fee from JOCRAMS Author Guidelines.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard Publication Fee',
                'code' => 'journal_publication_standard',
                'category' => FeeCatalogCategory::Journal,
                'fee_type' => FeeCatalogType::Publication,
                'amount' => (float) config('jocrams.fees.publication', 30000),
                'description' => 'Publication fee from JOCRAMS Author Guidelines.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Member Event Registration',
                'code' => 'event_member_registration',
                'category' => FeeCatalogCategory::Event,
                'fee_type' => FeeCatalogType::EventRegistration,
                'amount' => 5000,
                'description' => 'Default registration fee for active members attending events.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Non-Member Event Registration',
                'code' => 'event_non_member_registration',
                'category' => FeeCatalogCategory::Event,
                'fee_type' => FeeCatalogType::EventRegistration,
                'amount' => 10000,
                'description' => 'Default registration fee for non-members attending events.',
                'sort_order' => 2,
            ],
        ];

        foreach ($items as $item) {
            $row = FeeCatalogItem::withTrashed()->firstOrNew(['code' => $item['code']]);
            if ($row->trashed()) {
                $row->restore();
            }
            $row->fill([
                'name' => $item['name'],
                'category' => $item['category'],
                'fee_type' => $item['fee_type'],
                'amount' => $item['amount'],
                'currency' => 'NGN',
                'description' => $item['description'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ])->save();
        }
    }
}
