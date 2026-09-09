<?php

namespace Tests\Unit;

use App\Support\Html\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_decodes_entity_escaped_html(): void
    {
        $input = '&lt;h3&gt;Scope&lt;/h3&gt;&lt;p&gt;Hello&lt;/p&gt;';

        $this->assertSame('<h3>Scope</h3><p>Hello</p>', HtmlSanitizer::normalize($input));
        $this->assertSame('<h3>Scope</h3><p>Hello</p>', HtmlSanitizer::clean($input));
    }

    public function test_it_leaves_valid_html_unchanged(): void
    {
        $input = '<p>Hello <strong>world</strong></p>';

        $this->assertSame($input, HtmlSanitizer::normalize($input));
        $this->assertSame($input, HtmlSanitizer::clean($input));
    }
}
