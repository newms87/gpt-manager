<?php

namespace Tests\Unit\Services;

use App\Services\Task\Runners\ImageToTextTranscoderTaskRunner;
use App\Services\TextTranscodeHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TextTranscodeHelperTest extends TestCase
{
    // ==========================================
    // EMPTY ARRAY TESTS
    // ==========================================

    #[Test]
    public function format_text_transcodes_with_empty_array_returns_empty_string(): void
    {
        // Given
        $transcodes = [];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        $this->assertEquals('', $result);
    }

    // ==========================================
    // SINGLE TRANSCODE TESTS
    // ==========================================

    #[Test]
    public function format_text_transcodes_with_single_transcode_formats_correctly(): void
    {
        // Given
        $transcodes = [
            'Simple Text' => 'This is the content',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        $expected = "------ Simple Text ------\nThis is the content";
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function format_text_transcodes_with_single_transcode_with_multiline_content(): void
    {
        // Given
        $transcodes = [
            'Multiline Content' => "Line 1\nLine 2\nLine 3",
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        $expected = "------ Multiline Content ------\nLine 1\nLine 2\nLine 3";
        $this->assertEquals($expected, $result);
    }

    // ==========================================
    // MULTIPLE TRANSCODE TESTS
    // ==========================================

    #[Test]
    public function format_text_transcodes_with_multiple_transcodes_joins_with_double_newline(): void
    {
        // Given
        $transcodes = [
            'First Transcode'  => 'First content',
            'Second Transcode' => 'Second content',
            'Third Transcode'  => 'Third content',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        $expected = "------ First Transcode ------\nFirst content\n\n"
            . "------ Second Transcode ------\nSecond content\n\n"
            . "------ Third Transcode ------\nThird content";
        $this->assertEquals($expected, $result);
    }

    // ==========================================
    // OCR VS LLM TRANSCODE LOGIC TESTS
    // ==========================================

    #[Test]
    public function format_text_transcodes_excludes_ocr_when_llm_transcode_exists(): void
    {
        // Given
        $transcodes = [
            ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR  => 'OCR content (lower quality)',
            ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM  => 'LLM content (higher quality)',
            'Other Transcode'                                    => 'Other content',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        // Should NOT contain OCR content
        $this->assertStringNotContainsString('OCR content', $result);
        $this->assertStringNotContainsString(ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR, $result);

        // Should contain LLM content
        $this->assertStringContainsString('LLM content (higher quality)', $result);
        $this->assertStringContainsString(ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM, $result);

        // Should contain other transcode
        $this->assertStringContainsString('Other content', $result);
        $this->assertStringContainsString('Other Transcode', $result);
    }

    #[Test]
    public function format_text_transcodes_keeps_ocr_when_llm_transcode_does_not_exist(): void
    {
        // Given
        $transcodes = [
            ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR  => 'OCR content',
            'Other Transcode'                                    => 'Other content',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        // Should contain OCR content (since LLM doesn't exist)
        $this->assertStringContainsString('OCR content', $result);
        $this->assertStringContainsString(ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR, $result);

        // Should contain other transcode
        $this->assertStringContainsString('Other content', $result);
        $this->assertStringContainsString('Other Transcode', $result);
    }

    #[Test]
    public function format_text_transcodes_keeps_llm_when_ocr_does_not_exist(): void
    {
        // Given
        $transcodes = [
            ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM  => 'LLM content',
            'Other Transcode'                                    => 'Other content',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        // Should contain LLM content
        $this->assertStringContainsString('LLM content', $result);
        $this->assertStringContainsString(ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM, $result);

        // Should contain other transcode
        $this->assertStringContainsString('Other content', $result);
        $this->assertStringContainsString('Other Transcode', $result);
    }

    // ==========================================
    // EDGE CASE TESTS
    // ==========================================

    #[Test]
    public function format_text_transcodes_skips_empty_content(): void
    {
        // Given
        $transcodes = [
            'First Transcode'  => 'Has content',
            'Empty Transcode'  => '',
            'Null Transcode'   => null,
            'Second Transcode' => 'Also has content',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        // Should contain transcodes with content
        $this->assertStringContainsString('Has content', $result);
        $this->assertStringContainsString('Also has content', $result);

        // Should NOT contain empty transcode headers
        $this->assertStringNotContainsString('Empty Transcode', $result);
        $this->assertStringNotContainsString('Null Transcode', $result);
    }

    #[Test]
    public function format_text_transcodes_with_only_empty_content_returns_empty_string(): void
    {
        // Given
        $transcodes = [
            'Empty 1' => '',
            'Empty 2' => null,
            'Empty 3' => '',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        $this->assertEquals('', $result);
    }

    #[Test]
    public function format_text_transcodes_with_special_characters_in_names_and_content(): void
    {
        // Given
        $transcodes = [
            'Transcode w/ Special: Chars!' => 'Content with <html> & "quotes"',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        $expected = "------ Transcode w/ Special: Chars! ------\nContent with <html> & \"quotes\"";
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function format_text_transcodes_with_whitespace_only_content_is_kept(): void
    {
        // Given
        $transcodes = [
            'Whitespace' => '   ',
        ];

        // When
        $result = TextTranscodeHelper::formatTextTranscodes($transcodes);

        // Then
        // Whitespace is truthy, so it should be included
        $expected = "------ Whitespace ------\n   ";
        $this->assertEquals($expected, $result);
    }
}
