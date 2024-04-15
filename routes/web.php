<?php

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
