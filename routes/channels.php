<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('WorkflowRun.{teamId}', function (User $user, $teamId) {
    return (bool)$user->teams()->find($teamId);
});

Broadcast::channel('TaskRun.{teamId}', function (User $user, $teamId) {
    return (bool)$user->teams()->find($teamId);
});

Broadcast::channel('AgentThreadRun.{teamId}', function (User $user, $teamId) {
    return (bool)$user->teams()->find($teamId);
});

Broadcast::channel('TaskProcess.{userId}', function (User $user, $userId) {
    return $user->id === (int)$userId;
});

Broadcast::channel('StoredFile.{userId}', function (User $user, $userId) {
    return $user->id === (int)$userId;
});

Broadcast::channel('JobDispatch.{userId}', function (User $user, $userId) {
    return $user->id === (int)$userId;
});

Broadcast::channel('ClaudeCodeGeneration.{teamId}', function (User $user, $teamId) {
    return (bool)$user->teams()->find($teamId);
});
