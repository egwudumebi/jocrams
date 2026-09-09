<?php

namespace Database\Seeders;

use App\Enums\FeeCatalogCategory;
use App\Enums\FeeCatalogType;
use App\Models\FeeCatalogItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeeCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Associate Membership — Annual Dues',
                'code' => 'membership_associate_annual',
                'category' => FeeCatalogCategory::Membership,
                'fee_type' => FeeCatalogType::AnnualDues,
                'amount' => 25000,
                'description' => 'Annual membership dues for associate members.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Full Membership — Annual Dues',
                'code' => 'membership_full_annual',
                'category' => FeeCatalogCategory::Membership,
                'fee_type' => FeeCatalogType::AnnualDues,
                'amount' => 50000,
                'description' => 'Annual membership dues for full members.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Standard Manuscript Submission Fee',
                'code' => 'journal_submission_standard',
                'category' => FeeCatalogCategory::Journal,
                'fee_type' => FeeCatalogType::Submission,
                'amount' => 15000,
                'description' => 'Default fee charged when a manuscript is submitted.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard Publication Fee',
                'code' => 'journal_publication_standard',
                'category' => FeeCatalogCategory::Journal,
                'fee_type' => FeeCatalogType::Publication,
                'amount' => 25000,
                'description' => 'Fee charged after a manuscript is accepted for publication.',
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
            FeeCatalogItem::query()->firstOrCreate(
                ['code' => $item['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'fee_type' => $item['fee_type'],
                    'amount' => $item['amount'],
                    'currency' => 'NGN',
                    'description' => $item['description'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
