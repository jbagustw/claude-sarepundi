<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_keeps_allowed_formatting_tags(): void
    {
        $html = '<p>Hello <strong>world</strong>, this is <em>nice</em>.</p><ul><li>One</li><li>Two</li></ul>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_it_strips_script_tags(): void
    {
        $html = '<p>Hello</p><script>alert("xss")</script>';

        $this->assertSame('<p>Hello</p>', HtmlSanitizer::clean($html));
    }

    public function test_it_strips_event_handler_attributes(): void
    {
        $html = '<p onclick="alert(1)">Click me</p>';

        $this->assertSame('<p>Click me</p>', HtmlSanitizer::clean($html));
    }

    public function test_it_strips_javascript_uri_from_links(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';

        $this->assertStringNotContainsString('javascript:', HtmlSanitizer::clean($html));
    }

    public function test_it_keeps_safe_links_with_target_blank(): void
    {
        $html = '<a href="https://example.com">Example</a>';

        // HTMLPurifier's HTML.TargetBlank also adds rel="noreferrer noopener"
        // automatically, which is exactly the safe behavior we want.
        $this->assertSame(
            '<a href="https://example.com" target="_blank" rel="noreferrer noopener">Example</a>',
            HtmlSanitizer::clean($html)
        );
    }

    public function test_it_strips_disallowed_tags_like_iframe_and_img(): void
    {
        $html = '<p>Text</p><iframe src="https://evil.com"></iframe><img src="x.jpg">';

        $this->assertSame('<p>Text</p>', HtmlSanitizer::clean($html));
    }

    public function test_it_returns_null_and_empty_string_unchanged(): void
    {
        $this->assertNull(HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean(''));
    }
}
