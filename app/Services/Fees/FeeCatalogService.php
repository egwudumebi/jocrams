<?php

namespace App\Services\Fees;

use App\Enums\FeeCatalogCategory;
use App\Enums\FeeCatalogType;
use App\Models\FeeCatalogItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeeCatalogService
{
    /** @param array<string, mixed> $filters */
    public function paginateAdmin(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = FeeCatalogItem::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['fee_type'])) {
            $query->where('fee_type', $filters['fee_type']);
        }

        if (array_key_exists('active_only', $filters) && filter_var($filters['active_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        return $query->paginate($perPage)->through(fn (FeeCatalogItem $item) => $this->present($item));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function listActive(?string $category = null, ?string $feeType = null): Collection
    {
        return FeeCatalogItem::query()
            ->where('is_active', true)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($feeType, fn ($query) => $query->where('fee_type', $feeType))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (FeeCatalogItem $item) => $this->present($item));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): FeeCatalogItem
    {
        $this->assertValidCategoryTypePair(
            FeeCatalogCategory::from($data['category']),
            FeeCatalogType::from($data['fee_type']),
        );

        return FeeCatalogItem::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code'] ?? null),
            'category' => $data['category'],
            'fee_type' => $data['fee_type'],
            'amount' => $data['amount'],
            'currency' => strtoupper((string) ($data['currency'] ?? config('payments.currency', 'NGN'))),
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(FeeCatalogItem $item, array $data): FeeCatalogItem
    {
        $category = isset($data['category'])
            ? FeeCatalogCategory::from($data['category'])
            : $item->category;
        $feeType = isset($data['fee_type'])
            ? FeeCatalogType::from($data['fee_type'])
            : $item->fee_type;

        $this->assertValidCategoryTypePair($category, $feeType);

        $item->update([
            'name' => $data['name'] ?? $item->name,
            'code' => array_key_exists('code', $data) ? $this->normalizeCode($data['code']) : $item->code,
            'category' => $category->value,
            'fee_type' => $feeType->value,
            'amount' => $data['amount'] ?? $item->amount,
            'currency' => isset($data['currency'])
                ? strtoupper((string) $data['currency'])
                : $item->currency,
            'description' => $data['description'] ?? $item->description,
            'sort_order' => $data['sort_order'] ?? $item->sort_order,
            'is_active' => $data['is_active'] ?? $item->is_active,
        ]);

        return $item->fresh();
    }

    public function delete(FeeCatalogItem $item): void
    {
        $item->delete();
    }

    /** @return array<string, mixed> */
    public function present(FeeCatalogItem $item): array
    {
        return [
            'uuid' => $item->uuid,
            'name' => $item->name,
            'code' => $item->code,
            'category' => $item->category->value,
            'category_label' => $this->categoryLabel($item->category),
            'fee_type' => $item->fee_type->value,
            'fee_type_label' => $this->feeTypeLabel($item->fee_type),
            'amount' => (float) $item->amount,
            'currency' => $item->currency,
            'description' => $item->description,
            'sort_order' => $item->sort_order,
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function optionsMeta(): array
    {
        return [
            'categories' => collect(FeeCatalogCategory::cases())->map(fn (FeeCatalogCategory $category) => [
                'value' => $category->value,
                'label' => $this->categoryLabel($category),
                'fee_types' => collect(FeeCatalogType::forCategory($category))->map(fn (string $type) => [
                    'value' => $type,
                    'label' => $this->feeTypeLabel(FeeCatalogType::from($type)),
                ])->values()->all(),
            ])->values()->all(),
            'currencies' => ['NGN', 'USD', 'GBP', 'EUR'],
        ];
    }

    private function assertValidCategoryTypePair(FeeCatalogCategory $category, FeeCatalogType $feeType): void
    {
        if (! in_array($feeType->value, FeeCatalogType::forCategory($category), true)) {
            throw ValidationException::withMessages([
                'fee_type' => ['The selected fee type is not valid for this category.'],
            ]);
        }
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = Str::slug(trim($code), '_');

        return $normalized !== '' ? $normalized : null;
    }

    private function categoryLabel(FeeCatalogCategory $category): string
    {
        return match ($category) {
            FeeCatalogCategory::Membership => 'Membership',
            FeeCatalogCategory::Journal => 'Journal',
            FeeCatalogCategory::Event => 'Event',
        };
    }

    private function feeTypeLabel(FeeCatalogType $feeType): string
    {
        return match ($feeType) {
            FeeCatalogType::AnnualDues => 'Annual dues',
            FeeCatalogType::Submission => 'Submission fee',
            FeeCatalogType::Publication => 'Publication fee',
            FeeCatalogType::Registration => 'Registration fee',
            FeeCatalogType::EventRegistration => 'Event registration',
            FeeCatalogType::Other => 'Other',
        };
    }
}
