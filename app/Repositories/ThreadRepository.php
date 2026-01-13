<?php

namespace App\Repositories;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\DateHelper;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;

class ThreadRepository extends ActionRepository
{
    public static string $model = AgentThread::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()->id);
    }

    public function create(Agent $agent, string $name = '', ?Model $collaboratable = null): AgentThread
    {
        if (!$name) {
            $name = $agent->name . ' ' . DateHelper::formatDateTime(now());
        }

        $data = [
            'team_id'  => team()?->id ?: $agent->team_id,
            'user_id'  => user()?->id,
            'name'     => StringHelper::logSafeString(substr($name, 0, 150)),
            'agent_id' => $agent->id,
        ];

        if ($collaboratable) {
            $data['collaboratable_type'] = $collaboratable::class;
            $data['collaboratable_id']   = $collaboratable->getKey();
        }

        $thread = AgentThread::make()->forceFill($data);
        $thread->save();

        return $thread;
    }

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create-message'   => app(MessageRepository::class)->create($model, $data['role'] ?? AgentThreadMessage::ROLE_USER),
            'reset-to-message' => $this->resetToMessage($model, $data['message_id']),
            'copy'             => $this->copyThread($model),
            'run'              => $this->runAgentThread($model, $data),
            'stop'             => app(AgentThreadService::class)->stop($model),
            'resume'           => app(AgentThreadService::class)->resume($model),
            default            => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Run an agent thread
     */
    public function runAgentThread(AgentThread $agentThread, $data): AgentThreadRun
    {
        $responseDefinitionId = $data['response_schema_id']   ?? null;
        $responseFragmentId   = $data['response_fragment_id'] ?? null;

        $schemaDefinition = null;
        $schemaFragment   = null;

        if ($responseDefinitionId) {
            $schemaDefinition = team()->schemaDefinitions()->find($responseDefinitionId);

            if (!$schemaDefinition) {
                throw new ValidationError("Schema definition not found: $responseDefinitionId");
            }
        }

        if ($schemaDefinition && $responseFragmentId) {
            $schemaFragment = $schemaDefinition->fragments()->find($responseFragmentId);

            if (!$schemaFragment) {
                throw new ValidationError("Schema fragment not found: $responseFragmentId");
            }
        }

        return app(AgentThreadService::class)->withResponseFormat($schemaDefinition, $schemaFragment)->dispatch($agentThread);
    }

    /**
     * Append a new message to the thread
     */
    public function addMessageToThread(AgentThread $thread, string|array|int|bool|null $content = null, array $fileIds = []): AgentThread
    {
        if ($content || $fileIds) {
            if (is_scalar($content) || !$content) {
                $contentString = (string)$content;
            } else {
                // files is a special key that holds our file IDs
                $contentFiles = $this->formatContentFiles($content['files'] ?? []);

                if ($contentFiles) {
                    $content['files'] = $contentFiles;
                    $fileIds          = array_unique(array_merge($fileIds, array_column($contentFiles, 'id')));
                } else {
                    unset($content['files']);
                }

                // If the content is a single key with a scalar value, just use that as the content string
                if (!empty($content['content']) && count($content) === 1) {
                    $contentString = $content['content'];
                } else {
                    // Otherwise convert the content to a JSON string
                    $contentString = $content ? json_encode($content) : '';
                }
            }

            $message = $thread->messages()->create([
                'role'    => AgentThreadMessage::ROLE_USER,
                'content' => $contentString,
            ]);

            if ($fileIds) {
                app(MessageRepository::class)->saveFiles($message, $fileIds);
            }
        }

        return $thread;
    }

    /**
     * Format the content files for a message to an LLM agent
     */
    public function formatContentFiles($files): array
    {
        $formatted = [];

        foreach ($files as $file) {
            if ($file instanceof StoredFile) {
                $formatted[] = [
                    'id'  => $file->id,
                    'url' => $file->url,
                ];
            } else {
                $formatted[] = [
                    'id'  => ($file['id'] ?? null),
                    'url' => ($file['url'] ?? null),
                ];
            }
        }

        return $formatted;
    }

    /**
     * Deletes all the messages in a thread after the given message
     */
    public function resetToMessage(AgentThread $thread, $messageId): AgentThread
    {
        $thread->messages()->where('id', '>', $messageId)->each(fn(AgentThreadMessage $m) => $m->delete());

        return $thread;
    }

    /**
     * Copy a thread and its messages / files
     */
    public function copyThread(AgentThread $thread)
    {
        $newThread       = $thread->replicate();
        $newThread->name = ModelHelper::getNextModelName($thread);
        $newThread->save();

        foreach ($thread->messages as $message) {
            $messageCopy                  = $message->replicate();
            $messageCopy->agent_thread_id = $newThread->id;
            $messageCopy->save();

            foreach ($message->storedFiles as $storedFile) {
                $messageCopy->storedFiles()->attach($storedFile);
            }
        }

        return $newThread;
    }
}
