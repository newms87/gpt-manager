<?php

namespace App\Repositories;

use App\Api\OpenAI\OpenAIApi;
use App\Models\Agent\Agent;
use App\Models\Agent\Thread;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Repositories\ActionRepository;
use Illuminate\Database\Eloquent\Model;

class AgentRepository extends ActionRepository
{
    public static string $model = Agent::class;

    public function createAgent(array $data): Model
    {
        // TODO: Implement this via Laravel validation
        if (Agent::where('name', $data['name'] ?? '')->exists()) {
            throw new ValidationError('An agent with this name already exists');
        }

        $data += [
            'api'   => OpenAIApi::$serviceName,
            'model' => 'gpt-4-turbo',
        ];

        return Agent::create($data);
    }

    /**
     * @param string     $action
     * @param Agent      $model
     * @param array|null $data
     * @return Thread|bool|Model|mixed|null
     * @throws ValidationError
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createAgent($data),
            'create-thread' => app(ThreadsRepository::class)->create($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function filterFieldOptions(?array $filter = []): array
    {
        $aiModels = collect(static::getAiModels())->sortKeys()->map(fn($aiModel) => [
            'label' => $aiModel['api'] . ': ' . $aiModel['model'],
            'value' => $aiModel['model'],
        ])->values()->toArray();

        return [
            'aiModels' => $aiModels,
        ];
    }

    /**
     * Reverse lookup API from model
     *
     * @param string $model
     * @return string|null
     */
    public static function getApiFromModel(string $model): ?string
    {
        foreach(static::getAiModels() as $aiModel) {
            if ($aiModel['model'] === $model) {
                return $aiModel['api'];
            }
        }

        return null;
    }

    /**
     * Get all available AI models
     *
     * @return array
     */
    public static function getAiModels(): array
    {
        return cache()->rememberForever('ai-models', function () {
            $aiModels = [];

            foreach(config('ai.apis') as $apiName => $apiClass) {
                $models = $apiClass::make()->getModels();
                foreach($models as $model) {
                    $aiModels[$apiName . ':' . $model] = [
                        'api'   => $apiName,
                        'model' => $model,
                    ];
                }
            }

            return $aiModels;
        });
    }
}
