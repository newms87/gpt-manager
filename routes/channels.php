<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('WorkflowRun.{teamId}', function (User $user, $teamId) {
    return (bool)$user->teams()->find($teamId);
});
