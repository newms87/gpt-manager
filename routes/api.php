<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\ContentSourcesController;
use App\Http\Controllers\Ai\MessagesController;
use App\Http\Controllers\Ai\PromptDirectivesController;
use App\Http\Controllers\Ai\PromptSchemasController;
use App\Http\Controllers\Ai\TeamObjectsController;
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
ActionRoute::routes('workflows', new WorkflowsController, function () {
    Route::get('{workflow}/export-as-json', [WorkflowsController::class, 'exportAsJson'])->name('workflows.export-as-json');
});
ActionRoute::routes('workflow-inputs', new WorkflowInputsController);
ActionRoute::routes('prompt/schemas', new PromptSchemasController);
ActionRoute::routes('prompt/directives', new PromptDirectivesController);
ActionRoute::routes('workflow-jobs', new WorkflowJobsController);
ActionRoute::routes('workflow-runs', new WorkflowRunsController);
ActionRoute::routes('workflow-assignments', new WorkflowAssignmentsController);

// Agents
ActionRoute::routes('agents', new AgentsController);
ActionRoute::routes('threads', new ThreadsController);
ActionRoute::routes('messages', new MessagesController);

// Team Objects
ActionRoute::routes('team-objects', new TeamObjectsController);

// Audits
ActionRoute::routes('audit-requests', new AuditRequestsController);

Route::get('/tortguard/search', [TortguardController::class, 'search'])->name('api.tortguard.search');
Route::post('/tortguard/research', [TortguardController::class, 'research'])->name('api.tortguard.research');
Route::get('/tortguard/drug-side-effect/{id}', [TortguardController::class, 'getDrugSideEffect'])->name('api.tortguard.drug-side-effect');

// Tortguard
Route::get('/tortguard/dashboard', [TortguardController::class, 'getDashboardData'])->name('api.tortguard');
