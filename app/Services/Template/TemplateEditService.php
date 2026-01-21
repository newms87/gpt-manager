<?php

namespace App\Services\Template;

use App\Traits\HasDebugLogging;

/**
 * Service for applying anchored replacement edits to HTML/CSS content.
 *
 * Handles whitespace normalization for matching since HTML/CSS are whitespace-insensitive.
 * Returns detailed errors for failed edits with recovery hints for the LLM.
 */
class TemplateEditService
{
    use HasDebugLogging;

    protected const int PREVIEW_LENGTH = 100;

    /**
     * Apply anchored replacement edits for HTML/CSS.
     * Whitespace is normalized for matching (HTML/CSS are whitespace-insensitive).
     *
     * @param  string  $content  The current content to modify
     * @param  array  $edits  Array of ['old_string' => ..., 'new_string' => ...]
     * @return array{success: bool, content: string, errors: array, applied_count: int}
     */
    public function applyEdits(string $content, array $edits): array
    {
        $errors       = [];
        $appliedCount = 0;

        foreach ($edits as $index => $edit) {
            $oldString = $edit['old_string'] ?? '';
            $newString = $edit['new_string'] ?? '';

            if ($oldString === '') {
                $errors[] = $this->createError($index, 'invalid_edit', $oldString, 'old_string cannot be empty');

                continue;
            }

            if ($oldString === $newString) {
                // Skip no-op edits silently
                continue;
            }

            $result = $this->applySingleEdit($content, $oldString, $newString, $index);

            if ($result['success']) {
                $content = $result['content'];
                $appliedCount++;
                static::logDebug('Edit applied successfully', [
                    'index'              => $index,
                    'old_string_preview' => $this->truncate($oldString, self::PREVIEW_LENGTH),
                ]);
            } else {
                $errors[] = $result['error'];
                static::logDebug('Edit failed', [
                    'index' => $index,
                    'type'  => $result['error']['type'],
                    'hint'  => $result['error']['hint'],
                ]);
            }
        }

        return [
            'success'       => empty($errors),
            'content'       => $content,
            'errors'        => $errors,
            'applied_count' => $appliedCount,
        ];
    }

    /**
     * Apply a single edit to the content.
     *
     * @return array{success: bool, content?: string, error?: array}
     */
    protected function applySingleEdit(string $content, string $oldString, string $newString, int $index): array
    {
        // First try exact match
        $exactMatches = $this->findExactMatches($content, $oldString);

        if (count($exactMatches) === 1) {
            // Single exact match - perform replacement
            $newContent = substr_replace($content, $newString, $exactMatches[0], strlen($oldString));

            return ['success' => true, 'content' => $newContent];
        }

        if (count($exactMatches) > 1) {
            return [
                'success' => false,
                'error'   => $this->createMultipleMatchesError($index, $oldString, count($exactMatches)),
            ];
        }

        // No exact match - try with whitespace normalization
        return $this->findWithNormalizedWhitespace($content, $oldString, $newString, $index);
    }

    /**
     * Find with whitespace normalization (for HTML/CSS).
     * Collapses all whitespace sequences to single space for matching.
     *
     * @return array{success: bool, content?: string, error?: array}
     */
    protected function findWithNormalizedWhitespace(
        string $content,
        string $needle,
        string $replacement,
        int $index
    ): array {
        // Build a regex pattern that matches the needle with flexible whitespace
        $pattern = $this->buildWhitespaceFlexiblePattern($needle);

        // Find all matches
        $matchCount = preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        if ($matchCount === 0) {
            return [
                'success' => false,
                'error'   => $this->createNotFoundError($index, $needle),
            ];
        }

        if ($matchCount > 1) {
            return [
                'success' => false,
                'error'   => $this->createMultipleMatchesError($index, $needle, $matchCount),
            ];
        }

        // Single match found - replace it
        $matchedText   = $matches[0][0][0];
        $matchPosition = $matches[0][0][1];

        $newContent = substr_replace($content, $replacement, $matchPosition, strlen($matchedText));

        static::logDebug('Whitespace-normalized match found', [
            'index'          => $index,
            'original_match' => $this->truncate($matchedText, self::PREVIEW_LENGTH),
        ]);

        return ['success' => true, 'content' => $newContent];
    }

    /**
     * Build a regex pattern that matches the needle with flexible whitespace.
     *
     * This converts a string into a pattern where any whitespace sequence
     * in the needle matches any whitespace sequence in the content.
     */
    protected function buildWhitespaceFlexiblePattern(string $needle): string
    {
        // Split on whitespace while keeping track of where splits occurred
        $parts = preg_split('/\s+/', $needle, -1, PREG_SPLIT_NO_EMPTY);

        // Escape each part for regex
        $escapedParts = array_map(fn($part) => preg_quote($part, '/'), $parts);

        // Join with flexible whitespace pattern (\s+ matches 1 or more whitespace chars)
        $pattern = implode('\s+', $escapedParts);

        // Handle leading/trailing whitespace in original needle
        $hasLeadingWhitespace  = preg_match('/^\s/', $needle);
        $hasTrailingWhitespace = preg_match('/\s$/', $needle);

        if ($hasLeadingWhitespace) {
            $pattern = '\s+' . $pattern;
        }
        if ($hasTrailingWhitespace) {
            $pattern = $pattern . '\s+';
        }

        return '/' . $pattern . '/s';
    }

    /**
     * Find all exact matches of needle in content.
     *
     * @return array<int> Array of positions where needle was found
     */
    protected function findExactMatches(string $content, string $needle): array
    {
        $positions = [];
        $offset    = 0;

        while (($pos = strpos($content, $needle, $offset)) !== false) {
            $positions[] = $pos;
            $offset      = $pos + 1;
        }

        return $positions;
    }

    /**
     * Create an error response for when anchor is not found.
     */
    protected function createNotFoundError(int $index, string $oldString): array
    {
        return [
            'index'              => $index,
            'type'               => 'not_found',
            'old_string_preview' => $this->truncate($oldString, self::PREVIEW_LENGTH),
            'recoverable'        => true,
            'recovery_action'    => 'rebase',
            'hint'               => 'The anchor text was not found in the content. The content may have changed since you last saw it. Please re-read the current content and provide an updated anchor.',
        ];
    }

    /**
     * Create an error response for multiple matches.
     */
    protected function createMultipleMatchesError(int $index, string $oldString, int $count): array
    {
        return [
            'index'              => $index,
            'type'               => 'multiple_matches',
            'old_string_preview' => $this->truncate($oldString, self::PREVIEW_LENGTH),
            'count'              => $count,
            'recoverable'        => true,
            'recovery_action'    => 'expand_anchor',
            'hint'               => "The anchor text was found {$count} times in the content. Please provide a longer anchor that includes more surrounding context to make it unique.",
        ];
    }

    /**
     * Create a generic error response.
     */
    protected function createError(int $index, string $type, string $oldString, string $hint): array
    {
        return [
            'index'              => $index,
            'type'               => $type,
            'old_string_preview' => $this->truncate($oldString, self::PREVIEW_LENGTH),
            'recoverable'        => false,
            'hint'               => $hint,
        ];
    }

    /**
     * Truncate text to specified length with ellipsis.
     */
    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
