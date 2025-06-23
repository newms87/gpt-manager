<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\ArtifactsController;
use App\Http\Controllers\Ai\ContentSourcesController;
use App\Http\Controllers\Ai\MessagesController;
use App\Http\Controllers\Ai\PromptDirectivesController;
use App\Http\Controllers\Ai\SchemaAssociationsController;
use App\Http\Controllers\Ai\SchemaDefinitionsController;
use App\Http\Controllers\Ai\SchemaFragmentsController;
use App\Http\Controllers\Ai\TaskArtifactFiltersController;
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
use App\Http\Controllers\Api\WebhooksController;
use App\Http\Controllers\Api\WhatsAppConnectionsController;
use App\Http\Controllers\Api\WhatsAppMessagesController;
use App\Http\Controllers\ApiAuth\ApiAuthController;
use App\Http\Controllers\Audit\AuditRequestsController;
use App\Http\Controllers\Team\TeamsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Newms87\Danx\Http\Routes\ActionRoute;
use Newms87\Danx\Http\Routes\FileUploadRoute;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [ApiAuthController::class, 'login'])->withoutMiddleware('auth:sanctum');
Route::post('/login-to-team', [ApiAuthController::class, 'loginToTeam']);
Route::any('/logout', [ApiAuthController::class, 'logout']);

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('api.dashboard');

// Utility
FileUploadRoute::routes();

// Teams
Route::any('teams/list', [TeamsController::class, 'list'])->name('teams.list')->withoutMiddleware('auth:sanctum');

// Content Sources
ActionRoute::routes('content-sources', new ContentSourcesController);

// Schemas
ActionRoute::routes('schemas/definitions', new SchemaDefinitionsController, function () {
    Route::get('{schemaDefinition}/history', [SchemaDefinitionsController::class, 'history'])->name('schemas.history');
});
ActionRoute::routes('schemas/fragments', new SchemaFragmentsController);
ActionRoute::routes('schemas/associations', new SchemaAssociationsController);

// Artifacts
ActionRoute::routes('artifacts', new ArtifactsController);

// Tasks
ActionRoute::routes('task-definitions', new TaskDefinitionsController, function () {
    Route::post('{taskDefinition}/generate-claude-code', [TaskDefinitionsController::class, 'generateClaudeCode']);
});
ActionRoute::routes('task-artifact-filters', new TaskArtifactFiltersController);
ActionRoute::routes('task-inputs', new TaskInputsController);
ActionRoute::routes('task-runs', new TaskRunsController, function () {
    Route::get('{taskRun}/subscribe-to-processes', [TaskRunsController::class, 'subscribeToProcesses']);
});
ActionRoute::routes('task-processes', new TaskProcessesController);

// Workflows
ActionRoute::routes('workflow-definitions', new WorkflowDefinitionsController, function () {
    Route::get('{workflowDefinition}/export-to-json', [WorkflowDefinitionsController::class, 'exportToJson']);
    Route::post('/import-from-json', [WorkflowDefinitionsController::class, 'importFromJson']);
    Route::post('{workflowDefinition}/invoke', [WorkflowDefinitionsController::class, 'invoke']);
});
ActionRoute::routes('workflow-nodes', new WorkflowNodesController);
ActionRoute::routes('workflow-connections', new WorkflowConnectionsController);
ActionRoute::routes('workflow-runs', new WorkflowRunsController, function () {
    Route::get('run-statuses', [WorkflowRunsController::class, 'runStatuses'])->name('workflow-runs.run-statuses');
    Route::get('{workflowRun}/active-job-dispatches', [WorkflowRunsController::class, 'activeJobDispatches'])->name('workflow-runs.active-job-dispatches');
    Route::post('{workflowRun}/dispatch-workers', [WorkflowRunsController::class, 'dispatchWorkers'])->name('workflow-runs.dispatch-workers');
    Route::get('{workflowRun}/subscribe-to-job-dispatches', [WorkflowRunsController::class, 'subscribeToJobDispatches'])->name('workflow-runs.subscribe-to-job-dispatches');
});
ActionRoute::routes('workflow-inputs', new WorkflowInputsController);

// Agents
ActionRoute::routes('agents', new AgentsController);
ActionRoute::routes('threads', new ThreadsController);
ActionRoute::routes('messages', new MessagesController);
ActionRoute::routes('prompt/directives', new PromptDirectivesController);

// Team Objects
ActionRoute::routes('team-objects', new TeamObjectsController, function () {
    Route::post('{sourceObject}/merge/{targetObject}', [TeamObjectsController::class, 'merge'])->name('team-objects.merge');
});

// Audits
ActionRoute::routes('audit-requests', new AuditRequestsController);

// WhatsApp
ActionRoute::routes('whatsapp-connections', new WhatsAppConnectionsController);
ActionRoute::routes('whatsapp-messages', new WhatsAppMessagesController);

Route::post('whatsapp-connections/{whatsAppConnection}/verify', [WhatsAppConnectionsController::class, 'verify']);
Route::post('whatsapp-connections/{whatsAppConnection}/generate-webhook-url', [WhatsAppConnectionsController::class, 'generateWebhookUrl']);
Route::post('whatsapp-connections/{whatsAppConnection}/test-message', [WhatsAppConnectionsController::class, 'testMessage']);
Route::post('whatsapp-connections/{whatsAppConnection}/sync-messages', [WhatsAppConnectionsController::class, 'syncMessages']);

Route::get('whatsapp-messages/conversation', [WhatsAppMessagesController::class, 'conversation']);
Route::get('whatsapp-messages/recent', [WhatsAppMessagesController::class, 'recent']);

// Webhooks (no auth middleware)
Route::any('webhooks/whatsapp/{connection}/{token}', [WebhooksController::class, 'whatsappIncoming'])
    ->name('webhooks.whatsapp.incoming')
    ->withoutMiddleware(['auth:sanctum']);

// Websockets Pusher Broadcasting
Route::post('broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});
