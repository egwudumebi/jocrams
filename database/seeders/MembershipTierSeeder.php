<?php

namespace Database\Seeders;

use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

class MembershipTierSeeder extends Seeder
{
    public function run(): void
    {
        // Flat SICAMA membership: ₦25,000 (₦10k registration + ₦15k annual dues).
        $registrationFee = (float) config('jocrams.fees.membership_registration', 25000);

        MembershipTier::query()->updateOrCreate(
            ['slug' => 'sicama'],
            [
                'name' => 'SICAMA Member',
                'description' => 'Official SICAMA association membership. Includes ID card, certificate, and welcome letter after payment.',
                'annual_dues' => $registrationFee,
                'currency' => 'NGN',
                'benefits' => [
                    'SICAMA membership ID card',
                    'Membership certificate',
                    'Official welcome letter',
                    'Association directory listing',
                    'Member portal access',
                    'Event privileges',
                ],
                'sort_order' => 0,
                'is_active' => true,
                'min_documents_required' => 0,
                'requires_approval' => false,
            ],
        );

        // Legacy tiers — no longer offered publicly (journal access is separate).
        MembershipTier::query()->updateOrCreate(
            ['slug' => 'associate'],
            [
                'name' => 'Associate Member',
                'description' => 'Legacy tier (retired). Use SICAMA Member.',
                'annual_dues' => $registrationFee,
                'currency' => 'NGN',
                'benefits' => ['Newsletter', 'Event discounts', 'Member portal access'],
                'sort_order' => 1,
                'is_active' => false,
                'min_documents_required' => 1,
                'requires_approval' => true,
            ],
        );

        MembershipTier::query()->updateOrCreate(
            ['slug' => 'full'],
            [
                'name' => 'Full Member',
                'description' => 'Legacy tier (retired). Use SICAMA Member.',
                'annual_dues' => $registrationFee,
                'currency' => 'NGN',
                'benefits' => ['Newsletter', 'Event discounts', 'Digital library', 'Voting rights', 'Member portal access'],
                'sort_order' => 2,
                'is_active' => false,
                'min_documents_required' => 2,
                'requires_approval' => true,
            ],
        );
    }
}
