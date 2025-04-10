<?php

namespace App\Services\Task\Runners;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\Task\TaskProcessRunnerService;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Throwable;

/**
 * The input artifacts are expected to have either meta or JSON content with a category resolved.
 * At least 1 input artifact must have a non-empty value for the category. The sequential matcher sorts all artifacts
 * by their position and attempts to match the artifacts w/ missing category values to the artifacts that have
 * non-empty category values either before or after the artifact in the sequence. This is performed using an LLM agent
 * with a prompt to attempt to intelligently match artifacts together into category groups.
 *
 *   - Artifacts with the `__exclude` category will be removed from the final output.
 *   - Artifacts will be sorted based on their `position` field.
 *   - Any artifact categorized with an empty value will attempt to be matched with other artifact categories that were
 *       identified before and after artifact in the sequence sorted list.
 *   - The final list of artifacts (after filtering and sorting) will be set as the output.
 */
class SequentialCategoryMatcherTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Sequential Category Matcher';

    // Special category constants
    const string CATEGORY_EXCLUDE = '__exclude';

    protected string $classificationKey = '';

    /**
     * Initial activity message and
     */
    public function prepareProcess(): void
    {
        parent::prepareProcess();

        $this->activity('Preparing to resolve missing classifications in an ordered sequence of artifacts ', 5);
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

        // Resolve the fragment selector
        $fragmentSelector        = $this->resolveFragmentSelector();
        $this->classificationKey = $this->resolveClassificationKey($fragmentSelector['children']['classification']);

        $categoryGroups = $this->resolveCategoryGroups($inputArtifacts, $fragmentSelector);

        $this->activity('Auto-classifying first and last groups of artifacts (if they have only 1 category)...', 15);
        $unresolvedCategoryGroups = $this->autoClassifyFirstAndLastGroups($categoryGroups, $fragmentSelector);

        // If we have multiple groups to organize, we will dispatch them in separate tasks to run the operation in parallel
        if (count($unresolvedCategoryGroups) > 1) {
            $this->activity("Dispatching " . count($unresolvedCategoryGroups) . " category groups for sequential matching", 20);
            $this->dispatchCategoryGroups($unresolvedCategoryGroups);
            $this->complete();

            return;
        }

        $artifacts = $unresolvedCategoryGroups[0];

        $this->activity('Performing sequential category matching on ' . count($artifacts) . ' artifacts...', 20);
        $this->performSequentialMatching($artifacts, $fragmentSelector);
        $this->activity('Sequential category matching complete', 95);

        // Complete the task with all the artifacts w/ categories updated in the meta
        $this->complete($artifacts);
    }

    /**
     * Auto classify any category groups that have exactly 1 available category in the group. These are special cases
     * that can be resolved quickly and do not require an LLM to run to classify the artifacts
     */
    private function autoClassifyFirstAndLastGroups(array $categoryGroups, $fragmentSelector): array
    {
        $unresolvedGroups = [];

        $lastIndex = count($categoryGroups) - 1;

        // Best way to find artifacts w/
        foreach($categoryGroups as $index => $categoryGroup) {
            // If we're looking at the first or last group, try to auto-classify
            if ($index === 0 || $index === $lastIndex) {
                $categoryList = $this->getAllowedCategoryList($categoryGroup, $fragmentSelector);

                // if there is only 1 category in the list, we can apply the category to all artifacts in the group
                if (count($categoryList) === 1) {
                    $this->performSequentialMatching($categoryGroup, $fragmentSelector);

                    // This group is resolved so do not add to the unresolved list
                    continue;
                }
            }

            // If we made it here, the group is still unresolved
            $unresolvedGroups[] = $categoryGroup;
        }

        return $unresolvedGroups;
    }

    /**
     * Perform the sequential matching on the group of artifacts to update any artifacts w/ missing classifications
     * by comparing them to the artifacts that have been classified before and after them in the sequence.
     */
    private function performSequentialMatching($artifacts, array $fragmentSelector): void
    {
        // Resolve the allowed categories from the artifacts' meta
        $allowedCategoryList = $this->getAllowedCategoryList($artifacts, $fragmentSelector);

        // Resolve the pages in each category
        $categoriesWithPages = $this->classifyArtifacts($artifacts, $allowedCategoryList);

        // Apply the agent's assigned categories to the artifacts
        $this->applyCategories($categoriesWithPages, $artifacts);
    }

    /**
     * Resolve the fragment selector from the task definition
     */
    private function resolveFragmentSelector(): array
    {
        $fragmentSelector = $this->taskDefinition->schemaAssociations->first()?->schemaFragment->fragment_selector ?? [];

        // Always nest the fragment under the classification key
        return ['type' => 'object', 'children' => ['classification' => $fragmentSelector]];
    }

    /**
     * Resolve the classification key from the fragment selector using the property names of all the scalars in the
     * selector
     */
    private function resolveClassificationKey(array $fragmentSelector): string
    {
        // Default to using the defined classification key if given in the config
        $key = $this->config('classification_key');

        if ($key) {
            return $key;
        }

        // Otherwise, resolve the classification as a composite of scalar keys in the fragment
        $children = $fragmentSelector['children'] ?? [];
        $key      = '';

        foreach($children as $childKey => $child) {
            if (in_array($child['type'], ['array', 'object'])) {
                $childKey = $this->resolveClassificationKey($child);

                if ($childKey) {
                    $key .= '>' . $childKey;
                }
            } else {
                $key = $key ? '|' . $childKey : $childKey;
            }
        }

        return $key;
    }

    /**
     * Resolve the categories w/ page numbers assigned for each of the artifacts
     */
    private function classifyArtifacts($inputArtifacts, array $allowedCategoryList): array
    {
        $pageNumbers = collect($inputArtifacts)->pluck('position')->toArray();

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
        $agentThread = $this->setupAgentThread();
        app(ThreadRepository::class)->addMessageToThread($agentThread, $categoryPrompt);

        $correctionAttemptsRemaining = $this->config('correction_attempts', 2);

        do {
            static::log("Categorizing artifacts... $correctionAttemptsRemaining attempts remaining");
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
            } catch(Throwable $throwable) {
                // If the agent was unable to provide a valid response, we will ask it to try again
                static::log("Invalid response: " . $throwable->getMessage());

                if ($correctionAttemptsRemaining === 0) {
                    // If we have no more attempts remaining, we will throw the error
                    throw $throwable;
                }

                // Add the error message to the thread so the agent can see it
                app(ThreadRepository::class)->addMessageToThread($agentThread, $throwable->getMessage());
            }
        } while($correctionAttemptsRemaining-- > 0);

        // If we reach here, the agent was unable to provide a valid response
        throw new ValidationError("Agent was unable to provide a valid response for the categories.");
    }

    /**
     * The list of categories that are allowed, resolved from the current list of categories on the artifacts (if any
     * exist)
     *
     * NOTE: This is useful for when the agent has already identified categories for some artifacts, and we are trying
     * to resolve The categories for the rest of the artifacts that are related in sequence
     *
     * @param Artifact[] $artifacts
     */
    private function getAllowedCategoryList($artifacts, $fragmentSelector): array
    {
        $allowedCategoryList = [];

        foreach($artifacts as $inputArtifact) {
            // If the artifact has a category, add it to the list
            $category = $inputArtifact->getFlattenedMetaFragmentValuesString($fragmentSelector);

            if ($category && !str_contains($category, self::CATEGORY_EXCLUDE)) {
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
        foreach($categoryNames as $categoryName) {
            if (empty($allowedCategoryList[$categoryName])) {
                throw new ValidationError("The category '$categoryName' is not in the list of allowed categories.");
            }
        }

        // Check that all pages are added only 1 time and that the page actually exists
        $resolvedPages = [];
        foreach($categoriesWithPages as $categoryItem) {
            foreach($categoryItem['pages'] as $page) {
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
            throw new ValidationError("Not all page numbers have been assigned to a category. " .
                "You must assign all page numbers to a category, even if you are not 100% sure." .
                "Missing page numbers: " . json_encode($diffPageNumbers));
        }
    }

    /**
     * Apply the category to the artifact's JSON content
     */
    protected function applyCategories(array $categoryList, $artifacts): void
    {
        $artifacts = collect($artifacts);

        foreach($categoryList as $categoryItem) {
            $category = $categoryItem['category'] ?? null;

            if (!$category) {
                throw new Exception("Category is not defined in the category list");
            }

            $pages = $categoryItem['pages'] ?? [];

            foreach($pages as $page) {
                // Find the artifact with the given page number
                /** @var Artifact|null $artifact */
                $artifact = $artifacts->firstWhere('position', $page);

                if ($artifact) {
                    $this->applyCategory($artifact, $category);
                }
            }
        }
    }

    /**
     * Apply the category to the artifact's meta
     */
    protected function applyCategory(Artifact $artifact, $category): void
    {
        // Add the category to the artifact's meta
        $meta                                             = $artifact->meta ?? [];
        $meta['classification'][$this->classificationKey] = $category;
        $artifact->meta                                   = $meta;
        $artifact->save();
    }

    /**
     * Create the category prompt for the agent
     */
    protected function createCategoryPrompt($categoryList = [], $pageNumbers = []): string
    {
        // If a category list is given, then the category MUST fall in one of the items in the list no matter what (even if it doesn't really make sense, better to have a defined category than not, so using best guess here)
        $categoryDescription = "Classify each artifact (identified by page number) by selecting the best-matching category from the provided list. " .
            "You must choose one of the categories from the list, even if none of them seem like a perfect fit—use your best judgment to select the closest match. " .
            "Based on the artifact content, if you believe a category in the list is a duplicate of another category in the list (ie: the names are similar, different names referring to the same person, place or thing, etc.), decide which category to keep and disregard the similar category name (DO NOT USE)\n\n" .
            "Categories:\n";

        foreach($categoryList as $category) {
            $categoryDescription .= "* $category\n";
        }

        $pagesDescription = "Then provide the list of page numbers for each artifact that belongs to the category. " .
            "You may include pages even if you are not completely certain, as long as the chosen category is the best available option from the list. Each of these page numbers must appear in exactly 1 category: " . json_encode($pageNumbers);

        return $categoryDescription . "\n\n" . $pagesDescription;

    }

    /**
     * Create a schema definition for the category response
     */
    protected function createCategorySchema($categoryList = []): SchemaDefinition
    {
        $categoryDescription = "One of the following categories: " . implode(', ', $categoryList) . ". Use the users description of the categories to identify which pages belong to which category";

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
                                    'description' => "The list of page numbers that belong to the category. Each page can only be assigned to one category",
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
     * Collects sequences of artifacts with unresolved (empty) category values,
     * grouping them together with the nearest resolved (non-empty) categories
     * that appear before and/or after them. Each group contains:
     *   - A starting resolved category (if available),
     *   - One or more unresolved artifacts in the middle,
     *   - An ending resolved category (if available).
     *
     * These groups are used to infer missing categories based on surrounding context.
     * @param Artifact[] $artifacts
     */
    protected function resolveCategoryGroups($artifacts, array $fragmentSelector): array
    {
        static::log("Applying sequential categories to artifacts");

        /** @var Artifact[][] $categoryGroups */
        $categoryGroups = [];

        $groupIndex            = 0;
        $currentCategory       = '__first__';
        $hasUnresolvedCategory = false;

        foreach($artifacts as $artifact) {
            $category = $artifact->getFlattenedMetaFragmentValuesString($fragmentSelector);

            if (str_contains($category, self::CATEGORY_EXCLUDE)) {
                // If the artifact is excluded, skip it
                continue;
            }

            // Always add the artifact to the current group to see if previous artifacts belong to the same group
            $categoryGroups[$groupIndex][] = $artifact;

            // If this artifact does not have a category, we can flag the group for approval to be resolved
            if (!$category) {
                $hasUnresolvedCategory = true;
            }

            // If the category has changed, we need to start a new group
            if ($category && $currentCategory !== $category) {
                $currentCategory = $category;

                // If there are no unresolvedCategories, we can remove the group as all artifact have been resolved already
                if (!$hasUnresolvedCategory) {
                    unset($categoryGroups[$groupIndex]);
                }

                // Move on to the next group and reset the flag to check for unresolved categories for the group
                $groupIndex++;
                $hasUnresolvedCategory = false;

                // Add this artifact to the next group to see if subsequent artifacts belong to the same group
                $categoryGroups[$groupIndex][] = $artifact;
            }
        }

        static::log("Resolved " . count($categoryGroups) . " category groups: " . json_encode(array_keys($categoryGroups)));

        return $categoryGroups;
    }

    /**
     * Dispatch the category groups to the task process runner.
     * This will activate the task run again with new task processes to complete.
     */
    private function dispatchCategoryGroups(array $categoryGroups): void
    {
        $taskProcesses = [];
        /** @var Artifact[] $categoryGroupArtifacts */
        foreach($categoryGroups as $categoryGroupArtifacts) {
            $taskProcesses[] = TaskProcessRunnerService::prepare($this->taskRun, null, $categoryGroupArtifacts);
        }

        foreach($taskProcesses as $taskProcess) {
            TaskProcessRunnerService::dispatch($taskProcess);
        }
    }
}
