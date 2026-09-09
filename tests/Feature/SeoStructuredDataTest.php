<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoStructuredDataTest extends TestCase
{
    public function test_homepage_includes_organization_logo_structured_data(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"@type":"ImageObject"', false);
        $response->assertSee('images/sicama-logo.png', false);
        $response->assertSee('rel="icon"', false);
        $response->assertSee('rel="apple-touch-icon"', false);
        $response->assertSee('property="og:image:width"', false);
    }
}
