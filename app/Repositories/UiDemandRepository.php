<?php

namespace App\Repositories;

use App\Models\UiDemand;
use App\Resources\UiDemandResource;
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
                return UiDemandResource::make($this->createDemand($data));
            case 'update':
                return UiDemandResource::make($this->updateDemand($model, $data));
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

        // Create team object immediately
        $teamObjectRepo = app(TeamObjectRepository::class);
        $teamObject     = $teamObjectRepo->createTeamObject(
            'Demand',
            $demand->title,
            [
                'demand_id'   => $demand->id,
                'title'       => $demand->title,
                'description' => $demand->description,
            ]
        );

        $demand->update(['team_object_id' => $teamObject->id]);

        return $demand;
    }

    protected function updateDemand(UiDemand $demand, array $data): UiDemand
    {
        $demand->update($data);
        $this->syncInputFiles($demand, $data);

        return $demand;
    }

    public function syncInputFiles(UiDemand $demand, array $data): void
    {
        if (isset($data['files'])) {
            $files = StoredFile::whereIn('id', collect($data['files'])->pluck('id'))->get();
            $demand->inputFiles()->sync($files);
        }
    }
}
