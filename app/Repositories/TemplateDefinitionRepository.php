<?php

namespace App\Repositories;

use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateDefinitionHistory;
use App\Services\Template\TemplateDefinitionService;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class TemplateDefinitionRepository extends ActionRepository
{
    public static string $model = TemplateDefinition::class;

    public function query(): Builder
    {
        return parent::query()
            ->where('team_id', team()?->id ?: 0)
            ->with(['storedFile', 'user', 'previewStoredFile']);
    }

    /**
     * Apply filter for template type.
     */
    public function filterFieldType(Builder $builder, string $type): Builder
    {
        return $builder->where('type', $type);
    }

    /**
     * Apply filter for category.
     */
    public function filterFieldCategory(Builder $builder, string $category): Builder
    {
        return $builder->where('category', $category);
    }

    /**
     * Apply filter for active status.
     */
    public function filterFieldIsActive(Builder $builder, bool $isActive): Builder
    {
        return $builder->where('is_active', $isActive);
    }

    #[\Override]
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        $service = app(TemplateDefinitionService::class);

        return match ($action) {
            'create'                   => $service->createTemplate($data ?? []),
            'update'                   => $service->updateTemplate($model, $data ?? []),
            'fetch-template-variables' => $service->fetchTemplateVariables($model),
            'start-collaboration'      => $service->startCollaboration($model, $data['file_ids'] ?? [], $data['prompt'] ?? null),
            'send-message'             => $service->sendMessage(
                AgentThread::find($data['thread_id']),
                $data['message'] ?? '',
                $data['file_id'] ?? null
            ),
            'upload-screenshot'        => $service->uploadScreenshot(
                AgentThreadMessage::find($data['message_id']),
                $data['file_id']
            ),
            'restore-version'          => $service->restoreVersion(
                TemplateDefinitionHistory::find($data['history_id'])
            ),
            default                    => parent::applyAction($action, $model, $data)
        };
    }
}
