<?php

namespace App\Services\UiDemand;

use App\Models\Demand\UiDemand;
use Illuminate\Support\Facades\Cache;
use Newms87\Danx\Exceptions\ValidationError;
use Symfony\Component\Yaml\Yaml;

class UiDemandWorkflowConfigService
{
    protected const CACHE_KEY = 'ui_demand_workflow_config';

    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Get all workflow configurations in order
     */
    public function getWorkflows(): array
    {
        return $this->loadConfig()['workflows'] ?? [];
    }

    /**
     * Get single workflow configuration by key
     */
    public function getWorkflow(string $key): ?array
    {
        $workflows = $this->getWorkflows();

        foreach ($workflows as $workflow) {
            if ($workflow['key'] === $key) {
                return $workflow;
            }
        }

        return null;
    }

    /**
     * Get schema definition name
     */
    public function getSchemaDefinition(): string
    {
        return $this->loadConfig()['schema_definition'] ?? 'Demand Schema';
    }

    /**
     * Get workflows that this workflow depends on
     */
    public function getDependencies(string $key): array
    {
        $workflow = $this->getWorkflow($key);

        if (!$workflow) {
            throw new ValidationError("Workflow '{$key}' not found in configuration");
        }

        return $workflow['depends_on'] ?? [];
    }

    /**
     * Get workflows that depend on this workflow
     */
    public function getDependents(string $key): array
    {
        $dependents = [];
        $workflows  = $this->getWorkflows();

        foreach ($workflows as $workflow) {
            $dependencies = $workflow['depends_on'] ?? [];
            if (in_array($key, $dependencies)) {
                $dependents[] = $workflow['key'];
            }
        }

        return $dependents;
    }

    /**
     * Check if a workflow can run based on dependencies
     */
    public function canRunWorkflow(UiDemand $demand, string $workflowKey): bool
    {
        $workflow = $this->getWorkflow($workflowKey);

        if (!$workflow) {
            return false;
        }

        // Check if workflow is already running
        if ($this->isWorkflowRunning($demand, $workflowKey)) {
            return false;
        }

        // Check input requirements
        $inputConfig = $workflow['input'] ?? [];
        if (!empty($inputConfig['requires_input_files']) && $demand->inputFiles()->count() === 0) {
            return false;
        }

        // Source validation
        if (($inputConfig['source'] ?? null) === 'team_object' && !$demand->team_object_id) {
            return false;
        }

        // Check all dependencies are completed
        $dependencies = $workflow['depends_on'] ?? [];
        foreach ($dependencies as $dependencyKey) {
            $dependencyRun = $this->getLatestWorkflowRun($demand, $dependencyKey);
            if (!$dependencyRun || !$dependencyRun->isCompleted()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get workflow display configuration
     */
    public function getWorkflowDisplayConfig(string $key): array|false
    {
        $workflow = $this->getWorkflow($key);

        if (!$workflow) {
            return false;
        }

        return $workflow['display_artifacts'] ?? false;
    }

    /**
     * Get workflows formatted for API response
     */
    public function getWorkflowsForApi(): array
    {
        $workflows = $this->getWorkflows();

        return array_map(function ($workflow) {
            return [
                'key'                      => $workflow['key'],
                'name'                     => $workflow['name'],
                'label'                    => $workflow['label'],
                'description'              => $workflow['description'],
                'color'                    => $workflow['color'],
                'extracts_data'            => $workflow['extracts_data']          ?? false,
                'depends_on'               => $workflow['depends_on']             ?? [],
                'input'                    => $workflow['input']                  ?? [],
                'template_categories'      => $workflow['template_categories']    ?? [],
                'instruction_categories'   => $workflow['instruction_categories'] ?? [],
                'display_artifacts'        => $workflow['display_artifacts']      ?? false,
            ];
        }, $workflows);
    }

    /**
     * Load and cache the YAML configuration
     */
    protected function loadConfig(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $configPath = config_path('ui-demands-workflows.yaml');

            if (!file_exists($configPath)) {
                throw new ValidationError("Workflow configuration file not found: {$configPath}");
            }

            $config = Yaml::parseFile($configPath);

            // Validate basic structure
            if (empty($config['workflows']) || !is_array($config['workflows'])) {
                throw new ValidationError('Invalid workflow configuration: missing or invalid workflows array');
            }

            return $config;
        });
    }

    /**
     * Clear the configuration cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Check if a workflow is currently running
     */
    protected function isWorkflowRunning(UiDemand $demand, string $workflowKey): bool
    {
        return $demand->workflowRuns()
            ->wherePivot('workflow_type', $workflowKey)
            ->whereIn('workflow_runs.status', ['Pending', 'Running'])
            ->exists();
    }

    /**
     * Get latest workflow run for a workflow key
     */
    protected function getLatestWorkflowRun(UiDemand $demand, string $workflowKey)
    {
        // Use preloaded relationships when available for better performance
        if ($demand->relationLoaded('workflowRuns')) {
            return $demand->workflowRuns
                ->where('pivot.workflow_type', $workflowKey)
                ->sortByDesc('created_at')
                ->first();
        }

        return $demand->workflowRuns()
            ->wherePivot('workflow_type', $workflowKey)
            ->orderByDesc('created_at')
            ->first();
    }
}
