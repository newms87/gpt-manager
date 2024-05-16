<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\InputSourcesController;
use App\Http\Controllers\Ai\MessagesController;
use App\Http\Controllers\Ai\ThreadsController;
use App\Http\Controllers\Ai\WorkflowAssignmentsController;
use App\Http\Controllers\Ai\WorkflowJobsController;
use App\Http\Controllers\Ai\WorkflowsController;
use App\Http\Controllers\ApiAuth\ApiAuthController;
use App\Http\Controllers\Audit\AuditRequestsController;
use Flytedan\DanxLaravel\Http\Routes\ActionRoute;
use Flytedan\DanxLaravel\Http\Routes\FileUploadRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [ApiAuthController::class, 'login'])->withoutMiddleware('auth:sanctum');

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('api.dashboard');

// Utility
FileUploadRoute::routes();

// Input Sources
ActionRoute::routes('input-sources', new InputSourcesController);

// Workflows
ActionRoute::routes('workflows', new WorkflowsController);
ActionRoute::routes('workflow-jobs', new WorkflowJobsController);
ActionRoute::routes('workflow-assignments', new WorkflowAssignmentsController);

// Agents
ActionRoute::routes('agents', new AgentsController);
ActionRoute::routes('threads', new ThreadsController)->group(function () {
    Route::post('/{thread}/run', [ThreadsController::class, 'run'])->name('threads.run');
});
ActionRoute::routes('messages', new MessagesController);

// Audits
ActionRoute::routes('audit-requests', new AuditRequestsController);
