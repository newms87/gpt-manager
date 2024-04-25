<?php

use App\Http\Controllers\AgentsController;
use App\Http\Routes\ActionRoute;
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

ActionRoute::routes('agents', new AgentsController);
