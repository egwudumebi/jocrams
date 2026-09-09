<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_member_can_update_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'profile-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/member/auth/profile', [
            'position' => 'Senior Researcher',
            'professional_bio' => 'Works on applied economics.',
            'country' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'street' => '12 Example Street',
            'social_links' => ['https://linkedin.com/in/example'],
            'achievements' => ['Published in Journal of Economics'],
            'orcid' => '0000-0002-1825-0097',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('profile.position', 'Senior Researcher')
            ->assertJsonPath('profile.address.city', 'Ikeja')
            ->assertJsonPath('profile.social_links.0', 'https://linkedin.com/in/example')
            ->assertJsonPath('profile.orcid', '0000-0002-1825-0097')
            ->assertJsonPath('profile.orcid_url', 'https://orcid.org/0000-0002-1825-0097');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->uuid,
            'position' => 'Senior Researcher',
            'city' => 'Ikeja',
            'orcid' => '0000-0002-1825-0097',
        ]);
    }

    public function test_member_can_save_orcid_from_profile_url(): void
    {
        $user = User::factory()->create([
            'email' => 'orcid-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/member/auth/profile', [
            'orcid' => 'https://orcid.org/0000-0001-2345-6789',
        ])->assertOk()
            ->assertJsonPath('profile.orcid', '0000-0001-2345-6789');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->uuid,
            'orcid' => '0000-0001-2345-6789',
        ]);
    }

    public function test_invalid_orcid_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'bad-orcid-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/member/auth/profile', [
            'orcid' => 'not-an-orcid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['orcid']);
    }

    public function test_member_can_upload_profile_image(): void
    {
        $user = User::factory()->create([
            'email' => 'profile-image-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/member/auth/profile/image', [
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile image uploaded.')
            ->assertJsonStructure(['profile_image']);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->uuid,
        ]);

        $path = (string) \App\Models\UserProfile::query()
            ->where('user_id', $user->uuid)
            ->value('profile_image_path');

        Storage::disk('public')->assertExists($path);
    }

    public function test_member_can_remove_profile_image(): void
    {
        $user = User::factory()->create([
            'email' => 'profile-remove-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/member/auth/profile/image', [
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertOk();

        $path = (string) \App\Models\UserProfile::query()
            ->where('user_id', $user->uuid)
            ->value('profile_image_path');

        Storage::disk('public')->assertExists($path);

        $this->deleteJson('/api/v1/member/auth/profile/image')
            ->assertOk()
            ->assertJsonPath('message', 'Profile image removed.')
            ->assertJsonPath('profile_image', null);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->uuid,
            'profile_image_path' => null,
        ]);

        Storage::disk('public')->assertMissing($path);

        $this->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('user.profile.profile_image', null);
    }

    public function test_me_includes_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'profile-me-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/member/auth/profile', [
            'position' => 'Economist',
        ])->assertOk();

        $response = $this->getJson('/api/v1/member/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.profile.position', 'Economist')
            ->assertJsonStructure([
                'user' => [
                    'profile' => [
                        'profile_image',
                        'position',
                        'professional_bio',
                        'orcid',
                        'orcid_url',
                        'address' => ['country', 'state', 'city', 'street'],
                        'social_links',
                        'achievements',
                    ],
                ],
            ]);
    }

    public function test_public_profile_image_endpoint_serves_image(): void
    {
        $user = User::factory()->create([
            'email' => 'profile-public-user@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/member/auth/profile/image', [
            'image' => UploadedFile::fake()->image('avatar.png'),
        ])->assertOk();

        $response = $this->get('/api/v1/public/users/'.$user->uuid.'/profile-image');

        $response->assertOk();
    }
}
