<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeCatalogItem;
use App\Services\Fees\FeeCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeCatalogController extends Controller
{
    public function __construct(private readonly FeeCatalogService $service) {}

    public function meta(): JsonResponse
    {
        return response()->json(['data' => $this->service->optionsMeta()]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginateAdmin([
            'category' => $request->query('category'),
            'fee_type' => $request->query('fee_type'),
            'active_only' => $request->query('active_only'),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $item = $this->service->create($data);

        return response()->json(['data' => $this->service->present($item)], 201);
    }

    public function update(Request $request, FeeCatalogItem $feeCatalogItem): JsonResponse
    {
        $data = $request->validate($this->rules(isUpdate: true, ignoreId: $feeCatalogItem->id));

        $item = $this->service->update($feeCatalogItem, $data);

        return response()->json(['data' => $this->service->present($item)]);
    }

    public function destroy(FeeCatalogItem $feeCatalogItem): JsonResponse
    {
        $this->service->delete($feeCatalogItem);

        return response()->json(['message' => 'Fee removed from catalog.']);
    }

    /** @return array<string, mixed> */
    private function rules(bool $isUpdate = false, ?int $ignoreId = null): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        $codeRule = Rule::unique('fee_catalog_items', 'code')->whereNull('deleted_at');
        if ($ignoreId !== null) {
            $codeRule = $codeRule->ignore($ignoreId);
        }

        return [
            'name' => [$required, 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:80', $codeRule],
            'category' => [$required, Rule::in(\App\Enums\FeeCatalogCategory::values())],
            'fee_type' => [$required, Rule::in(\App\Enums\FeeCatalogType::values())],
            'amount' => [$required, 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
