<?php

namespace Tests\Unit\Services\Template;

use App\Services\Template\TemplateEditService;
use Tests\TestCase;

class TemplateEditServiceTest extends TestCase
{
    protected TemplateEditService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateEditService();
    }

    // ==========================================
    // EXACT MATCH TESTS
    // ==========================================

    public function test_applies_single_exact_edit(): void
    {
        // Given
        $content = '<div class="header"><h1>Title</h1></div>';
        $edits = [
            ['old_string' => '<h1>Title</h1>', 'new_string' => '<h1>New Title</h1>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<div class="header"><h1>New Title</h1></div>', $result['content']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['applied_count']);
    }

    public function test_applies_multiple_exact_edits(): void
    {
        // Given
        $html = <<<HTML
<div class="invoice-header">
  <h1 data-var-company_name>Company Name</h1>
  <p class="subtitle">Invoice #<span data-var-invoice_number>12345</span></p>
</div>
HTML;
        $edits = [
            ['old_string' => 'Company Name', 'new_string' => 'Acme Corp'],
            ['old_string' => '12345', 'new_string' => '67890'],
        ];

        // When
        $result = $this->service->applyEdits($html, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Acme Corp', $result['content']);
        $this->assertStringContainsString('67890', $result['content']);
        $this->assertStringNotContainsString('Company Name', $result['content']);
        $this->assertStringNotContainsString('12345', $result['content']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(2, $result['applied_count']);
    }

    public function test_edits_are_applied_sequentially(): void
    {
        // Given - Second edit operates on content modified by first
        $content = '<p>Hello World</p>';
        $edits = [
            ['old_string' => 'Hello', 'new_string' => 'Goodbye'],
            ['old_string' => 'Goodbye World', 'new_string' => 'Goodbye Universe'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<p>Goodbye Universe</p>', $result['content']);
        $this->assertEquals(2, $result['applied_count']);
    }

    // ==========================================
    // WHITESPACE NORMALIZATION TESTS
    // ==========================================

    public function test_matches_with_different_whitespace(): void
    {
        // Given - Content has multiple spaces where needle has single space
        $content = '<div   class="box">Content</div>';
        $edits = [
            ['old_string' => '<div class="box">', 'new_string' => '<div class="container">'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<div class="container">Content</div>', $result['content']);
        $this->assertEquals(1, $result['applied_count']);
    }

    public function test_matches_with_newlines_vs_spaces(): void
    {
        // Given - Content has newlines where needle has spaces
        $content = "<div>\n  text\n</div>";
        $edits = [
            ['old_string' => '<div> text </div>', 'new_string' => '<div>new text</div>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<div>new text</div>', $result['content']);
        $this->assertEquals(1, $result['applied_count']);
    }

    public function test_normalizes_tabs_spaces_newlines(): void
    {
        // Given - Content has mixed whitespace (tabs, spaces, newlines) between tokens
        $content = "<span\t\n   class=\"label\">Text</span>";
        $edits = [
            ['old_string' => '<span class="label">Text</span>', 'new_string' => '<span class="badge">Text</span>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<span class="badge">Text</span>', $result['content']);
        $this->assertEquals(1, $result['applied_count']);
    }

    public function test_whitespace_normalization_with_css(): void
    {
        // Given - CSS content with different indentation
        $css = <<<CSS
.invoice-header {
    background: #f0f0f0;
    padding: 20px;
}
CSS;
        $edits = [
            ['old_string' => '.invoice-header { background: #f0f0f0; padding: 20px; }', 'new_string' => '.invoice-header { background: #fff; padding: 30px; }'],
        ];

        // When
        $result = $this->service->applyEdits($css, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('.invoice-header { background: #fff; padding: 30px; }', $result['content']);
        $this->assertEquals(1, $result['applied_count']);
    }

    // ==========================================
    // ERROR HANDLING TESTS
    // ==========================================

    public function test_returns_not_found_error_when_anchor_missing(): void
    {
        // Given
        $content = '<div>Some content</div>';
        $edits = [
            ['old_string' => '<span>Nonexistent</span>', 'new_string' => '<span>New</span>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertFalse($result['success']);
        $this->assertEquals('<div>Some content</div>', $result['content']); // Content unchanged
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('not_found', $result['errors'][0]['type']);
        $this->assertEquals(0, $result['applied_count']);
    }

    public function test_returns_multiple_matches_error(): void
    {
        // Given - Anchor appears more than once
        $content = '<p>Hello</p><p>Hello</p><p>World</p>';
        $edits = [
            ['old_string' => '<p>Hello</p>', 'new_string' => '<p>Hi</p>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('multiple_matches', $result['errors'][0]['type']);
        $this->assertEquals(2, $result['errors'][0]['count']);
        $this->assertEquals(0, $result['applied_count']);
    }

    public function test_errors_include_recovery_hints(): void
    {
        // Given - Not found error
        $content = '<div>Content</div>';
        $edits = [
            ['old_string' => '<span>Missing</span>', 'new_string' => '<span>New</span>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertCount(1, $result['errors']);
        $error = $result['errors'][0];

        $this->assertArrayHasKey('recoverable', $error);
        $this->assertArrayHasKey('recovery_action', $error);
        $this->assertArrayHasKey('hint', $error);
        $this->assertTrue($error['recoverable']);
        $this->assertEquals('rebase', $error['recovery_action']);
        $this->assertNotEmpty($error['hint']);
    }

    public function test_multiple_matches_error_has_expand_anchor_hint(): void
    {
        // Given
        $content = '<p>Dup</p><p>Dup</p>';
        $edits = [
            ['old_string' => '<p>Dup</p>', 'new_string' => '<p>Unique</p>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $error = $result['errors'][0];
        $this->assertTrue($error['recoverable']);
        $this->assertEquals('expand_anchor', $error['recovery_action']);
        $this->assertStringContainsString('longer anchor', $error['hint']);
    }

    public function test_truncates_long_old_string_in_error(): void
    {
        // Given - Old string longer than 100 chars
        $longString = str_repeat('x', 150);
        $content = '<div>Content</div>';
        $edits = [
            ['old_string' => $longString, 'new_string' => 'short'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertCount(1, $result['errors']);
        $preview = $result['errors'][0]['old_string_preview'];
        $this->assertLessThanOrEqual(100, strlen($preview));
        $this->assertStringEndsWith('...', $preview);
    }

    public function test_invalid_edit_with_empty_old_string(): void
    {
        // Given
        $content = '<div>Content</div>';
        $edits = [
            ['old_string' => '', 'new_string' => 'something'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('invalid_edit', $result['errors'][0]['type']);
        $this->assertFalse($result['errors'][0]['recoverable']);
    }

    // ==========================================
    // PARTIAL SUCCESS TESTS
    // ==========================================

    public function test_continues_applying_after_failed_edit(): void
    {
        // Given - First edit fails, second and third succeed
        $content = '<div><p>Keep</p><span>Change</span></div>';
        $edits = [
            ['old_string' => '<missing>NotFound</missing>', 'new_string' => '<new>Replaced</new>'],
            ['old_string' => '<p>Keep</p>', 'new_string' => '<p>Kept</p>'],
            ['old_string' => '<span>Change</span>', 'new_string' => '<span>Changed</span>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertFalse($result['success']); // Overall failure due to first edit
        $this->assertStringContainsString('<p>Kept</p>', $result['content']); // Second edit applied
        $this->assertStringContainsString('<span>Changed</span>', $result['content']); // Third edit applied
        $this->assertEquals(2, $result['applied_count']);
    }

    public function test_success_is_false_when_any_edit_fails(): void
    {
        // Given - 2 succeed, 1 fails
        $content = '<div><p>A</p><p>B</p></div>';
        $edits = [
            ['old_string' => '<p>A</p>', 'new_string' => '<p>AA</p>'],
            ['old_string' => '<p>Missing</p>', 'new_string' => '<p>New</p>'],
            ['old_string' => '<p>B</p>', 'new_string' => '<p>BB</p>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_applied_count_reflects_successful_edits(): void
    {
        // Given - Middle edit fails
        $content = '<div><a>1</a><b>2</b><c>3</c></div>';
        $edits = [
            ['old_string' => '<a>1</a>', 'new_string' => '<a>one</a>'],
            ['old_string' => '<x>missing</x>', 'new_string' => '<x>new</x>'],
            ['old_string' => '<c>3</c>', 'new_string' => '<c>three</c>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertEquals(2, $result['applied_count']);
        $this->assertCount(1, $result['errors']);
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    public function test_empty_edits_array_returns_success(): void
    {
        // Given
        $content = '<div>Original content</div>';
        $edits = [];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals($content, $result['content']); // Content unchanged
        $this->assertEmpty($result['errors']);
        $this->assertEquals(0, $result['applied_count']);
    }

    public function test_empty_content_with_edits(): void
    {
        // Given
        $content = '';
        $edits = [
            ['old_string' => '<p>Something</p>', 'new_string' => '<p>New</p>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertFalse($result['success']);
        $this->assertEquals('', $result['content']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('not_found', $result['errors'][0]['type']);
    }

    public function test_handles_special_regex_characters(): void
    {
        // Given - Content with regex special characters: [ ] . * + ? ^ $ { } | \ ( )
        $content = '<div data-pattern="[0-9]+" class="box">Value: $100.00</div>';
        $edits = [
            ['old_string' => '[0-9]+', 'new_string' => '[a-z]+'],
            ['old_string' => '$100.00', 'new_string' => '$200.00'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('[a-z]+', $result['content']);
        $this->assertStringContainsString('$200.00', $result['content']);
        $this->assertEquals(2, $result['applied_count']);
    }

    public function test_handles_backslashes_in_content(): void
    {
        // Given - Content with backslashes (common in file paths or escape sequences)
        $content = '<code>path\\to\\file</code>';
        $edits = [
            ['old_string' => 'path\\to\\file', 'new_string' => 'new\\path\\here'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<code>new\\path\\here</code>', $result['content']);
    }

    public function test_skips_no_op_edits_silently(): void
    {
        // Given - Edit where old_string equals new_string
        $content = '<div>Content</div>';
        $edits = [
            ['old_string' => 'Content', 'new_string' => 'Content'], // No-op
            ['old_string' => '<div>', 'new_string' => '<section>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<section>Content</div>', $result['content']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['applied_count']); // Only the second edit counted
    }

    public function test_handles_unicode_content(): void
    {
        // Given - Content with unicode characters
        $content = '<p>Hello 世界! Привет мир! 🌍</p>';
        $edits = [
            ['old_string' => '世界', 'new_string' => 'World'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<p>Hello World! Привет мир! 🌍</p>', $result['content']);
    }

    public function test_handles_html_entities(): void
    {
        // Given - Content with HTML entities
        $content = '<p>&lt;script&gt;alert("XSS")&lt;/script&gt;</p>';
        $edits = [
            ['old_string' => '&lt;script&gt;', 'new_string' => '&lt;div&gt;'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('&lt;div&gt;', $result['content']);
    }

    public function test_handles_multiline_replacement(): void
    {
        // Given - Multi-line content
        $content = <<<HTML
<div class="card">
  <h2>Title</h2>
  <p>Description</p>
</div>
HTML;
        $replacement = <<<HTML
<article class="card">
  <h1>New Title</h1>
  <p>New Description</p>
</article>
HTML;
        $edits = [
            ['old_string' => $content, 'new_string' => $replacement],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<article class="card">', $result['content']);
        $this->assertStringContainsString('<h1>New Title</h1>', $result['content']);
    }

    public function test_preserves_surrounding_content(): void
    {
        // Given
        $content = 'PREFIX<target>replace me</target>SUFFIX';
        $edits = [
            ['old_string' => '<target>replace me</target>', 'new_string' => '<target>replaced</target>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('PREFIX<target>replaced</target>SUFFIX', $result['content']);
    }

    public function test_whitespace_at_boundaries(): void
    {
        // Given - Needle has leading/trailing whitespace
        $content = "  <div>content</div>  ";
        $edits = [
            ['old_string' => '  <div>content</div>  ', 'new_string' => '<span>new</span>'],
        ];

        // When
        $result = $this->service->applyEdits($content, $edits);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals('<span>new</span>', $result['content']);
    }
}
