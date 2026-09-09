<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_admin_can_load_settings_group(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->getJson('/api/v1/admin/settings/general');

        $response->assertOk()
            ->assertJsonPath('group', 'general')
            ->assertJsonFragment(['key' => 'site_name', 'value' => 'JOCRAMS']);
    }

    public function test_admin_can_save_settings_group(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->postJson('/api/v1/admin/settings/general', [
            'values' => [
                'site_name' => 'Jocrams Updated',
                'contact_email' => 'hello@jocrams.test',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('system_settings', [
            'group_name' => 'general',
            'setting_key' => 'site_name',
            'setting_value' => 'Jocrams Updated',
        ]);
    }

    public function test_secret_setting_is_masked_and_preserved_when_not_changed(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->postJson('/api/v1/admin/settings/payment', [
            'values' => [
                'secret_key' => 'sk_live_test_secret',
            ],
        ])->assertOk();

        $stored = SystemSetting::query()
            ->where('group_name', 'payment')
            ->where('setting_key', 'secret_key')
            ->value('setting_value');

        $this->assertSame('sk_live_test_secret', Crypt::decryptString($stored));

        $this->getJson('/api/v1/admin/settings/payment')
            ->assertOk()
            ->assertJsonFragment(['key' => 'secret_key', 'value' => '********']);

        $this->postJson('/api/v1/admin/settings/payment', [
            'values' => [
                'secret_key' => '********',
                'currency' => 'NGN',
            ],
        ])->assertOk();

        $storedAfter = SystemSetting::query()
            ->where('group_name', 'payment')
            ->where('setting_key', 'secret_key')
            ->value('setting_value');

        $this->assertSame('sk_live_test_secret', Crypt::decryptString($storedAfter));
    }

    public function test_admin_can_create_user_with_role(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $response = $this->postJson('/api/v1/admin/settings/user-roles', [
            'name' => 'Finance Admin',
            'email' => 'finance@jocrams.test',
            'role_slug' => 'super-admin',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'user_id', 'temporary_password']);

        $user = User::query()->where('email', 'finance@jocrams.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('super-admin'));
    }

    public function test_unknown_settings_group_returns_not_found(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->first());

        $this->getJson('/api/v1/admin/settings/invalid-group')
            ->assertNotFound();
    }
}
