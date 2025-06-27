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

    public function startChat(Request $request)
    {
        $request->validate([
            'message'      => 'required|string',
            'context'      => 'required|string',
            'context_data' => 'sometimes|array',
        ]);

        $result = app(UniversalAssistantService::class)->createChatThread(
            message: $request->input('message'),
            context: $request->input('context'),
            contextData: $request->input('context_data', [])
        );

        return AgentThreadResource::make($result['thread'], ['messages' => true, 'actions' => true]);
    }

    public function chat(Request $request, AgentThread $agentThread)
    {
        $request->validate([
            'message'      => 'required|string',
            'context'      => 'required|string',
            'context_data' => 'sometimes|array',
        ]);

        if (!$agentThread->canEdit()) {
            abort(403, 'Unauthorized to edit this thread');
        }

        $result = app(UniversalAssistantService::class)->handleChatMessage(
            thread: $agentThread,
            message: $request->input('message'),
            context: $request->input('context'),
            contextData: $request->input('context_data', [])
        );

        return AgentThreadResource::make($result['thread'], ['actions' => true]);
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
