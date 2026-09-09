<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\Settings\SiteBranding;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteBrandingEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_password_reset_email_does_not_include_laravel_logo_without_site_logo(): void
    {
        config(['app.url' => 'http://127.0.0.1:8002']);

        $user = User::factory()->create(['name' => 'Ada Okonkwo']);

        $html = (new ResetPasswordNotification('test-token'))
            ->toMail($user)
            ->render()
            ->toHtml();

        $this->assertStringNotContainsString('laravel.com/img/notification-logo', $html);
        $this->assertStringContainsString('Regards,<br>', $html);
        $this->assertStringContainsString('JOCRAMS', $html);
        $this->assertStringContainsString('http://127.0.0.1:8002/member/reset-password?token=test-token', $html);
    }

    public function test_password_reset_email_includes_uploaded_site_logo(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://127.0.0.1:8002']);

        $logoPath = 'branding/site-logo-test.png';
        Storage::disk('public')->put($logoPath, 'logo-image');

        SystemSetting::query()->updateOrCreate(
            ['group_name' => 'general', 'setting_key' => 'site_logo_path'],
            ['setting_value' => $logoPath, 'is_secret' => false],
        );

        SiteBranding::clearCache();

        $user = User::factory()->create(['name' => 'Ada Okonkwo']);

        $html = (new ResetPasswordNotification('test-token'))
            ->toMail($user)
            ->render()
            ->toHtml();

        $this->assertStringContainsString('/storage/branding/site-logo-test.png', $html);
        $this->assertStringNotContainsString('laravel.com/img/notification-logo', $html);
    }

    public function test_admin_can_upload_and_remove_site_logo(): void
    {
        Storage::fake('public');

        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $upload = $this->postJson('/api/v1/admin/settings/general/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $upload->assertOk()
            ->assertJsonStructure(['message', 'path', 'url']);

        $this->assertDatabaseHas('system_settings', [
            'group_name' => 'general',
            'setting_key' => 'site_logo_path',
        ]);

        $path = SystemSetting::query()
            ->where('group_name', 'general')
            ->where('setting_key', 'site_logo_path')
            ->value('setting_value');

        $this->assertIsString($path);
        Storage::disk('public')->assertExists($path);

        $this->deleteJson('/api/v1/admin/settings/general/logo')
            ->assertOk();

        $this->assertSame('', SystemSetting::query()
            ->where('group_name', 'general')
            ->where('setting_key', 'site_logo_path')
            ->value('setting_value'));
    }
}
