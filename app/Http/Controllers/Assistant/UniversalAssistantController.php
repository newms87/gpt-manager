<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Resources\Assistant\AssistantActionResource;
use App\Models\Agent\AgentThread;
use App\Models\Assistant\AssistantAction;
use App\Repositories\Assistant\UniversalAssistantRepository;
use App\Resources\Agent\AgentThreadResource;
use App\Services\Assistant\UniversalAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Newms87\Danx\Http\Controllers\ActionController;

class UniversalAssistantController extends ActionController
{
    public static ?string $repo     = UniversalAssistantRepository::class;
    public static ?string $resource = AssistantActionResource::class;

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'      => 'required|string',
            'context'      => 'required|string',
            'context_data' => 'sometimes|array',
            'thread_id'    => 'sometimes|exists:agent_threads,id',
        ]);

        $result = app(UniversalAssistantService::class)->handleChatMessage(
            message: $request->input('message'),
            context: $request->input('context'),
            contextData: $request->input('context_data', []),
            threadId: $request->input('thread_id')
        );

        return response()->json([
            'thread'               => $result['thread'] ? AgentThreadResource::make($result['thread']) : null,
            'message'              => $result['message'],
            'actions'              => AssistantActionResource::collection($result['actions'] ?? []),
            'context_capabilities' => $result['context_capabilities'] ?? [],
        ]);
    }

    public function getThread(AgentThread $thread): JsonResponse
    {
        // Note: Thread authorization should be handled by route model binding or middleware
        // For now, assuming thread access is controlled by team_id scoping

        return response()->json([
            'thread'  => AgentThreadResource::make($thread->load(['messages', 'runs'])),
            'actions' => AssistantActionResource::collection(
                $thread->assistantActions()->orderBy('created_at', 'desc')->get()
            ),
        ]);
    }

    public function getActions(Request $request): JsonResponse
    {
        $request->validate([
            'thread_id' => 'sometimes|exists:agent_threads,id',
            'context'   => 'sometimes|string',
            'status'    => 'sometimes|string',
        ]);

        $query = AssistantAction::where('user_id', auth()->id());

        if ($request->has('thread_id')) {
            $query->where('agent_thread_id', $request->input('thread_id'));
        }

        if ($request->has('context')) {
            $query->where('context', $request->input('context'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $actions = $query->orderBy('created_at', 'desc')->get();

        return response()->json(AssistantActionResource::collection($actions));
    }

    public function approveAction(AssistantAction $action): JsonResponse
    {
        $action->authorize();

        app(UniversalAssistantService::class)->executeAction($action);

        return response()->json(AssistantActionResource::make($action->fresh()));
    }

    public function cancelAction(AssistantAction $action): JsonResponse
    {
        $action->authorize();

        $action->markCancelled();

        return response()->json(AssistantActionResource::make($action->fresh()));
    }

    public function getContextCapabilities(Request $request): JsonResponse
    {
        $request->validate([
            'context'      => 'required|string',
            'context_data' => 'sometimes|array',
        ]);

        $capabilities = app(UniversalAssistantService::class)->getContextCapabilities(
            context: $request->input('context'),
            contextData: $request->input('context_data', [])
        );

        return response()->json($capabilities);
    }
}
