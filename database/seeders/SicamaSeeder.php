<?php

namespace Database\Seeders;

use App\Models\EditorialBoardMember;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class SicamaSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@jocrams.test')->value('id');

        $settings = [
            ['group_name' => 'general', 'setting_key' => 'site_name', 'setting_value' => 'JOCRAMS', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'site_tagline', 'setting_value' => 'Journal of Communication Research and Media Studies', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'journal_full_name', 'setting_value' => 'Journal of Communication Research and Media Studies', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'parent_org_name', 'setting_value' => 'SICAMA', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'parent_org_full_name', 'setting_value' => 'Scholars in Communication and Media Advancement Initiative', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'parent_org_motto', 'setting_value' => 'Advancing Communication and Media Scholarship for the good of the society.', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'parent_org_logo_path', 'setting_value' => 'branding/sicama-logo.png', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'site_logo_path', 'setting_value' => 'branding/sicama-logo.png', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'app_name', 'setting_value' => 'JOCRAMS', 'is_secret' => false],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->updateOrCreate(
                [
                    'group_name' => $setting['group_name'],
                    'setting_key' => $setting['setting_key'],
                ],
                [
                    'setting_value' => $setting['setting_value'],
                    'is_secret' => $setting['is_secret'],
                    'updated_by' => $adminId,
                ],
            );
        }

        EditorialBoardMember::query()->updateOrCreate(
            ['name' => 'Dr Anthony Ekpo Bassey', 'role_title' => 'Production Editor'],
            [
                'affiliation' => 'Department of Mass Communication, Faculty of Communication and Media Studies, University of Calabar, Calabar-Nigeria.',
                'bio' => null,
                'sort_order' => 10,
                'is_active' => true,
            ],
        );
    }
}
