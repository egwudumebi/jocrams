<?php

namespace Tests\Feature;

use App\Enums\FeeCatalogCategory;
use App\Enums\FeeCatalogType;
use App\Models\FeeCatalogItem;
use App\Models\User;
use Database\Seeders\FeeCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeeCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(FeeCatalogSeeder::class);
    }

    public function test_journal_manager_can_list_fee_catalog(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->firstOrFail());

        $this->getJson('/api/v1/journal/admin/fee-catalog')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['uuid', 'name', 'category', 'fee_type', 'amount', 'currency', 'is_active'],
                ],
            ]);
    }

    public function test_journal_manager_can_create_and_deactivate_fee(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->firstOrFail());

        $response = $this->postJson('/api/v1/journal/admin/fee-catalog', [
            'name' => 'Premium Submission Fee',
            'code' => 'journal_submission_premium',
            'category' => FeeCatalogCategory::Journal->value,
            'fee_type' => FeeCatalogType::Submission->value,
            'amount' => 20000,
            'currency' => 'NGN',
            'description' => 'Higher tier submission fee.',
            'sort_order' => 5,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Premium Submission Fee')
            ->assertJsonPath('data.amount', 20000);

        $uuid = $response->json('data.uuid');

        $this->putJson('/api/v1/journal/admin/fee-catalog/'.$uuid, [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('fee_catalog_items', [
            'uuid' => $uuid,
            'is_active' => false,
        ]);
    }

    public function test_create_rejects_invalid_category_type_pair(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->firstOrFail());

        $this->postJson('/api/v1/journal/admin/fee-catalog', [
            'name' => 'Invalid Fee',
            'category' => FeeCatalogCategory::Membership->value,
            'fee_type' => FeeCatalogType::Submission->value,
            'amount' => 1000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['fee_type']);
    }

    public function test_public_endpoint_lists_active_fees_only(): void
    {
        FeeCatalogItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive Fee',
            'category' => FeeCatalogCategory::Journal,
            'fee_type' => FeeCatalogType::Submission,
            'amount' => 999,
            'currency' => 'NGN',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/public/fee-catalog?category=journal')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Inactive Fee']);
    }

    public function test_journal_manager_can_delete_fee(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@jocrams.test')->firstOrFail());

        $item = FeeCatalogItem::query()->where('code', 'journal_submission_standard')->firstOrFail();

        $this->deleteJson('/api/v1/journal/admin/fee-catalog/'.$item->uuid)
            ->assertOk();

        $this->assertSoftDeleted('fee_catalog_items', ['uuid' => $item->uuid]);
    }
}
