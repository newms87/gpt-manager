<?php

return [
    'api_url' => env('STRIPE_API_URL', 'https://api.stripe.com/v1/'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
];