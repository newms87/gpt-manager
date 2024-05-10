<?php

namespace App\Repositories;

use App\Models\Workflow\Workflow;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class WorkflowRepository extends ActionRepository
{
    public static string $model = Workflow::class;

    /**
     * @param array $data
     * @return Workflow
     */
    public function createWorkflow(array $data): Model
    {
        Validator::make($data, [
            'name' => 'required|string|max:80|unique:workflows',
        ])->validate();

        return Workflow::create($data);
    }

    /**
     * @param string     $action
     * @param null       $model
     * @param array|null $data
     * @return Workflow|bool|Model|mixed|null
     * @throws ValidationError
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createWorkflow($data),
            default => parent::applyAction($action, $model, $data)
        };
    }
}
