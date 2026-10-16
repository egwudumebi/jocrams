<?php

use App\Models\MembershipTier;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $fee = (float) config('jocrams.fees.membership_registration', 25000);

        MembershipTier::query()->where('slug', 'sicama')->update([
            'name' => 'SICAMA Member',
            'description' => 'Official SICAMA association membership. Includes ID card, certificate, and welcome letter after payment.',
            'annual_dues' => $fee,
            'is_active' => true,
            'min_documents_required' => 0,
            'requires_approval' => false,
            'sort_order' => 0,
        ]);

        MembershipTier::query()->whereIn('slug', ['associate', 'full'])->update([
            'is_active' => false,
            'annual_dues' => $fee,
        ]);

        foreach (['fee_student_ngn', 'fee_professional_ngn'] as $key) {
            SystemSetting::query()->updateOrCreate(
                ['group_name' => 'membership', 'setting_key' => $key],
                ['setting_value' => (string) (int) $fee, 'is_secret' => false],
            );
        }
    }

    public function down(): void
    {
        MembershipTier::query()->whereIn('slug', ['associate', 'full', 'sicama'])->update([
            'is_active' => true,
            'annual_dues' => 20000,
        ]);
    }
};
