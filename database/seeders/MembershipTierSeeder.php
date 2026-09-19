<?php

namespace Database\Seeders;

use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

class MembershipTierSeeder extends Seeder
{
    public function run(): void
    {
        // Client-documented membership registration fee: ₦20,000 (JOCRAMS INFO / flyer).
        $registrationFee = (float) config('jocrams.fees.membership_registration', 20000);

        MembershipTier::query()->updateOrCreate(
            ['slug' => 'associate'],
            [
                'name' => 'Associate Member',
                'description' => 'Entry-level membership. Registration fee aligned with SICAMA/JOCRAMS published rate.',
                'annual_dues' => $registrationFee,
                'currency' => 'NGN',
                'benefits' => ['Newsletter', 'Event discounts', 'Member portal access'],
                'sort_order' => 1,
                'is_active' => true,
                'min_documents_required' => 1,
                'requires_approval' => true,
            ],
        );

        MembershipTier::query()->updateOrCreate(
            ['slug' => 'full'],
            [
                'name' => 'Full Member',
                'description' => 'Full membership with all benefits. Registration fee aligned with SICAMA/JOCRAMS published rate.',
                'annual_dues' => $registrationFee,
                'currency' => 'NGN',
                'benefits' => ['Newsletter', 'Event discounts', 'Digital library', 'Voting rights', 'Member portal access'],
                'sort_order' => 2,
                'is_active' => true,
                'min_documents_required' => 2,
                'requires_approval' => true,
            ],
        );
    }
}
