<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\ContentSourcesController;
use App\Http\Controllers\Ai\MessagesController;
use App\Http\Controllers\Ai\PromptDirectivesController;
use App\Http\Controllers\Ai\SchemaAssociationsController;
use App\Http\Controllers\Ai\SchemaDefinitionsController;
use App\Http\Controllers\Ai\SchemaFragmentsController;
use App\Http\Controllers\Ai\TaskDefinitionAgentsController;
use App\Http\Controllers\Ai\TaskDefinitionsController;
use App\Http\Controllers\Ai\TaskInputsController;
use App\Http\Controllers\Ai\TaskProcessesController;
use App\Http\Controllers\Ai\TaskRunsController;
use App\Http\Controllers\Ai\TeamObjectsController;
use App\Http\Controllers\Ai\ThreadsController;
use App\Http\Controllers\Ai\WorkflowConnectionsController;
use App\Http\Controllers\Ai\WorkflowDefinitionsController;
use App\Http\Controllers\Ai\WorkflowInputsController;
use App\Http\Controllers\Ai\WorkflowNodesController;
use App\Http\Controllers\Ai\WorkflowRunsController;
use App\Http\Controllers\ApiAuth\ApiAuthController;
use App\Http\Controllers\Audit\AuditRequestsController;
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

// Schemas
ActionRoute::routes('schemas/definitions', new SchemaDefinitionsController, function () {
    Route::get('{schemaDefinition}/history', [SchemaDefinitionsController::class, 'history'])->name('schemas.history');
});
ActionRoute::routes('schemas/fragments', new SchemaFragmentsController);
ActionRoute::routes('schemas/associations', new SchemaAssociationsController);

// Tasks
ActionRoute::routes('task-definitions', new TaskDefinitionsController);
ActionRoute::routes('task-definition-agents', new TaskDefinitionAgentsController);
ActionRoute::routes('task-inputs', new TaskInputsController);
ActionRoute::routes('task-runs', new TaskRunsController);
ActionRoute::routes('task-processes', new TaskProcessesController);

// Workflows
ActionRoute::routes('workflow-definitions', new WorkflowDefinitionsController);
ActionRoute::routes('workflow-nodes', new WorkflowNodesController);
ActionRoute::routes('workflow-connections', new WorkflowConnectionsController);
ActionRoute::routes('workflow-runs', new WorkflowRunsController, function () {
    Route::get('run-statuses', [WorkflowRunsController::class, 'runStatuses'])->name('workflow-runs.run-statuses');
});
ActionRoute::routes('workflow-inputs', new WorkflowInputsController);

// Agents
ActionRoute::routes('agents', new AgentsController);
ActionRoute::routes('threads', new ThreadsController);
ActionRoute::routes('messages', new MessagesController);
ActionRoute::routes('prompt/directives', new PromptDirectivesController);

// Team Objects
ActionRoute::routes('team-objects', new TeamObjectsController);

// Audits
ActionRoute::routes('audit-requests', new AuditRequestsController);
