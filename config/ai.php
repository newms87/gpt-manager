<?php

use App\Api\OpenAI\OpenAIApi;

return [
    'apis' => [
        OpenAIApi::$serviceName => OpenAIApi::class,
    ],
];
