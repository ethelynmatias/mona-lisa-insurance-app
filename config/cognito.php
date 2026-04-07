<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cognito Forms API Configuration
    |--------------------------------------------------------------------------
    |
    | API key from: Organization Settings → Integrations → "+ New API Key"
    | Docs: https://www.cognitoforms.com/support/476
    |
    */

    'api_key'  => env('COGNITO_API_KEY'),
    'base_url' => env('COGNITO_BASE_URL', 'https://www.cognitoforms.com/api/'),
    'timeout'  => env('COGNITO_TIMEOUT', 30),

];
