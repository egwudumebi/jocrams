<?php

namespace App\Support\OpenApi;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\In as InRule;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionNamedType;
use Throwable;

class OpenApiSpecFactory
{
    /**
     * @var array<int, string>
     */
    private array $tags = [];

    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
        private readonly OpenApiSpecEnhancer $enhancer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $this->tags = [];
        $paths = [];

        foreach ($this->router->getRoutes() as $route) {
            if (! $this->shouldDocument($route)) {
                continue;
            }

            $path = $this->normalisePath($route);

            foreach ($this->httpMethods($route) as $method) {
                $paths[$path][$method] = $this->operation($route, $method, $path);
            }
        }

        ksort($paths);
        sort($this->tags);

        return $this->enhancer->enhance([
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('openapi.title'),
                'description' => config('openapi.description'),
                'version' => config('openapi.version'),
            ],
            'servers' => $this->servers(),
            'tags' => array_map(
                fn (string $tag): array => ['name' => $tag],
                array_values(array_unique($this->tags)),
            ),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum token',
                    ],
                ],
            ],
        ]);
    }

    private function shouldDocument(LaravelRoute $route): bool
    {
        $uri = $route->uri();

        foreach ((array) config('openapi.include_paths', []) as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function httpMethods(LaravelRoute $route): array
    {
        return collect($route->methods())
            ->map(fn (string $method): string => strtolower($method))
            ->reject(fn (string $method): bool => in_array($method, ['head', 'options'], true))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(LaravelRoute $route, string $method, string $path): array
    {
        $middleware = $route->gatherMiddleware();
        $tag = $this->tagFor($path);
        $this->tags[] = $tag;

        $operation = [
            'tags' => [$tag],
            'summary' => $this->summaryFor($route, $method, $path),
            'operationId' => $this->operationId($method, $route->uri()),
            'parameters' => $this->parametersFor($route),
            'responses' => $this->responsesFor($middleware, $method),
        ];

        $requestClass = $this->formRequestClassFor($route);
        if ($requestClass !== null) {
            $rules = $this->rulesFor($requestClass);
            if ($rules !== null) {
                if (in_array($method, ['get', 'delete'], true)) {
                    $operation['parameters'] = array_merge(
                        $operation['parameters'],
                        $this->queryParametersFromRules($rules),
                    );
                } else {
                    $operation['requestBody'] = $this->requestBodyFromRules($rules);
                }
            }
        }

        $security = $this->securityFor($middleware);
        if ($security !== null) {
            $operation['security'] = $security;
        }

        $abilities = $this->abilitiesFor($middleware);
        if ($abilities !== []) {
            $operation['x-required-abilities'] = $abilities;
        }

        $controller = $this->controllerFor($route);
        if ($controller !== null) {
            $operation['x-controller'] = $controller;
        }

        return $operation;
    }

    private function normalisePath(LaravelRoute $route): string
    {
        $uri = preg_replace('/\{([^}?]+)\??\}/', '{$1}', $route->uri()) ?: $route->uri();

        return '/'.ltrim($uri, '/');
    }

    private function tagFor(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $tag = $segments[0] ?? 'api';

        if (($segments[0] ?? null) === 'api' && ($segments[1] ?? null) === 'v1') {
            $tag = $segments[2] ?? 'api';
        } elseif (($segments[0] ?? null) === 'v1') {
            $tag = $segments[1] ?? 'api';
        }

        return Str::headline($tag);
    }

    private function summaryFor(LaravelRoute $route, string $method, string $path): string
    {
        $controller = $this->controllerFor($route);

        if ($controller !== null) {
            $class = Str::before($controller, '@');
            $name = class_basename($class);
            $name = Str::replaceEnd('Controller', '', $name);

            return Str::headline($name);
        }

        return strtoupper($method).' '.$path;
    }

    private function operationId(string $method, string $uri): string
    {
        $id = preg_replace('/[^A-Za-z0-9]+/', '_', $method.'_'.$uri) ?: $method;

        return trim($id, '_');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parametersFor(LaravelRoute $route): array
    {
        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);

        return collect($matches[1] ?? [])
            ->map(function (string $parameter) use ($route): array {
                $name = rtrim($parameter, '?');
                $schema = $this->pathParameterSchema($route, $name);

                return [
                    'name' => $name,
                    'in' => 'path',
                    'required' => ! str_ends_with($parameter, '?'),
                    'schema' => $schema,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pathParameterSchema(LaravelRoute $route, string $name): array
    {
        $wheres = $route->wheres ?? [];
        $pattern = (string) ($wheres[$name] ?? '');

        if (Str::contains($pattern, '[0-9]+') || Str::contains($pattern, '\\d+')) {
            return ['type' => 'integer'];
        }

        if (Str::endsWith($name, 'Id') || Str::contains(strtolower($pattern), 'a-f')) {
            return ['type' => 'string', 'format' => 'uuid'];
        }

        return ['type' => 'string'];
    }

    /**
     * @param  array<int, string>  $middleware
     * @return array<string, mixed>
     */
    private function responsesFor(array $middleware, string $method): array
    {
        $responses = [
            $method === 'post' ? '201' : '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ],
        ];

        if ($this->usesMiddleware($middleware, 'auth:sanctum')) {
            $responses['401'] = ['description' => 'Unauthenticated'];
        }

        if ($this->abilitiesFor($middleware) !== []) {
            $responses['403'] = ['description' => 'Forbidden'];
        }

        if ($this->usesMiddleware($middleware, 'throttle:')) {
            $responses['429'] = ['description' => 'Too many requests'];
        }

        return $responses;
    }

    /**
     * @param  array<int, string>  $middleware
     * @return array<int, array<string, array<int, mixed>>>|null
     */
    private function securityFor(array $middleware): ?array
    {
        if (! $this->usesMiddleware($middleware, 'auth:sanctum')) {
            return null;
        }

        return [
            ['bearerAuth' => []],
        ];
    }

    /**
     * @param  array<int, string>  $middleware
     */
    private function usesMiddleware(array $middleware, string $needle): bool
    {
        foreach ($middleware as $item) {
            if (str_starts_with($item, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $middleware
     * @return array<int, string>
     */
    private function abilitiesFor(array $middleware): array
    {
        return collect($middleware)
            ->filter(fn (string $item): bool => str_starts_with($item, 'ability:'))
            ->map(fn (string $item): string => Str::after($item, 'ability:'))
            ->values()
            ->all();
    }

    private function controllerFor(LaravelRoute $route): ?string
    {
        $controller = $route->getAction('controller') ?? $route->getAction('uses');

        return is_string($controller) ? $controller : null;
    }

    private function formRequestClassFor(LaravelRoute $route): ?string
    {
        $controller = $this->controllerFor($route);
        if ($controller === null) {
            return null;
        }

        [$class, $method] = str_contains($controller, '@')
            ? explode('@', $controller, 2)
            : [$controller, '__invoke'];

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($class, $method);
        } catch (Throwable) {
            return null;
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $requestClass = $type->getName();
            if (is_subclass_of($requestClass, FormRequest::class)) {
                return $requestClass;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rulesFor(string $requestClass): ?array
    {
        try {
            $request = new $requestClass;

            if (! $request instanceof FormRequest) {
                return null;
            }

            $request->setContainer($this->container);

            return $request->rules();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function requestBodyFromRules(array $rules): array
    {
        $schema = $this->schemaFromRules($rules);
        $mediaType = $schema['x-has-files'] ? 'multipart/form-data' : 'application/json';
        unset($schema['x-has-files']);

        return [
            'required' => true,
            'content' => [
                $mediaType => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<int, array<string, mixed>>
     */
    private function queryParametersFromRules(array $rules): array
    {
        $schema = $this->schemaFromRules($rules);
        $required = $schema['required'] ?? [];

        return collect($schema['properties'] ?? [])
            ->map(function (array $propertySchema, string $name) use ($required): array {
                return [
                    'name' => $name,
                    'in' => 'query',
                    'required' => in_array($name, $required, true),
                    'schema' => $propertySchema,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function schemaFromRules(array $rules): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'x-has-files' => false,
        ];

        foreach ($rules as $field => $fieldRules) {
            if (str_contains($field, '.*')) {
                continue;
            }

            $ruleStrings = $this->normaliseRules($fieldRules);
            $propertySchema = $this->propertySchemaFromRules($ruleStrings);
            $schema['properties'][$field] = $propertySchema;

            if (in_array('required', $ruleStrings, true)) {
                $schema['required'][] = $field;
            }

            if (($propertySchema['format'] ?? null) === 'binary') {
                $schema['x-has-files'] = true;
            }
        }

        foreach ($rules as $field => $fieldRules) {
            if (! preg_match('/^(.+)\.\*\.(.+)$/', $field, $matches)) {
                continue;
            }

            [, $parent, $child] = $matches;
            $childRules = $this->normaliseRules($fieldRules);
            $childSchema = $this->propertySchemaFromRules($childRules);

            $schema['properties'][$parent] ??= [
                'type' => 'array',
                'items' => ['type' => 'object', 'properties' => []],
            ];
            $schema['properties'][$parent]['type'] = 'array';
            $schema['properties'][$parent]['items']['type'] = 'object';
            $schema['properties'][$parent]['items']['properties'][$child] = $childSchema;

            if (in_array('required', $childRules, true)) {
                $schema['properties'][$parent]['items']['required'] ??= [];
                $schema['properties'][$parent]['items']['required'][] = $child;
            }

            if (($childSchema['format'] ?? null) === 'binary') {
                $schema['x-has-files'] = true;
            }
        }

        foreach ($rules as $field => $fieldRules) {
            if (! str_contains($field, '.*') || preg_match('/^(.+)\.\*\.(.+)$/', $field)) {
                continue;
            }

            $parent = Str::before($field, '.*');
            $itemSchema = $this->propertySchemaFromRules($this->normaliseRules($fieldRules));
            $schema['properties'][$parent] ??= ['type' => 'array'];
            $schema['properties'][$parent]['type'] = 'array';
            $schema['properties'][$parent]['items'] = $itemSchema;

            if (($itemSchema['format'] ?? null) === 'binary') {
                $schema['x-has-files'] = true;
            }
        }

        if ($schema['required'] === []) {
            unset($schema['required']);
        }

        return $schema;
    }

    /**
     * @return array<int, string>
     */
    private function normaliseRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        if (! is_array($rules)) {
            $rules = [$rules];
        }

        return collect($rules)
            ->flatMap(function (mixed $rule): array {
                if ($rule instanceof InRule) {
                    $reflection = new ReflectionProperty($rule, 'values');
                    $reflection->setAccessible(true);
                    /** @var array<int, mixed> $values */
                    $values = $reflection->getValue($rule);

                    return ['in:'.implode(',', array_map(strval(...), $values))];
                }

                if (is_string($rule)) {
                    return [$rule];
                }

                if (is_object($rule) && method_exists($rule, '__toString')) {
                    return [(string) $rule];
                }

                return [is_object($rule) ? class_basename($rule) : (string) $rule];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<string, mixed>
     */
    private function propertySchemaFromRules(array $rules): array
    {
        $lowerRules = array_map(strtolower(...), $rules);
        $schema = ['type' => 'string'];

        if ($this->hasRule($lowerRules, ['integer'])) {
            $schema = ['type' => 'integer'];
        } elseif ($this->hasRule($lowerRules, ['numeric', 'decimal'])) {
            $schema = ['type' => 'number'];
        } elseif ($this->hasRule($lowerRules, ['boolean'])) {
            $schema = ['type' => 'boolean'];
        } elseif ($this->hasRule($lowerRules, ['array'])) {
            $schema = ['type' => 'array', 'items' => ['type' => 'string']];
        } elseif ($this->hasRule($lowerRules, ['file', 'image'])) {
            $schema = ['type' => 'string', 'format' => 'binary'];
        }

        foreach ($lowerRules as $rule) {
            if (str_starts_with($rule, 'email')) {
                $schema['format'] = 'email';
            } elseif ($rule === 'uuid') {
                $schema['format'] = 'uuid';
            } elseif ($rule === 'url') {
                $schema['format'] = 'uri';
            } elseif ($rule === 'date') {
                $schema['format'] = 'date';
            } elseif ($rule === 'nullable') {
                $schema['nullable'] = true;
            } elseif (str_starts_with($rule, 'in:')) {
                $schema['enum'] = array_map(
                    fn (string $value): string => trim($value, '"'),
                    array_values(array_filter(explode(',', substr($rule, 3)), fn (string $value): bool => $value !== ''))
                );
            } elseif (str_starts_with($rule, 'max:')) {
                $this->applyMaximum($schema, (int) substr($rule, 4));
            } elseif (str_starts_with($rule, 'min:')) {
                $this->applyMinimum($schema, (int) substr($rule, 4));
            }
        }

        return $schema;
    }

    /**
     * @param  array<int, string>  $rules
     * @param  array<int, string>  $needles
     */
    private function hasRule(array $rules, array $needles): bool
    {
        foreach ($rules as $rule) {
            foreach ($needles as $needle) {
                if ($rule === $needle || str_starts_with($rule, $needle.':')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function applyMaximum(array &$schema, int $value): void
    {
        if (($schema['type'] ?? null) === 'string') {
            $schema['maxLength'] = $value;
        } elseif (in_array($schema['type'] ?? null, ['integer', 'number'], true)) {
            $schema['maximum'] = $value;
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function applyMinimum(array &$schema, int $value): void
    {
        if (($schema['type'] ?? null) === 'string') {
            $schema['minLength'] = $value;
        } elseif (in_array($schema['type'] ?? null, ['integer', 'number'], true)) {
            $schema['minimum'] = $value;
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function servers(): array
    {
        return collect(config('openapi.servers', []))
            ->map(fn (string $url): array => ['url' => $url])
            ->values()
            ->all();
    }
}
