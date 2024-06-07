<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\ContentSourcesController;
use App\Http\Controllers\Ai\MessagesController;
use App\Http\Controllers\Ai\ThreadsController;
use App\Http\Controllers\Ai\WorkflowAssignmentsController;
use App\Http\Controllers\Ai\WorkflowInputsController;
use App\Http\Controllers\Ai\WorkflowJobsController;
use App\Http\Controllers\Ai\WorkflowRunsController;
use App\Http\Controllers\Ai\WorkflowsController;
use App\Http\Controllers\ApiAuth\ApiAuthController;
use App\Http\Controllers\Audit\AuditRequestsController;
use App\Teams\TortGuard\TortguardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Newms87\Danx\Http\Routes\ActionRoute;
use Newms87\Danx\Http\Routes\FileUploadRoute;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [ApiAuthController::class, 'login'])->withoutMiddleware('auth:sanctum');
Route::any('/logout', [ApiAuthController::class, 'logout']);

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('api.dashboard');

// Utility
FileUploadRoute::routes();

// Content Sources
ActionRoute::routes('content-sources', new ContentSourcesController);

// Workflows
ActionRoute::routes('workflows', new WorkflowsController);
ActionRoute::routes('workflow-inputs', new WorkflowInputsController);
ActionRoute::routes('workflow-jobs', new WorkflowJobsController);
ActionRoute::routes('workflow-runs', new WorkflowRunsController);
ActionRoute::routes('workflow-assignments', new WorkflowAssignmentsController);

// Agents
ActionRoute::routes('agents', new AgentsController);
ActionRoute::routes('threads', new ThreadsController)->group(function () {
    Route::post('/{thread}/run', [ThreadsController::class, 'run'])->name('threads.run');
});
ActionRoute::routes('messages', new MessagesController);

// Audits
ActionRoute::routes('audit-requests', new AuditRequestsController);

// Tortguard
Route::get('/tortguard/dashboard', [TortguardController::class, 'getDashboardData'])->name('api.tortguard');
