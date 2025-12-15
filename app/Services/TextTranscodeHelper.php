<?php

namespace App\Services;

use App\Services\Task\Runners\ImageToTextTranscoderTaskRunner;

/**
 * Helper for formatting text transcode content from StoredFiles.
 *
 * Handles platform-specific logic like preferring Image To Text Transcoder over OCR.
 */
class TextTranscodeHelper
{
    /**
     * Format text transcodes array into a combined string with separators.
     *
     * - Prefers LLM transcode over OCR (they serve the same purpose, LLM is higher quality)
     * - Includes all other text transcodes
     *
     * @param  array<string, string>  $transcodes  Array where key is transcode_name and value is content
     * @return string Formatted string with ----- separators between transcodes
     */
    public static function formatTextTranscodes(array $transcodes): string
    {
        if (empty($transcodes)) {
            return '';
        }

        // If both OCR and LLM transcodes exist, exclude OCR (LLM is higher quality)
        if (isset($transcodes[ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM]) && isset($transcodes[ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR])) {
            unset($transcodes[ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR]);
        }

        $output = [];
        foreach ($transcodes as $transcodeName => $content) {
            if ($content) {
                $output[] = "------ {$transcodeName} ------\n{$content}";
            }
        }

        return implode("\n\n", $output);
    }
}
