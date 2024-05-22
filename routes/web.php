<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Welcome Info
Route::get('/healthcheck', function () {
    return [
        'success'     => true,
        'gpt-manager' => [
            'author' => 'Daniel Newman',
            'email'  => 'newms87@gmail.com',
            'github' => 'https://github.com/newms87/gpt-manager',
        ],
    ];
});

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

// Auth
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Imports
require __DIR__ . '/auth.php';

// Redirect all traffic to the SPA
Route::get('{any}', function () {
    // If the request is for a node_modules asset, redirect to the app_url() to fetch the asset
    if (str_contains(request()->path(), 'node_modules')) {
        return redirect(app_url(request()->path()));
    }

    return File::get(public_path('index.html'));
})->where('any', '.*');
