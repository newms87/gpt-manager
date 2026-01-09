<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

$broadcastAuthTeam = fn(User $user, $teamId) => (bool)$user->teams()->find($teamId);

Broadcast::channel('WorkflowRun.{teamId}', $broadcastAuthTeam);
Broadcast::channel('TaskRun.{teamId}', $broadcastAuthTeam);
Broadcast::channel('TaskDefinition.{teamId}', $broadcastAuthTeam);
Broadcast::channel('AgentThreadRun.{teamId}', $broadcastAuthTeam);
Broadcast::channel('AgentThread.{teamId}', $broadcastAuthTeam);
Broadcast::channel('TaskProcess.{teamId}', $broadcastAuthTeam);
Broadcast::channel('StoredFile.{teamId}', $broadcastAuthTeam);
Broadcast::channel('JobDispatch.{teamId}', $broadcastAuthTeam);
Broadcast::channel('ClaudeCodeGeneration.{teamId}', $broadcastAuthTeam);
Broadcast::channel('UsageSummary.{teamId}', $broadcastAuthTeam);
Broadcast::channel('TeamObject.{teamId}', $broadcastAuthTeam);
Broadcast::channel('UiDemand.{teamId}', $broadcastAuthTeam);
Broadcast::channel('ApiLog.{teamId}', $broadcastAuthTeam);
