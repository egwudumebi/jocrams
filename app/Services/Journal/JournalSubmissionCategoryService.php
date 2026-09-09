<?php

namespace App\Services\Journal;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalSubmissionCategoryService
{
    public const TYPE = 'journal';

    /** @param array<string, mixed> $data */
    public function create(array $data): Category
    {
        return Category::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
            'description' => $data['description'] ?? null,
            'type' => self::TYPE,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(Category $category, array $data): Category
    {
        abort_unless($category->type === self::TYPE, 404);

        $category->update([
            'name' => $data['name'] ?? $category->name,
            'slug' => isset($data['slug']) ? $this->uniqueSlug($data['slug'], $category->id) : $category->slug,
            'description' => $data['description'] ?? $category->description,
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        abort_unless($category->type === self::TYPE, 404);

        $category->delete();
    }

    public function paginateAdmin(int $perPage = 50): LengthAwarePaginator
    {
        return Category::query()
            ->where('type', self::TYPE)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Category $category) => $this->present($category));
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public function listActive()
    {
        return Category::query()
            ->where('type', self::TYPE)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => $this->present($category));
    }

    /** @return list<string> */
    public function activeNames(): array
    {
        return Category::query()
            ->where('type', self::TYPE)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function validateSelectedCategory(string $category): void
    {
        $allowed = $this->activeNames();

        if ($allowed === []) {
            return;
        }

        if (! in_array($category, $allowed, true)) {
            throw ValidationException::withMessages([
                'category' => ['Please select a valid manuscript category.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function present(Category $category): array
    {
        return [
            'uuid' => $category->uuid,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value);
        $base = $slug;
        $counter = 1;

        while (Category::query()
            ->where('type', self::TYPE)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
