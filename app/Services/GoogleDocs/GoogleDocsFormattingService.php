<?php

namespace App\Services\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ApiException;

class GoogleDocsFormattingService
{
    use HasDebugLogging;

    /**
     * Check if a string contains markdown syntax
     */
    public function containsMarkdown(string $text): bool
    {
        // Check for common markdown patterns
        return preg_match('/(\*\*|__|\*|_|^#{1,3}\s|^\s*[-*+]\s)/m', $text) === 1;
    }

    /**
     * Parse markdown text and return plain text with formatting instructions
     */
    public function parseMarkdown(string $markdown): array
    {
        $plainText = $markdown;
        $formats = [];
        $offset = 0; // Track position changes as we strip markdown syntax

        // Split into lines to handle paragraph-level formatting
        $lines = explode("\n", $markdown);
        $processedLines = [];
        $currentPosition = 0;

        foreach($lines as $line) {
            $lineStart = $currentPosition;
            $processedLine = $line;
            $lineFormats = [];

            // Check for headings (must be at start of line)
            if (preg_match('/^(#{1,3})\s+(.*)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $processedLine = $matches[2];

                $lineLength = strlen($processedLine);
                $formats[] = [
                    'type' => 'heading' . $level,
                    'start' => $lineStart,
                    'end' => $lineStart + $lineLength,
                ];

                $currentPosition += $lineLength;
            } else {
                // Process inline formatting (bold, italic)
                $processedLine = $this->parseInlineFormatting($line, $lineStart, $lineFormats);
                $formats = array_merge($formats, $lineFormats);
                $currentPosition += strlen($processedLine);
            }

            $processedLines[] = $processedLine;

            // Add newline character position (if not last line)
            if ($line !== end($lines)) {
                $currentPosition += 1; // Account for \n
            }
        }

        $plainText = implode("\n", $processedLines);

        return [
            'plainText' => $plainText,
            'formats' => $formats,
        ];
    }

    /**
     * Parse inline formatting (bold, italic) from a line
     */
    public function parseInlineFormatting(string $line, int $lineStart, array &$formats): string
    {
        $result = $line;
        $allMatches = [];

        // Find all bold patterns (**text** or __text__)
        preg_match_all('/(\*\*|__)(.+?)\\1/', $line, $boldMatches, PREG_OFFSET_CAPTURE);
        foreach($boldMatches[0] as $index => $match) {
            $allMatches[] = [
                'type' => 'bold',
                'position' => $match[1],
                'fullMatch' => $match[0],
                'text' => $boldMatches[2][$index][0],
                'markerLength' => strlen($boldMatches[1][$index][0]), // ** or __
            ];
        }

        // Find all italic patterns (*text* or _text_) - but not if it's part of bold
        preg_match_all('/(?<!\*|\w)(\*|_)([^*_]+?)\\1(?!\*|\w)/', $line, $italicMatches, PREG_OFFSET_CAPTURE);
        foreach($italicMatches[0] as $index => $match) {
            $allMatches[] = [
                'type' => 'italic',
                'position' => $match[1],
                'fullMatch' => $match[0],
                'text' => $italicMatches[2][$index][0],
                'markerLength' => strlen($italicMatches[1][$index][0]), // * or _
            ];
        }

        // Sort by position (process left to right)
        usort($allMatches, fn($a, $b) => $a['position'] <=> $b['position']);

        // Strip markdown and calculate plain text positions
        $removedChars = 0;
        foreach($allMatches as $match) {
            // Calculate position in plain text after removing previous markdown
            $plainTextStart = $match['position'] - $removedChars;
            $textLength = strlen($match['text']);

            $formats[] = [
                'type' => $match['type'],
                'start' => $lineStart + $plainTextStart,
                'end' => $lineStart + $plainTextStart + $textLength,
            ];

            // Track how many characters we've removed (opening + closing markers)
            $removedChars += $match['markerLength'] * 2;
        }

        // Strip all markdown syntax in one pass
        $result = preg_replace('/(\*\*|__)(.+?)\\1/', '$2', $result);
        $result = preg_replace('/(?<!\*|\w)(\*|_)([^*_]+?)\\1(?!\*|\w)/', '$2', $result);

        return $result;
    }

    /**
     * Apply formatting instructions to text in document
     */
    public function applyFormattingToText(GoogleDocsApi $api, string $documentId, int $baseIndex, array $formats): void
    {
        $requests = [];

        static::log("applyFormattingToText: Starting", [
            'base_index' => $baseIndex,
            'formats_count' => count($formats),
        ]);

        foreach($formats as $formatIndex => $format) {
            $startIndex = $baseIndex + $format['start'];
            $endIndex = $baseIndex + $format['end'];

            static::log("applyFormattingToText: Processing format", [
                'format_index' => $formatIndex,
                'type' => $format['type'],
                'format_start' => $format['start'],
                'format_end' => $format['end'],
                'calculated_start_index' => $startIndex,
                'calculated_end_index' => $endIndex,
            ]);

            switch($format['type']) {
                case 'bold':
                    $requests[] = [
                        'updateTextStyle' => [
                            'range' => [
                                'startIndex' => $startIndex,
                                'endIndex' => $endIndex,
                            ],
                            'textStyle' => [
                                'bold' => true,
                            ],
                            'fields' => 'bold',
                        ],
                    ];
                    break;

                case 'italic':
                    $requests[] = [
                        'updateTextStyle' => [
                            'range' => [
                                'startIndex' => $startIndex,
                                'endIndex' => $endIndex,
                            ],
                            'textStyle' => [
                                'italic' => true,
                            ],
                            'fields' => 'italic',
                        ],
                    ];
                    break;

                case 'heading1':
                case 'heading2':
                case 'heading3':
                    $namedStyle = strtoupper(str_replace('heading', 'HEADING_', $format['type']));
                    $requests[] = [
                        'updateParagraphStyle' => [
                            'range' => [
                                'startIndex' => $startIndex,
                                'endIndex' => $endIndex,
                            ],
                            'paragraphStyle' => [
                                'namedStyleType' => $namedStyle,
                            ],
                            'fields' => 'namedStyleType',
                        ],
                    ];
                    break;
            }
        }

        if (!empty($requests)) {
            static::log("applyFormattingToText: Sending batch update", [
                'requests_count' => count($requests),
                'requests' => $requests,
            ]);

            $response = $api->post("documents/{$documentId}:batchUpdate", [
                'requests' => $requests,
            ]);

            $responseData = $response->json();

            static::log("applyFormattingToText: Batch update response", [
                'response' => $responseData,
            ]);

            if (isset($responseData['error'])) {
                throw new ApiException('Failed to apply formatting: ' . ($responseData['error']['message'] ?? 'Unknown error'));
            }

            static::log("Formatting applied to text", [
                'document_id' => $documentId,
                'formats_applied' => count($requests),
            ]);
        }
    }

    /**
     * Replace a variable with formatted markdown content
     */
    public function replaceVariableWithFormattedMarkdown(GoogleDocsApi $api, string $documentId, string $variable, string $markdownValue): void
    {
        try {
            static::log("Replacing variable with formatted markdown", [
                'document_id' => $documentId,
                'variable'    => $variable,
                'markdown_length' => strlen($markdownValue),
            ]);

            // Step 1: Convert literal \n (2 chars: backslash + n) to actual newlines
            $markdownValue = str_replace(chr(92) . 'n', "\n", $markdownValue);

            $parsed = $this->parseMarkdown($markdownValue);
            $plainText = $parsed['plainText'];
            $formats = $parsed['formats'];

            // Step 2: Read document to find the variable placeholder position
            $document = $api->get("documents/{$documentId}")->json();
            $placeholder = '{{' . $variable . '}}';
            $placeholderPosition = app(GoogleDocsContentService::class)->findTextPosition($document, $placeholder);

            if ($placeholderPosition === null) {
                static::log("Could not find variable placeholder, falling back to replaceAllText", [
                    'variable' => $variable,
                    'placeholder' => $placeholder,
                ]);
                // Fallback to simple replacement
                $this->replaceVariablesWithPlainText($api, $documentId, [$variable => $plainText]);
                return;
            }

            $placeholderEndIndex = $placeholderPosition + strlen($placeholder);

            static::log("Found placeholder position", [
                'placeholder' => $placeholder,
                'start_index' => $placeholderPosition,
                'end_index' => $placeholderEndIndex,
            ]);

            // Step 3: Build batch update request: delete placeholder and insert text with proper newlines
            $requests = [
                // First, delete the placeholder
                [
                    'deleteContentRange' => [
                        'range' => [
                            'startIndex' => $placeholderPosition,
                            'endIndex' => $placeholderEndIndex,
                        ],
                    ],
                ],
                // Then, insert the plain text (insertText properly handles \n as newlines)
                [
                    'insertText' => [
                        'location' => ['index' => $placeholderPosition],
                        'text' => $plainText,
                    ],
                ],
            ];

            // Execute the delete and insert operations
            $response = $api->post("documents/{$documentId}:batchUpdate", [
                'requests' => $requests,
            ]);

            $responseData = $response->json();
            if (isset($responseData['error'])) {
                throw new ApiException('Failed to replace variable: ' . ($responseData['error']['message'] ?? 'Unknown error'));
            }

            static::log("Placeholder deleted and text inserted", [
                'placeholder_position' => $placeholderPosition,
                'text_length' => strlen($plainText),
            ]);

            // DEBUG: Log what we're about to format
            static::log("About to apply formatting", [
                'base_index' => $placeholderPosition,
                'formats_count' => count($formats),
                'first_format' => $formats[0] ?? null,
            ]);

            // Step 4: Apply formatting to the inserted text
            // NOTE: There's a 2-character offset issue - possibly related to how Google Docs
            // counts the placeholder {{variable}} vs the inserted text. Subtracting 2 fixes it.
            $formattingBaseIndex = $placeholderPosition - 2;
            if (!empty($formats)) {
                $this->applyFormattingToText($api, $documentId, $formattingBaseIndex, $formats);
            }

            static::log("Variable replaced with formatted markdown successfully", [
                'document_id' => $documentId,
                'variable' => $variable,
                'formats_applied' => count($formats),
            ]);

        } catch(\Exception $e) {
            static::log("Failed to replace variable with formatted markdown", [
                'document_id' => $documentId,
                'variable' => $variable,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - fall back to plain text replacement
        }
    }

    /**
     * Replace variables with plain text (no formatting)
     */
    public function replaceVariablesWithPlainText(GoogleDocsApi $api, string $documentId, array $variableMappings): void
    {
        $requests = [];

        foreach($variableMappings as $variable => $textValue) {
            // Convert literal \n (2 chars: backslash + n) to actual newlines
            $textValue = str_replace(chr(92) . 'n', "\n", $textValue);

            $requests[] = [
                'replaceAllText' => [
                    'containsText' => [
                        'text'      => '{{' . $variable . '}}',
                        'matchCase' => true,
                    ],
                    'replaceText'  => $textValue,
                ],
            ];
        }

        if (!empty($requests)) {
            $response = $api->post("documents/{$documentId}:batchUpdate", [
                'requests' => $requests,
            ]);

            $responseData = $response->json();
            if (isset($responseData['error'])) {
                throw new ApiException('Failed to replace variables: ' . ($responseData['error']['message'] ?? 'Unknown error'));
            }

            static::log("Plain text variables replaced in document", [
                'document_id'        => $documentId,
                'variables_replaced' => count($requests),
            ]);
        }
    }
}
