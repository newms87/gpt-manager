<?php

namespace App\Repositories;

use App\Models\ContentSource\ContentSource;
use App\Services\ContentSource\ContentSourceFetchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Repositories\ActionRepository;

class ContentSourceRepository extends ActionRepository
{
    public static string $model = ContentSource::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function summaryQuery(array $filter = []): Builder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw('SUM(workflow_inputs_count) as workflow_inputs_count'),
        ]);
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->create($data),
            'fetch'  => $this->fetch($model),
            default  => parent::applyAction($action, $model, $data)
        };
    }

    public function create(array $data): ContentSource
    {
        $data += [
            'type' => ContentSource::TYPE_API,
        ];

        $contentSource          = ContentSource::make($data);
        $contentSource->team_id = team()->id;
        $contentSource->validate();
        $contentSource->save();

        return $contentSource;
    }

    public function fieldOptions(?array $filter = []): array
    {
        return [
            'types' => ContentSource::where('team_id', team()->id)->filter($filter)->pluck('type'),
        ];
    }

    public function fetch(ContentSource $contentSource): ContentSource
    {
        app(ContentSourceFetchService::class)->fetch($contentSource);

        return $contentSource;
    }
}
