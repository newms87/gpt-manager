<?php

namespace App\Repositories\ContentSearch;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Newms87\Danx\Repositories\ActionRepository;

class ContentSearchRepository extends ActionRepository
{
    public static string $model = Artifact::class;

    /**
     * Get artifacts scoped to team and optionally filtered
     */
    public function getArtifactsForSearch(int $teamId, array $artifactIds = []): EloquentCollection
    {
        $query = Artifact::where('team_id', $teamId);
        
        if (!empty($artifactIds)) {
            $query->whereIn('id', $artifactIds);
        }
        
        return $query->orderBy('created_at')->get();
    }

    /**
     * Get artifacts with text content for LLM searching
     */
    public function getArtifactsWithTextContent(int $teamId, array $artifactIds = []): EloquentCollection
    {
        $query = Artifact::where('team_id', $teamId)
            ->whereNotNull('text_content')
            ->where('text_content', '!=', '');
            
        if (!empty($artifactIds)) {
            $query->whereIn('id', $artifactIds);
        }
        
        return $query->orderBy('created_at')->get();
    }

    /**
     * Get artifacts with meta or json_content for field path searching
     */
    public function getArtifactsWithStructuredData(int $teamId, array $artifactIds = []): EloquentCollection
    {
        $query = Artifact::where('team_id', $teamId)
            ->where(function($q) {
                $q->whereNotNull('meta')
                  ->orWhereNotNull('json_content');
            });
            
        if (!empty($artifactIds)) {
            $query->whereIn('id', $artifactIds);
        }
        
        return $query->orderBy('created_at')->get();
    }

    /**
     * Get task definition directives for searching
     */
    public function getTaskDefinitionDirectives(TaskDefinition $taskDefinition): Collection
    {
        return $taskDefinition->taskDefinitionDirectives()
            ->with('directive')
            ->get()
            ->map(function ($taskDirective) {
                // Add the directive text directly to the task directive for easier access
                $taskDirective->directive_text = $taskDirective->directive->directive_text ?? '';
                return $taskDirective;
            });
    }

    /**
     * Get directives with non-empty text content
     */
    public function getDirectivesWithText(TaskDefinition $taskDefinition): Collection
    {
        return $this->getTaskDefinitionDirectives($taskDefinition)
            ->filter(function ($taskDirective) {
                return !empty($taskDirective->directive_text);
            });
    }

    /**
     * Search artifacts by field path in meta or json_content
     */
    public function searchArtifactsByFieldPath(int $teamId, string $fieldPath, array $artifactIds = []): EloquentCollection
    {
        $query = Artifact::where('team_id', $teamId)
            ->where(function($q) use ($fieldPath) {
                $q->whereNotNull('meta->' . $fieldPath)
                  ->orWhereNotNull('json_content->' . $fieldPath);
            });
            
        if (!empty($artifactIds)) {
            $query->whereIn('id', $artifactIds);
        }
        
        return $query->get();
    }

    /**
     * Get artifact by ID with team validation
     */
    public function getArtifactForTeam(string $artifactId, int $teamId): ?Artifact
    {
        return Artifact::where('id', $artifactId)
            ->where('team_id', $teamId)
            ->first();
    }

    /**
     * Get task definition with team validation
     */
    public function getTaskDefinitionForTeam(string $taskDefinitionId, int $teamId): ?TaskDefinition
    {
        return TaskDefinition::where('id', $taskDefinitionId)
            ->where('team_id', $teamId)
            ->first();
    }

    /**
     * Search artifacts by regex pattern in text content
     */
    public function searchArtifactsByRegex(int $teamId, string $pattern, array $artifactIds = []): Collection
    {
        $query = Artifact::where('team_id', $teamId)
            ->whereNotNull('text_content')
            ->where('text_content', '!=', '')
            ->whereRaw('text_content REGEXP ?', [$pattern]);
            
        if (!empty($artifactIds)) {
            $query->whereIn('id', $artifactIds);
        }
        
        return $query->get();
    }

    /**
     * Get artifacts filtered by potential content patterns
     * This is used to pre-filter artifacts before LLM processing
     */
    public function getArtifactsWithPotentialMatches(int $teamId, array $patterns, array $artifactIds = []): Collection
    {
        $query = Artifact::where('team_id', $teamId)
            ->whereNotNull('text_content')
            ->where('text_content', '!=', '');

        if (!empty($patterns)) {
            $query->where(function($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('text_content', 'LIKE', "%{$pattern}%");
                }
            });
        }
        
        if (!empty($artifactIds)) {
            $query->whereIn('id', $artifactIds);
        }
        
        return $query->orderBy('created_at')->get();
    }

    /**
     * Count artifacts by search criteria for logging/metrics
     */
    public function countArtifactsForSearch(int $teamId, array $criteria = []): array
    {
        $baseQuery = Artifact::where('team_id', $teamId);
        
        return [
            'total' => (clone $baseQuery)->count(),
            'with_text_content' => (clone $baseQuery)
                ->whereNotNull('text_content')
                ->where('text_content', '!=', '')
                ->count(),
            'with_meta' => (clone $baseQuery)
                ->whereNotNull('meta')
                ->count(),
            'with_json_content' => (clone $baseQuery)
                ->whereNotNull('json_content')
                ->count(),
            'with_structured_data' => (clone $baseQuery)
                ->where(function($q) {
                    $q->whereNotNull('meta')
                      ->orWhereNotNull('json_content');
                })
                ->count(),
        ];
    }

    /**
     * Get artifact text content length for processing optimization
     */
    public function getArtifactTextLengths(Collection $artifacts): array
    {
        return $artifacts->map(function($artifact) {
            return [
                'id' => $artifact->id,
                'text_length' => strlen($artifact->text_content ?? ''),
                'has_meta' => !empty($artifact->meta),
                'has_json_content' => !empty($artifact->json_content),
            ];
        })->toArray();
    }

    /**
     * Sort artifacts by text content length for optimal processing
     * @param Collection|EloquentCollection $artifacts
     */
    public function sortArtifactsByTextLength($artifacts, string $direction = 'asc'): Collection
    {
        return $artifacts->sortBy(function($artifact) {
            return strlen($artifact->text_content ?? '');
        }, SORT_REGULAR, $direction === 'desc');
    }

    /**
     * Get sample text content for logging/debugging
     */
    public function getTextSample(string $text, int $maxLength = 200): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        return substr($text, 0, $maxLength) . '...';
    }

    /**
     * Validate team access to resources
     * @param Collection|EloquentCollection|null $artifacts
     */
    public function validateTeamAccess(int $teamId, $artifacts = null, TaskDefinition $taskDefinition = null): bool
    {
        // Validate task definition team access
        if ($taskDefinition && $taskDefinition->team_id !== $teamId) {
            return false;
        }
        
        // Validate all artifacts belong to team
        if ($artifacts) {
            $invalidArtifacts = $artifacts->filter(function($artifact) use ($teamId) {
                return $artifact->team_id !== $teamId;
            });
            
            if ($invalidArtifacts->isNotEmpty()) {
                return false;
            }
        }
        
        return true;
    }
}