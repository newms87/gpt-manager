<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\InputSourcesController;
use App\Http\Controllers\Ai\MessagesController;
use App\Http\Controllers\Ai\ThreadsController;
use App\Http\Controllers\Ai\WorkflowAssignmentsController;
use App\Http\Controllers\Ai\WorkflowJobsController;
use App\Http\Controllers\Ai\WorkflowsController;
use App\Http\Controllers\ProfileController;
use Flytedan\DanxLaravel\Http\Routes\ActionRoute;
use Flytedan\DanxLaravel\Http\Routes\FileUploadRoute;
use Illuminate\Support\Facades\Route;

// Welcome Info
Route::get('/', function () {
    return [
        'success'     => true,
        'gpt-manager' => [
            'author' => 'Daniel Newman',
            'email'  => 'newms87@gmail.com',
            'github' => 'https://github.com/flytedan/gpt-manager',
        ],
    ];
});

// Auth
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

// Imports
require __DIR__ . '/auth.php';
