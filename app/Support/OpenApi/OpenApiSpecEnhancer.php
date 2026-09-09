<?php

namespace App\Support\OpenApi;

class OpenApiSpecEnhancer
{
    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function enhance(array $spec): array
    {
        $spec['components']['schemas'] = array_merge(
            $spec['components']['schemas'] ?? [],
            $this->schemas(),
        );

        $spec['paths'] = $this->enhancePaths($spec['paths'] ?? []);

        return $spec;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        return [
            'PaginationMeta' => [
                'type' => 'object',
                'description' => 'Standard list pagination metadata.',
                'properties' => [
                    'current_page' => ['type' => 'integer', 'example' => 1],
                    'last_page' => ['type' => 'integer', 'example' => 3],
                    'per_page' => ['type' => 'integer', 'example' => 25],
                    'total' => ['type' => 'integer', 'example' => 52],
                ],
            ],
            'PaginatedListResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return array<string, mixed>
     */
    private function enhancePaths(array $paths): array
    {
        $paginatedPaths = [
            '/api/v1/admin/members',
            '/api/v1/admin/payments',
            '/api/v1/public/members',
            '/api/v1/public/news',
            '/api/v1/member/applications',
        ];

        foreach ($paginatedPaths as $path) {
            $this->addPaginationQueryParameters($paths, $path, 'get');
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $paths
     */
    private function addPaginationQueryParameters(array &$paths, string $path, string $method): void
    {
        if (! isset($paths[$path][$method])) {
            return;
        }

        $existing = $paths[$path][$method]['parameters'] ?? [];
        $names = array_column($existing, 'name');

        foreach ([
            [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'description' => 'Page number (1-based).',
            ],
            [
                'name' => 'per_page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
                'description' => 'Items per page (max 100).',
            ],
        ] as $parameter) {
            if (! in_array($parameter['name'], $names, true)) {
                $existing[] = $parameter;
            }
        }

        $paths[$path][$method]['parameters'] = $existing;
    }
}
