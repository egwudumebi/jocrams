<?php

namespace Database\Seeders;

use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

class MembershipTierSeeder extends Seeder
{
    public function run(): void
    {
        MembershipTier::query()->firstOrCreate(
            ['slug' => 'associate'],
            [
                'name' => 'Associate Member',
                'description' => 'Entry-level membership tier.',
                'annual_dues' => 25000,
                'currency' => 'NGN',
                'benefits' => ['Newsletter', 'Event discounts'],
                'sort_order' => 1,
                'is_active' => true,
                'min_documents_required' => 1,
                'requires_approval' => true,
            ],
        );

        MembershipTier::query()->firstOrCreate(
            ['slug' => 'full'],
            [
                'name' => 'Full Member',
                'description' => 'Full membership with all benefits.',
                'annual_dues' => 50000,
                'currency' => 'NGN',
                'benefits' => ['Newsletter', 'Event discounts', 'Digital library', 'Voting rights'],
                'sort_order' => 2,
                'is_active' => true,
                'min_documents_required' => 2,
                'requires_approval' => true,
            ],
        );
    }
}
