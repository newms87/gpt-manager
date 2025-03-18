<?php

namespace App\Repositories;

use App\Models\Prompt\PromptDirective;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Repositories\ActionRepository;

class PromptDirectiveRepository extends ActionRepository
{
    public static string $model = PromptDirective::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id)->whereNull('resource_package_import_id');
    }

    public function summaryQuery(array $filter = []): Builder|QueryBuilder
    {
        return parent::summaryQuery($filter)->addSelect([
            DB::raw("SUM(agents_count) as agents_count"),
        ]);
    }

    public function applyAction(string $action, PromptDirective|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createPromptDirective($data),
            'update' => $this->updatePromptDirective($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createPromptDirective($input): PromptDirective
    {
        $promptDirective = PromptDirective::make()->forceFill([
            'team_id' => team()->id,
        ]);

        return $this->updatePromptDirective($promptDirective, $input);
    }

    public function updatePromptDirective(PromptDirective $promptDirective, array $input): PromptDirective
    {
        $promptDirective->fill($input);

        $promptDirective->validate()->save($input);

        return $promptDirective;
    }
}
