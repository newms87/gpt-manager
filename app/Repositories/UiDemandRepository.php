<?php

namespace App\Repositories;

use App\Models\UiDemand;
use App\Resources\UiDemandResource;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;

class UiDemandRepository extends ActionRepository
{
    public static string $model = UiDemand::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function applyAction(string $action, Model|null|array $model = null, ?array $data = null)
    {
        switch($action) {
            case 'create':
                return UiDemandResource::details($this->createDemand($data));
            case 'update':
                return UiDemandResource::details($this->updateDemand($model, $data));
            default:
                return parent::applyAction($action, $model, $data);
        }
    }

    protected function createDemand(array $data): UiDemand
    {
        $data['team_id'] = team()->id;
        $data['user_id'] = auth()->id();
        $data['status']  = UiDemand::STATUS_DRAFT;

        $demand = UiDemand::create($data);
        $this->syncInputFiles($demand, $data);

        $schemaDefinition = app(UiDemandWorkflowService::class)->getSchemaDefinitionForDemand();
        // Create team object immediately
        $teamObject = app(TeamObjectRepository::class)->createTeamObject(
            'Demand',
            $demand->title,
            [
                'schema_definition_id' => $schemaDefinition->id,
                'demand_id'            => $demand->id,
                'title'                => $demand->title,
                'description'          => $demand->description,
            ]
        );

        $demand->update(['team_object_id' => $teamObject->id]);

        return $demand;
    }

    protected function updateDemand(UiDemand $demand, array $data): UiDemand
    {
        $demand->update($data);
        $this->syncInputFiles($demand, $data);
        $this->syncOutputFiles($demand, $data);

        return $demand;
    }

    public function syncInputFiles(UiDemand $demand, array $data): void
    {
        if (array_key_exists('input_files', $data)) {
            $files = $data['input_files'] ?? [];

            if (empty($files)) {
                // Clear all input files
                $demand->inputFiles()->sync([]);
            } else {
                $fileIds       = collect($files)->pluck('id')->filter();
                $existingFiles = StoredFile::whereIn('id', $fileIds)->pluck('id');

                // Create sync data with category pivot
                $syncData = $existingFiles->mapWithKeys(function ($fileId) {
                    return [$fileId => ['category' => 'input']];
                })->toArray();

                $demand->inputFiles()->sync($syncData);
            }
        }
    }

    public function syncOutputFiles(UiDemand $demand, array $data): void
    {
        if (array_key_exists('output_files', $data)) {
            $files = $data['output_files'] ?? [];

            if (empty($files)) {
                // Clear all output files
                $demand->outputFiles()->sync([]);
            } else {
                $fileIds       = collect($files)->pluck('id')->filter();
                $existingFiles = StoredFile::whereIn('id', $fileIds)->pluck('id');

                // Create sync data with category pivot
                $syncData = $existingFiles->mapWithKeys(function ($fileId) {
                    return [$fileId => ['category' => 'output']];
                })->toArray();

                $demand->outputFiles()->sync($syncData);
            }
        }
    }
}
