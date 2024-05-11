<?php

namespace App\Repositories;

use App\Models\Shared\InputSource;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class InputSourcesRepository extends ActionRepository
{
    public static string $model = InputSource::class;

    /**
     * @param string              $action
     * @param Model|Workflow|null $model
     * @param array|null          $data
     * @return Workflow|WorkflowJob|bool|Model|mixed|null
     * @throws ValidationError
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createInputSource($data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * @param array $data
     * @return Workflow
     */
    public function createInputSource(array $data): Model
    {
        $data['team_id'] = team()->id;
        $data['user_id'] = user()->id;
     
        Validator::make($data, [
            'name' => 'required|string|max:80|unique:input_sources',
        ])->validate();

        $inputSource = InputSource::make()->forceFill($data);
        $inputSource->save();

        return $inputSource;
    }
}
