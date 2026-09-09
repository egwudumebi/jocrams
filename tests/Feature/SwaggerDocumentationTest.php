<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['openapi.enabled' => true]);
    }

    public function test_openapi_json_documents_registered_v1_routes(): void
    {
        $response = $this->getJson('/docs/openapi.json');

        $response->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'Jocrams API');

        $document = $response->json();

        $this->assertArrayHasKey('/api/v1/member/auth/login', $document['paths']);
        $this->assertArrayHasKey('/api/v1/admin/auth/login', $document['paths']);
        $this->assertArrayHasKey('/api/v1/public/members', $document['paths']);
        $this->assertArrayHasKey('post', $document['paths']['/api/v1/member/auth/login']);
        $this->assertArrayHasKey('requestBody', $document['paths']['/api/v1/member/auth/login']['post']);
        $this->assertArrayHasKey('bearerAuth', $document['components']['securitySchemes']);
        $this->assertArrayHasKey('PaginationMeta', $document['components']['schemas']);
    }

    public function test_swagger_ui_loads(): void
    {
        $response = $this->get('/docs');

        $response->assertOk();
        $response->assertSee('SwaggerUIBundle', false);
        $response->assertSee('/docs/openapi.json', false);
    }
}
