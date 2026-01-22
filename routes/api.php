<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\ArtifactCategoryDefinitionsController;
use App\Http\Controllers\Ai\ArtifactsController;
use App\Http\Controllers\Ai\ContentSourcesController;
use App\Http\Controllers\Ai\McpServersController;
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
use App\Http\Controllers\Api\Auth\OAuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\SubscriptionPlansController;
use App\Http\Controllers\ApiAuth\ApiAuthController;
use App\Http\Controllers\PusherSubscriptionController;
use App\Http\Controllers\Team\TeamsController;
use App\Http\Controllers\Template\TemplateDefinitionsController;
use App\Http\Controllers\TemplateVariableController;
use App\Http\Controllers\UiDemandsController;
use App\Http\Controllers\Usage\UsageEventsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Newms87\Danx\DanxServiceProvider;
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
ActionRoute::routes('schemas/artifact-category-definitions', new ArtifactCategoryDefinitionsController);

// Artifacts
ActionRoute::routes('artifacts', new ArtifactsController);

// Tasks
ActionRoute::routes('task-definitions', new TaskDefinitionsController, function () {
    Route::post('{taskDefinition}/generate-claude-code', [TaskDefinitionsController::class, 'generateClaudeCode']);
});
ActionRoute::routes('task-artifact-filters', new TaskArtifactFiltersController);
ActionRoute::routes('task-inputs', new TaskInputsController);
ActionRoute::routes('task-runs', new TaskRunsController, function () {
    Route::get('{taskRun}/errors', [TaskRunsController::class, 'errors']);
    Route::get('{taskRun}/history', [TaskRunsController::class, 'history']);
});
ActionRoute::routes('task-processes', new TaskProcessesController, function () {
    Route::get('{taskProcess}/history', [TaskProcessesController::class, 'history']);
});

// Workflows
ActionRoute::routes('workflow-definitions', new WorkflowDefinitionsController, function () {
    Route::get('{workflowDefinition}/export-to-json', [WorkflowDefinitionsController::class, 'exportToJson']);
    Route::post('/import-from-json', [WorkflowDefinitionsController::class, 'importFromJson']);
    Route::post('{workflowDefinition}/invoke', [WorkflowDefinitionsController::class, 'invoke']);
});
ActionRoute::routes('workflow-nodes', new WorkflowNodesController, function () {
    Route::post('clipboard-export', [WorkflowNodesController::class, 'clipboardExport']);
    Route::post('clipboard-import', [WorkflowNodesController::class, 'clipboardImport']);
});
ActionRoute::routes('workflow-connections', new WorkflowConnectionsController);
ActionRoute::routes('workflow-runs', new WorkflowRunsController, function () {
    Route::get('run-statuses', [WorkflowRunsController::class, 'runStatuses'])->name('workflow-runs.run-statuses');
    Route::get('{workflowRun}/active-job-dispatches', [WorkflowRunsController::class, 'activeJobDispatches'])->name('workflow-runs.active-job-dispatches');
    Route::post('{workflowRun}/dispatch-workers', [WorkflowRunsController::class, 'dispatchWorkers'])->name('workflow-runs.dispatch-workers');
    Route::get('{workflowRun}/errors', [WorkflowRunsController::class, 'errors']);
});
ActionRoute::routes('workflow-inputs', new WorkflowInputsController);

// Agents
ActionRoute::routes('agents', new AgentsController);
ActionRoute::routes('mcp-servers', new McpServersController);
ActionRoute::routes('threads', new ThreadsController);
ActionRoute::routes('messages', new MessagesController);
ActionRoute::routes('prompt/directives', new PromptDirectivesController);

// Usage Events
ActionRoute::routes('usage-events', new UsageEventsController);

// Generic OAuth for all services
Route::prefix('oauth')->group(function () {
    // Generic OAuth endpoints that work with any service
    Route::get('{service}/authorize', [OAuthController::class, 'authorize']);
    Route::get('callback', [OAuthController::class, 'callback'])->withoutMiddleware('auth:sanctum');
    Route::get('{service}/status', [OAuthController::class, 'status']);
    Route::post('{service}/validate', [OAuthController::class, 'validate']);
    Route::post('{service}/refresh', [OAuthController::class, 'refresh']);
    Route::delete('{service}/revoke', [OAuthController::class, 'revoke']);

    // Auth token management
    Route::get('tokens', [OAuthController::class, 'index']);
    Route::post('api-keys', [OAuthController::class, 'storeApiKey']);
    Route::delete('tokens/{authToken}', [OAuthController::class, 'destroy']);
});

// Team Objects
ActionRoute::routes('team-objects', new TeamObjectsController, function () {
    Route::post('{sourceObject}/merge/{targetObject}', [TeamObjectsController::class, 'merge'])->name('team-objects.merge');
});

// Audits
DanxServiceProvider::registerAuditRoutes();

// UI Demands
ActionRoute::routes('ui-demands', new UiDemandsController, function () {
    Route::get('workflow-config', [UiDemandsController::class, 'workflowConfig'])->name('ui-demands.workflow-config');
    Route::post('{uiDemand}/workflow/{workflowKey}', [UiDemandsController::class, 'runWorkflow'])->name('ui-demands.run-workflow');
});

// Template Definitions
ActionRoute::routes('template-definitions', new TemplateDefinitionsController);

// Template Variables
ActionRoute::routes('template-variables', new TemplateVariableController);

// Billing & Subscriptions
Route::prefix('billing')->group(function () {
    Route::get('subscription', [BillingController::class, 'getSubscription']);
    Route::post('subscription', [BillingController::class, 'createSubscription']);
    Route::delete('subscription', [BillingController::class, 'cancelSubscription']);

    Route::get('payment-methods', [BillingController::class, 'listPaymentMethods']);
    Route::post('payment-methods', [BillingController::class, 'addPaymentMethod']);
    Route::delete('payment-methods/{paymentMethod}', [BillingController::class, 'removePaymentMethod']);

    Route::post('setup-intent', [BillingController::class, 'createSetupIntent']);
    Route::post('confirm-setup', [BillingController::class, 'confirmSetup']);

    Route::get('history', [BillingController::class, 'getBillingHistory']);
    Route::get('usage', [BillingController::class, 'getUsageStats']);
});

// Subscription Plans (require authentication)
Route::prefix('subscription-plans')->group(function () {
    Route::get('/', [SubscriptionPlansController::class, 'index']);
    Route::get('compare', [SubscriptionPlansController::class, 'compare']);
    Route::get('{plan}', [SubscriptionPlansController::class, 'show']);
});

// Websockets Pusher Broadcasting
Route::post('broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});

// Pusher Subscriptions
Route::prefix('pusher')->group(function () {
    Route::post('subscribe', [PusherSubscriptionController::class, 'subscribe']);
    Route::post('unsubscribe', [PusherSubscriptionController::class, 'unsubscribe']);
    Route::post('keepalive-by-ids', [PusherSubscriptionController::class, 'keepaliveByIds']);
});
