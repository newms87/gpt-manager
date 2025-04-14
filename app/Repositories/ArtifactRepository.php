<?php

namespace App\Repositories;

use App\Models\Task\Artifact;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class ArtifactRepository extends ActionRepository
{
    public static string $model = Artifact::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id)->orderBy('position');
    }
}
