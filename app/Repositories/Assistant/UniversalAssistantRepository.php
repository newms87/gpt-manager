<?php

namespace App\Repositories\Assistant;

use App\Models\Assistant\AssistantAction;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class UniversalAssistantRepository extends ActionRepository
{
    public static string $model = AssistantAction::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function actions(): array
    {
        return [
            'create' => [
                'rules'    => [
                    'agent_thread_id' => 'required|exists:agent_threads,id',
                    'context'         => 'required|string',
                    'action_type'     => 'required|string',
                    'target_type'     => 'required|string',
                    'target_id'       => 'sometimes|string',
                    'title'           => 'required|string',
                    'description'     => 'sometimes|string',
                    'payload'         => 'sometimes|array',
                    'preview_data'    => 'sometimes|array',
                ],
                'callback' => [$this, 'create'],
            ],
            'update' => [
                'rules'    => [
                    'status'        => 'sometimes|string',
                    'result_data'   => 'sometimes|array',
                    'error_message' => 'sometimes|string',
                ],
                'callback' => [$this, 'update'],
            ],
        ];
    }

    public function create(array $data): AssistantAction
    {
        return AssistantAction::create(array_merge($data, [
            'team_id' => team()->id,
            'user_id' => user()->id,
        ]));
    }

    public function getActiveActionsForThread(int $threadId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('agent_thread_id', $threadId)
            ->whereIn('status', [
                AssistantAction::STATUS_PENDING,
                AssistantAction::STATUS_IN_PROGRESS,
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getActionHistory(int $threadId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('agent_thread_id', $threadId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getActionsByContext(string $context, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('context', $context)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
