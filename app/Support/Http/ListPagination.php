<?php

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListPagination
{
    public static function perPage(?Request $request = null, int $default = 25, int $max = 100): int
    {
        $request ??= request();

        return min($max, max(1, (int) $request->query('per_page', $default)));
    }

    /** @return array{current_page: int, last_page: int, per_page: int, total: int} */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /** @param array<int, mixed> $data @param array<string, mixed> $extra */
    public static function response(array $data, LengthAwarePaginator $paginator, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge([
            'data' => $data,
            'meta' => self::meta($paginator),
        ], $extra), $status, [], JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $extra */
    public static function fromPaginator(LengthAwarePaginator $paginator, array $extra = [], int $status = 200): JsonResponse
    {
        return self::response($paginator->items(), $paginator, $extra, $status);
    }
}
