<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $adminId = DB::table('users')->where('email', 'admin@jocrams.test')->value('id');

        $settings = [
            ['group_name' => 'general', 'setting_key' => 'site_banner_enabled', 'setting_value' => '0', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'site_banner_message', 'setting_value' => '', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'site_banner_link', 'setting_value' => '', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'site_banner_link_label', 'setting_value' => 'Learn more', 'is_secret' => false],
            ['group_name' => 'general', 'setting_key' => 'site_banner_style', 'setting_value' => 'info', 'is_secret' => false],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('system_settings')
                ->where('group_name', $setting['group_name'])
                ->where('setting_key', $setting['setting_key'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('system_settings')->insert([
                ...$setting,
                'updated_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('group_name', 'general')
            ->whereIn('setting_key', [
                'site_banner_enabled',
                'site_banner_message',
                'site_banner_link',
                'site_banner_link_label',
                'site_banner_style',
            ])
            ->delete();
    }
};
