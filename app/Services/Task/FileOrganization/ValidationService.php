<?php

namespace App\Services\Task\FileOrganization;

use Newms87\Danx\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Validates file organization data structures for correctness.
 */
class ValidationService
{
    use HasDebugLogging;

    /**
     * Validate that no page_number appears in multiple groups.
     * Each page must belong to exactly ONE group.
     *
     * @param  array  $jsonContent  The artifact's json_content with groups
     *
     * @throws ValidationError if any page appears in multiple groups
     */
    public function validateNoDuplicatePages(array $jsonContent): void
    {
        $groups = $jsonContent['groups'] ?? [];

        if (empty($groups)) {
            return;
        }

        // Track which page_numbers we've seen and in which group
        $pageToGroup = [];

        foreach ($groups as $group) {
            $groupName = $group['name']  ?? 'Unknown';
            $files     = $group['files'] ?? [];

            foreach ($files as $fileData) {
                // Handle both old format (integer) and new format (object)
                if (is_int($fileData)) {
                    $pageNumber = $fileData;
                } else {
                    $pageNumber = $fileData['page_number'] ?? null;
                }

                if ($pageNumber === null) {
                    continue;
                }

                // Check if we've seen this page before
                if (isset($pageToGroup[$pageNumber])) {
                    $firstGroup = $pageToGroup[$pageNumber];
                    throw new ValidationError(
                        "Invalid file organization: Page $pageNumber appears in multiple groups.\n" .
                        "First group: '$firstGroup'\n" .
                        "Second group: '$groupName'\n\n" .
                        'Each page must belong to exactly ONE group. Please revise the grouping so that each page appears in only one group.',
                        400
                    );
                }

                // Record this page
                $pageToGroup[$pageNumber] = $groupName;
            }
        }

        static::logDebug('Validation passed: No duplicate pages found across ' . count($groups) . ' groups');
    }
}
