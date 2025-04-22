<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use Illuminate\Support\Collection;
use Newms87\Danx\Helpers\ArrayHelper;

/**
 * Service to handle projection and aggregation operations between different artifact hierarchy levels
 */
class ArtifactsProjectionService
{
    /**
     * Project data from source artifacts to target artifacts based on the projection configuration
     * 
     * @param Collection $sourceArtifacts Source artifacts to project data from
     * @param Collection $targetArtifacts Target artifacts to project data onto
     * @param array $config Configuration for the projection
     * @return Collection Updated target artifacts
     */
    public function project(Collection $sourceArtifacts, Collection $targetArtifacts, array $config): Collection
    {
        // Extract projection configuration
        $projectText = $config['project_text'] ?? false;
        $projectJson = $config['project_json'] ?? false;
        $projectMeta = $config['project_meta'] ?? false;
        $projectFiles = $config['project_files'] ?? false;
        $jsonFragmentSelector = $config['json_fragment_selector'] ?? [];
        $metaFragmentSelector = $config['meta_fragment_selector'] ?? [];
        $textSeparator = $config['text_separator'] ?? "\n\n";
        $textPrefix = $config['text_prefix'] ?? "";
        
        // If nothing to project, just return the targets unchanged
        if (!$projectText && !$projectJson && !$projectMeta && !$projectFiles) {
            return $targetArtifacts;
        }
        
        // Create a copy of target artifacts to avoid modifying the original collection
        $projectedArtifacts = collect();
        
        foreach ($targetArtifacts as $targetArtifact) {
            // Clone the target artifact to avoid modifying the original
            $projectedArtifact = Artifact::create([
                'name' => $targetArtifact->name,
                'position' => $targetArtifact->position,
                'text_content' => $targetArtifact->text_content,
                'json_content' => $targetArtifact->json_content,
                'meta' => $targetArtifact->meta,
                'parent_artifact_id' => $targetArtifact->parent_artifact_id,
                'original_artifact_id' => $targetArtifact->id,
                'schema_definition_id' => $targetArtifact->schema_definition_id,
                'task_definition_id' => $targetArtifact->task_definition_id,
                'task_process_id' => $targetArtifact->task_process_id,
            ]);
            
            // Project data from each source artifact onto the target
            foreach ($sourceArtifacts as $sourceArtifact) {
                // Project text content
                if ($projectText && $sourceArtifact->text_content) {
                    if ($projectedArtifact->text_content) {
                        $projectedArtifact->text_content .= $textSeparator;
                    }
                    $projectedArtifact->text_content .= $textPrefix . $sourceArtifact->text_content;
                }
                
                // Project JSON content
                if ($projectJson && $sourceArtifact->json_content) {
                    $jsonToProject = $jsonFragmentSelector ? 
                        $sourceArtifact->getJsonFragment($jsonFragmentSelector) : 
                        $sourceArtifact->json_content;
                    
                    $projectedArtifact->json_content = ArrayHelper::mergeArraysRecursivelyUnique(
                        $projectedArtifact->json_content ?? [], 
                        $jsonToProject
                    );
                }
                
                // Project meta data
                if ($projectMeta && $sourceArtifact->meta) {
                    $metaToProject = $metaFragmentSelector ? 
                        $sourceArtifact->getMetaFragment($metaFragmentSelector) : 
                        $sourceArtifact->meta;
                    
                    $projectedArtifact->json_content = ArrayHelper::mergeArraysRecursivelyUnique(
                        $projectedArtifact->meta ?? [], 
                        $metaToProject
                    );
                }
                
                // Project stored files
                if ($projectFiles && $sourceArtifact->storedFiles->isNotEmpty()) {
                    $projectedArtifact->storedFiles()->syncWithoutDetaching($sourceArtifact->storedFiles->pluck('id'));
                }
            }
            
            // Save the changes
            $projectedArtifact->save();
            $projectedArtifacts->push($projectedArtifact);
        }
        
        return $projectedArtifacts;
    }

    /**
     * Aggregate data from source artifacts to their respective target ancestors
     * 
     * @param Collection $sourceArtifacts Source artifacts to aggregate data from
     * @param Collection $targetAncestors Target ancestors to aggregate data onto
     * @param array $config Configuration for aggregation
     * @return Collection Updated target ancestors
     */
    public function aggregate(Collection $sourceArtifacts, Collection $targetAncestors, array $config): Collection
    {
        // Extract aggregation configuration
        $aggregateText = $config['aggregate_text'] ?? false;
        $aggregateJson = $config['aggregate_json'] ?? false;
        $aggregateMeta = $config['aggregate_meta'] ?? false;
        $aggregateFiles = $config['aggregate_files'] ?? false;
        $jsonFragmentSelector = $config['json_fragment_selector'] ?? [];
        $metaFragmentSelector = $config['meta_fragment_selector'] ?? [];
        $textSeparator = $config['text_separator'] ?? "\n\n";
        $textPrefix = $config['text_prefix'] ?? "";
        
        // If nothing to aggregate, just return the targets unchanged
        if (!$aggregateText && !$aggregateJson && !$aggregateMeta && !$aggregateFiles) {
            return $targetAncestors;
        }
        
        // Group source artifacts by their target ancestor
        $sourcesByAncestor = $this->groupArtifactsByAncestor($sourceArtifacts, $targetAncestors);
        
        // Create a copy of target ancestors to avoid modifying the original collection
        $aggregatedAncestors = collect();
        
        foreach ($targetAncestors as $targetAncestor) {
            // Skip if there are no source artifacts for this ancestor
            if (!isset($sourcesByAncestor[$targetAncestor->id])) {
                $aggregatedAncestors->push($targetAncestor);
                continue;
            }
            
            // Clone the target artifact to avoid modifying the original
            $aggregatedAncestor = Artifact::create([
                'name' => $targetAncestor->name,
                'position' => $targetAncestor->position,
                'text_content' => $targetAncestor->text_content,
                'json_content' => $targetAncestor->json_content,
                'meta' => $targetAncestor->meta,
                'parent_artifact_id' => $targetAncestor->parent_artifact_id,
                'original_artifact_id' => $targetAncestor->id,
                'schema_definition_id' => $targetAncestor->schema_definition_id,
                'task_definition_id' => $targetAncestor->task_definition_id,
                'task_process_id' => $targetAncestor->task_process_id,
            ]);
            
            // Aggregate data from each source artifact to the ancestor
            foreach ($sourcesByAncestor[$targetAncestor->id] as $sourceArtifact) {
                // Aggregate text content
                if ($aggregateText && $sourceArtifact->text_content) {
                    if ($aggregatedAncestor->text_content) {
                        $aggregatedAncestor->text_content .= $textSeparator;
                    }
                    $aggregatedAncestor->text_content .= $textPrefix . $sourceArtifact->text_content;
                }
                
                // Aggregate JSON content
                if ($aggregateJson && $sourceArtifact->json_content) {
                    $jsonToAggregate = $jsonFragmentSelector ? 
                        $sourceArtifact->getJsonFragment($jsonFragmentSelector) : 
                        $sourceArtifact->json_content;
                    
                    $aggregatedAncestor->json_content = ArrayHelper::mergeArraysRecursivelyUnique(
                        $aggregatedAncestor->json_content ?? [], 
                        $jsonToAggregate
                    );
                }
                
                // Aggregate meta data
                if ($aggregateMeta && $sourceArtifact->meta) {
                    $metaToAggregate = $metaFragmentSelector ? 
                        $sourceArtifact->getMetaFragment($metaFragmentSelector) : 
                        $sourceArtifact->meta;
                    
                    $aggregatedAncestor->meta = ArrayHelper::mergeArraysRecursivelyUnique(
                        $aggregatedAncestor->meta ?? [], 
                        $metaToAggregate
                    );
                }
                
                // Aggregate stored files
                if ($aggregateFiles && $sourceArtifact->storedFiles->isNotEmpty()) {
                    $aggregatedAncestor->storedFiles()->syncWithoutDetaching($sourceArtifact->storedFiles->pluck('id'));
                }
            }
            
            // Save the changes
            $aggregatedAncestor->save();
            $aggregatedAncestors->push($aggregatedAncestor);
        }
        
        return $aggregatedAncestors;
    }

    /**
     * Group source artifacts by their target ancestor
     * 
     * @param Collection $sourceArtifacts Source artifacts to group
     * @param Collection $targetAncestors Target ancestors to group by
     * @return array Map of ancestor IDs to their source artifacts
     */
    private function groupArtifactsByAncestor(Collection $sourceArtifacts, Collection $targetAncestors): array
    {
        $sourcesByAncestor = [];
        
        foreach ($sourceArtifacts as $sourceArtifact) {
            // Find the ancestor of this source artifact that is in the target ancestors
            $ancestorId = $this->findAncestorId($sourceArtifact, $targetAncestors);
            
            if ($ancestorId) {
                if (!isset($sourcesByAncestor[$ancestorId])) {
                    $sourcesByAncestor[$ancestorId] = collect();
                }
                
                $sourcesByAncestor[$ancestorId]->push($sourceArtifact);
            }
        }
        
        return $sourcesByAncestor;
    }

    /**
     * Find the ID of the ancestor of an artifact that is in the target ancestors
     * 
     * @param Artifact $artifact Artifact to find ancestor for
     * @param Collection $targetAncestors Target ancestors to search within
     * @return int|null ID of the ancestor or null if not found
     */
    private function findAncestorId(Artifact $artifact, Collection $targetAncestors): ?int
    {
        // If the artifact itself is in the target ancestors, return its ID
        if ($targetAncestors->contains('id', $artifact->id)) {
            return $artifact->id;
        }
        
        // If the artifact has no parent, it's not a descendant of any target ancestor
        if (!$artifact->parent_artifact_id) {
            return null;
        }
        
        // If the parent is in the target ancestors, return its ID
        if ($targetAncestors->contains('id', $artifact->parent_artifact_id)) {
            return $artifact->parent_artifact_id;
        }
        
        // Otherwise, recursively check the parent
        $parent = $artifact->parent;
        if (!$parent) {
            return null;
        }
        
        return $this->findAncestorId($parent, $targetAncestors);
    }
}
