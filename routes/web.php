<?php

use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\ProfileController;
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

Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth']);

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})
    ->middleware('auth')
    ->name('dashboard');

// Auth
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Stripe Webhooks (no auth)
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);


// Imports
require __DIR__ . '/auth.php';
