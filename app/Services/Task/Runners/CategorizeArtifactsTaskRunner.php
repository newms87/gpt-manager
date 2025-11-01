<?php

namespace App\Services\Task\Runners;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\Task\TaskProcessDispatcherService;
use App\Services\Task\TaskProcessRunnerService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Throwable;

/**
 * The runner processes all artifacts and applies category classification based on the user's prompt.
 * Behavior summary:
 *
 * - Each input artifact will be assigned a `__category` field (in JSON) with the category determined by the process.
 * - After all task processes finish:
 *   - Artifacts with the `__exclude` category will be removed from the final output.
 *   - Artifacts will be sorted based on their `position` field.
 *   - Any artifact categorized as `__unknown` will attempt to be matched with other artifact categories that were
 *   identified before and after artifact in the sequence sorted list.
 * - The final list of artifacts (after filtering and sorting) will be set as the output.
 */
class CategorizeArtifactsTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Categorize Artifacts';

    // Special category constants
    const string
        CATEGORY_UNKNOWN = '__unknown',
        CATEGORY_EXCLUDE = '__exclude';

    /**
     * Initial activity message and
     */
    public function prepareProcess(): void
    {
        parent::prepareProcess();

        $this->activity('Preparing to categorize artifacts' . ($this->isSequentialMode() ? ' in sequential mode' : ''), 5);
    }

    public function run(): void
    {
        $taskDefinition = $this->taskRun->taskDefinition;
        $agent          = $taskDefinition->agent;

        // Make sure to include page numbers in the agent thread so the agent can reference them
        $this->includePageNumbersInThread = true;

        // Get all input artifacts
        $inputArtifacts = $this->taskProcess->inputArtifacts;
        $totalArtifacts = $inputArtifacts->count();

        if ($totalArtifacts === 0) {
            $this->activity('No artifacts to categorize', 100);
            $this->complete([]);

            return;
        }

        $this->activity("Using agent to categorize $totalArtifacts artifacts: " . $agent->name, 10);

        // Resolve the allowed categories from the artifacts' json content
        $allowedCategoryList = $this->getAllowedCategoryList($inputArtifacts);

        // Resolve the pages in each category
        $categoriesWithPages = $this->resolveCategoriesWithPages($inputArtifacts, $allowedCategoryList);

        // Apply the agent's assigned categories to the artifacts
        $this->applyCategories($categoriesWithPages, $inputArtifacts);

        $this->activity('Categorization complete', 95);

        // Complete the task with the input artifacts
        // Note: The final processing of artifacts (sorting, category matching, etc.)
        // will be done when all task processes are complete
        $this->complete($inputArtifacts);
    }

    /**
     * Resolve the categories w/ page numbers assigned for each of the artifacts
     */
    private function resolveCategoriesWithPages($inputArtifacts, array $allowedCategoryList)
    {
        $pageNumbers = $inputArtifacts->pluck('position')->toArray();

        // If there is only 1 allowed category, then we can skip the agent and just assign that category to all artifacts
        if (count($allowedCategoryList) === 1) {
            $firstCategory = reset($allowedCategoryList);
            $this->activity("Only 1 category found, applying automatically: $firstCategory", 80);

            return [
                [
                    'category' => $firstCategory,
                    'pages'    => $pageNumbers,
                ],
            ];
        }

        // Ask the agent for the categories to assign to the artifacts
        $schema         = $this->createCategorySchema($allowedCategoryList);
        $categoryPrompt = $this->createCategoryPrompt($allowedCategoryList, $pageNumbers);

        // Prepare the agent thread
        $agentThread = $this->setupAgentThread($this->taskProcess->inputArtifacts()->get());
        app(ThreadRepository::class)->addMessageToThread($agentThread, $categoryPrompt);

        $correctionAttemptsRemaining = $this->config('correction_attempts', 2);

        do {
            static::logDebug("Categorizing artifacts... $correctionAttemptsRemaining attempts remaining");
            $categoryArtifact    = $this->runAgentThreadWithSchema($agentThread, $schema);
            $categoriesWithPages = $categoryArtifact->json_content['categories'] ?? [];

            // If the list is not strictly defined, we can skip validation
            if (!$allowedCategoryList) {
                return $categoriesWithPages;
            }

            // If there is a strict list of categories, validate the agent has responded correctly
            try {
                $this->validateCategoryResponse($allowedCategoryList, $categoriesWithPages, $pageNumbers);

                return $categoriesWithPages;
            } catch (Throwable $throwable) {
                // If the agent was unable to provide a valid response, we will ask it to try again
                static::logDebug('Invalid response: ' . $throwable->getMessage());

                if ($correctionAttemptsRemaining === 0) {
                    // If we have no more attempts remaining, we will throw the error
                    throw $throwable;
                }

                // Add the error message to the thread so the agent can see it
                app(ThreadRepository::class)->addMessageToThread($agentThread, $throwable->getMessage());
            }
        } while ($correctionAttemptsRemaining-- > 0);

        // If we reach here, the agent was unable to provide a valid response
        throw new ValidationError('Agent was unable to provide a valid response for the categories.');
    }

    /**
     * The list of categories that are allowed, resolved from the current list of categories on the artifacts (if any
     * exist)
     *
     * NOTE: This is useful for when the agent has already identified categories for some artifacts, and we are trying
     * to resolve The categories for the rest of the artifacts that are related in sequence
     *
     * @param  Artifact[]  $artifacts
     */
    private function getAllowedCategoryList($artifacts): array
    {
        $allowedCategoryList = [];

        foreach ($artifacts as $inputArtifact) {
            // If the artifact has a category, add it to the list
            $category = $inputArtifact->json_content['__category'] ?? null;
            if ($category && !in_array($category, [self::CATEGORY_EXCLUDE, self::CATEGORY_UNKNOWN])) {
                $allowedCategoryList[$category] = $category;
            }
        }

        return $allowedCategoryList;
    }

    /**
     * Validate the category response from the agent
     *  - verify that all category responses were in the allowed category list
     *  - verify that all pages were assigned to a category
     */
    private function validateCategoryResponse(array $allowedCategoryList, array $categoriesWithPages, array $pageNumbers): void
    {
        $categoryNames = array_map(fn($category) => $category['category'], $categoriesWithPages);

        // Verify the categories are in the allowed category list
        foreach ($categoryNames as $categoryName) {
            if (empty($allowedCategoryList[$categoryName])) {
                throw new ValidationError("The category '$categoryName' is not in the list of allowed categories.");
            }
        }

        // Check that all pages are added only 1 time and that the page actually exists
        $resolvedPages = [];
        foreach ($categoriesWithPages as $categoryItem) {
            foreach ($categoryItem['pages'] as $page) {
                if (!empty($resolvedPages[$page])) {
                    throw new ValidationError("Page number '$page' has been assigned to multiple categories.");
                }

                if (!in_array($page, $pageNumbers)) {
                    throw new ValidationError("P number '$page' is not valid. Valid numbers are: " . json_encode($pageNumbers));
                }

                $resolvedPages[$page] = true;
            }
        }

        // Make sure all pages have been assigned
        if (count($resolvedPages) !== count($pageNumbers)) {
            $diffPageNumbers = array_values(array_diff($pageNumbers, array_keys($resolvedPages)));
            throw new ValidationError('Not all page numbers have been assigned to a category. ' .
                'You must assign all page numbers to a category, even if you are not 100% sure.' .
                'Missing page numbers: ' . json_encode($diffPageNumbers));
        }
    }

    /**
     * Check if the task is running in sequential mode
     */
    protected function isSequentialMode(): bool
    {
        return $this->taskRun->taskDefinition->task_runner_config['sequential_mode'] ?? true;
    }

    /**
     * Apply the category to the artifact's JSON content
     */
    protected function applyCategories(array $categoryList, Collection $artifacts): void
    {
        foreach ($categoryList as $categoryItem) {
            $category = $categoryItem['category'] ?? self::CATEGORY_UNKNOWN;

            // If any part of the category name has the unknown flag, the whole category is unknown
            if (str_contains($category, self::CATEGORY_UNKNOWN)) {
                $category = self::CATEGORY_UNKNOWN;
            }

            $pages = $categoryItem['pages'] ?? [];

            foreach ($pages as $page) {
                // Find the artifact with the given page number
                /** @var Artifact|null $artifact */
                $artifact = $artifacts->firstWhere('position', $page);

                if ($artifact) {
                    // Add the category to the artifact's JSON content
                    $jsonContent               = $artifact->json_content ?? [];
                    $jsonContent['__category'] = $category;
                    $artifact->json_content    = $jsonContent;
                    $artifact->save();
                }
            }
        }
    }

    /**
     * Create the category prompt for the agent
     */
    protected function createCategoryPrompt($categoryList = [], $pageNumbers = []): string
    {
        // If a category list is given, then the category MUST fall in one of the items in the list no matter what (even if it doesn't really make sense, better to have a defined category than not, so using best guess here)
        if ($categoryList) {
            $categoryDescription = 'Classify each artifact (identified by page number) by selecting the best-matching category from the provided list. ' .
                'You must choose one of the categories from the list, even if none of them seem like a perfect fit—use your best judgment to select the closest match. ' .
                "Based on the artifact content, if you believe a category in the list is a duplicate of another category in the list (ie: the names are similar, different names referring to the same person, place or thing, etc.), decide which category to keep and disregard the similar category name (DO NOT USE)\n\n" .
                "Categories:\n";

            foreach ($categoryList as $category) {
                $categoryDescription .= "* $category\n";
            }

            $pagesDescription = 'Then provide the list of page numbers for each artifact that belongs to the category. ' .
                'You may include pages even if you are not completely certain, as long as the chosen category is the best available option from the list. Each of these page numbers must appear in exactly 1 category: ' . json_encode($pageNumbers);

            return $categoryDescription . "\n\n" . $pagesDescription;
        } else {
            // Build the category description based on user defined categories in the prompt, while only defining a category if 100% sure.
            return 'Classify the artifact by choosing the most appropriate category. ' .
                "In addition to user-defined categories, you may also use the following options:\n\n" .
                '* ' . self::CATEGORY_UNKNOWN . ": Use this if you're not 100% confident about the category.\n" .
                '* ' . self::CATEGORY_EXCLUDE . ': Use this to exclude the artifact entirely from the output if the user has requested artifacts to be excluded under certain conditions.';
        }
    }

    /**
     * Create a schema definition for the category response
     */
    protected function createCategorySchema($categoryList = []): SchemaDefinition
    {
        if ($categoryList) {
            $categoryDescription = 'One of the following categories: ' . implode(', ', $categoryList) . '. Use the users description of the categories to identify which pages belong to which category';
        } else {
            $categoryDescription = 'The best match based on the users description for the categories. If unsure you can use ' . self::CATEGORY_UNKNOWN . ".\n" .
                ' If requested to exclude artifacts, you may also use ' . self::CATEGORY_EXCLUDE . ' to exclude the artifact entirely from the output.';
        }

        return SchemaDefinition::make([
            'name'   => 'Category',
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'categories' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'category' => [
                                    'type'        => 'string',
                                    'description' => $categoryDescription,
                                ],
                                'pages'    => [
                                    'type'        => 'array',
                                    'description' => 'The list of page numbers that belong to the category. Each page can only be assigned to one category',
                                    'items'       => ['type' => 'number'],
                                ],
                            ],
                            'required'   => ['category', 'pages'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Process the final list of artifacts after all task processes are complete
     * 1. Sort artifacts by position
     * 2. Remove artifacts with __exclude category
     * 3. Perform category matching
     */
    public function afterAllProcessesCompleted(): void
    {
        // Get all output artifacts from all processes
        $finalOutputArtifacts = $this->taskRun->outputArtifacts;

        // 1. Sort all the artifacts
        static::logDebug('Sorting artifacts by position');
        $finalOutputArtifacts = $finalOutputArtifacts->sort(fn(Artifact $a, Artifact $b) => $a->position <=> $b->position);

        // 2. Exclude artifacts with __exclude category
        $countBefore          = $finalOutputArtifacts->count();
        $finalOutputArtifacts = $finalOutputArtifacts->filter(fn(Artifact $a) => ($a->json_content['__category'] ?? null) !== self::CATEGORY_EXCLUDE);
        $totalExcluded        = $countBefore - $finalOutputArtifacts->count();
        static::logDebug("Excluded $totalExcluded artifacts with __exclude category");

        // 2. Attempt category matching if the task is in sequential mode
        if ($this->isSequentialMode()) {
            $this->matchSequentialCategories($finalOutputArtifacts);
        }

        // Update the task run's output artifacts
        static::logDebug('Updating task run with ' . $finalOutputArtifacts->count() . ' output artifacts');
        $this->taskRun->outputArtifacts()->sync($finalOutputArtifacts->pluck('id'));
        $this->taskRun->updateRelationCounter('outputArtifacts');
    }

    /**
     * Collect sequential artifacts with the __unknown category and the previous and next resolved category into a group
     * And attempt to match the unknown categorized artifacts to a category based on its sequence in the group
     *
     * @param  Artifact[]  $artifacts
     */
    protected function matchSequentialCategories($artifacts): void
    {
        static::logDebug('Applying sequential categories to artifacts');

        /** @var Artifact[][] $categoryGroups */
        $categoryGroups = [];

        $groupKey = '';

        foreach ($artifacts as $artifact) {
            $category = $artifact->json_content['__category'] ?? null;

            if ($category === self::CATEGORY_EXCLUDE) {
                throw new Exception("Artifact with __exclude category found in sequential mode. This should not happen: $artifact");
            }

            // If category is unknown, add it to the current group to attempt to resolve with the previous and next known category
            if ($category === self::CATEGORY_UNKNOWN) {
                // Add the category
                $categoryGroups[$groupKey][] = $artifact;
            } else {
                // Add to the current group to see if previous artifacts belong to the same group
                $categoryGroups[$groupKey][] = $artifact;

                // If the category has changed, we need to start a new group
                if ($groupKey !== $category) {
                    // Changing the group key to start a new group (or add to an existing group if it already had existed)
                    $groupKey = $category;

                    // Add to the next group to see if subsequent artifacts belong to the same group
                    $categoryGroups[$groupKey][] = $artifact;
                }
            }
        }

        static::logDebug('Resolved categories: ' . json_encode(array_keys($categoryGroups)));

        $groupsWithUnknowns = [];
        foreach ($categoryGroups as $categoryGroupArtifacts) {
            foreach ($categoryGroupArtifacts as $categoryGroupArtifact) {
                if (($categoryGroupArtifact->json_content['__category'] ?? '') === self::CATEGORY_UNKNOWN) {
                    $groupsWithUnknowns[] = $categoryGroupArtifacts;
                    break;
                }
            }
        }

        $this->dispatchCategoryGroups($groupsWithUnknowns);
    }

    /**
     * Dispatch the category groups to the task process runner.
     * This will activate the task run again with new task processes to complete.
     */
    private function dispatchCategoryGroups(array $categoryGroups): void
    {
        static::logDebug('Dispatching ' . count($categoryGroups) . ' category groups for sequential matching');

        $taskProcesses = [];
        /** @var Artifact[] $categoryGroupArtifacts */
        foreach ($categoryGroups as $categoryGroupArtifacts) {
            $taskProcesses[] = TaskProcessRunnerService::prepare($this->taskRun, null, $categoryGroupArtifacts);
        }

        // Trigger dispatcher to pick up the new processes
        TaskProcessDispatcherService::dispatchForTaskRun($this->taskRun);
    }
}
