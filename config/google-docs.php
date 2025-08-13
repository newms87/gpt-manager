<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Docs API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Docs API integration supporting both OAuth 2.0
    | and service account authentication. OAuth is preferred when available.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API URLs
    |--------------------------------------------------------------------------
    */

    'api_url' => env('GOOGLE_DOCS_API_URL', 'https://docs.googleapis.com/v1/'),


    /*
    |--------------------------------------------------------------------------
    | Default Folder Configuration
    |--------------------------------------------------------------------------
    */

    'default_folder_id' => env('GOOGLE_DOCS_DEFAULT_FOLDER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Document Permissions
    |--------------------------------------------------------------------------
    */

    'default_permissions' => [
        'type' => 'anyone',
        'role' => 'reader',
    ],

    /*
    |--------------------------------------------------------------------------
    | File ID Detection Configuration
    |--------------------------------------------------------------------------
    */

    'file_id_detection_model' => env('GOOGLE_DOCS_FILE_ID_DETECTION_MODEL') ?: 'gpt-5-nano',

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0 Configuration
    |--------------------------------------------------------------------------
    |
    | OAuth configuration has been moved to config/auth.php under the 'oauth'
    | section. This allows for a unified authentication system that supports
    | multiple services (Google, Stripe, GitHub, etc.).
    |
    | See: config/auth.php -> auth.oauth.google
    |
    */
];
