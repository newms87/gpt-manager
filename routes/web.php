<?php

use App\Http\Controllers\Ai\AgentsController;
use App\Http\Controllers\Ai\MessagesController;
use App\Http\Controllers\Ai\ThreadsController;
use App\Http\Controllers\ProfileController;
use Flytedan\DanxLaravel\Http\Routes\ActionRoute;
use Flytedan\DanxLaravel\Http\Routes\FileUploadRoute;
use Illuminate\Support\Facades\Route;

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

FileUploadRoute::routes();
ActionRoute::routes('agents', new AgentsController);
ActionRoute::routes('threads', new ThreadsController)->group(function () {
    Route::post('/{thread}/run', [ThreadsController::class, 'run'])->name('threads.run');
});
ActionRoute::routes('messages', new MessagesController);

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
