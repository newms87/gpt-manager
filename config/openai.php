<?php

return [
    'api_key'    => env("OPENAI_API_KEY"),
    'project_id' => env("OPENAI_PROJECT_ID"),
    'api_url'    => env("OPENAI_API_URL", "https://api.openai.com/v1/"),
];
