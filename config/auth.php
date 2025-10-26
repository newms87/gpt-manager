<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', App\Models\User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OAuth providers. Each service can have its own
    | OAuth settings including endpoints and scopes.
    |
    */

    'oauth' => [
        'google' => [
            'client_id'     => env('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri'  => env('APP_URL') . '/api/oauth/callback',
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'revoke_url'    => 'https://oauth2.googleapis.com/revoke',
            'scopes'        => [
                'https://www.googleapis.com/auth/documents',
                'https://www.googleapis.com/auth/drive',
            ],
            'access_type'     => 'offline',
            'approval_prompt' => 'force',
        ],

        // Example configuration for other services
        'stripe' => [
            'client_id'     => env('STRIPE_OAUTH_CLIENT_ID'),
            'client_secret' => env('STRIPE_OAUTH_CLIENT_SECRET'),
            'redirect_uri'  => env('APP_URL') . '/api/oauth/callback',
            'auth_url'      => 'https://connect.stripe.com/oauth/authorize',
            'token_url'     => 'https://connect.stripe.com/oauth/token',
            'scopes'        => ['read_write'], // Stripe uses different scope format
        ],

        'github' => [
            'client_id'     => env('GITHUB_OAUTH_CLIENT_ID'),
            'client_secret' => env('GITHUB_OAUTH_CLIENT_SECRET'),
            'redirect_uri'  => env('APP_URL') . '/api/oauth/callback',
            'auth_url'      => 'https://github.com/login/oauth/authorize',
            'token_url'     => 'https://github.com/login/oauth/access_token',
            'scopes'        => ['repo', 'user'],
        ],

        // Add more services as needed...
    ],

    /*
    |--------------------------------------------------------------------------
    | API Key Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for services that use API keys instead of OAuth.
    | This is mainly for documentation and validation purposes.
    |
    */

    'api_keys' => [
        'openai' => [
            'name'        => 'OpenAI API Key',
            'description' => 'API key for OpenAI services',
            'url'         => 'https://platform.openai.com/api-keys',
        ],

        'anthropic' => [
            'name'        => 'Anthropic API Key',
            'description' => 'API key for Claude AI services',
            'url'         => 'https://console.anthropic.com/',
        ],

        // Add more API key services as needed...
    ],

];
