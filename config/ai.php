<?php

use App\Api\OpenAi\OpenAiApi;

return [
    'apis' => [
        OpenAiApi::$serviceName => OpenAiApi::class,
    ],
];
