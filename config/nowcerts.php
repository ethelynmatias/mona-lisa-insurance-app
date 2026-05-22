<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NowCerts API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials from: NowCerts → Settings → API → Generate API Key
    | Docs: https://api.nowcerts.com/Help
    |
    */

    'username' => env('NOWCERTS_USERNAME'),
    'password' => env('NOWCERTS_PASSWORD'),
    'base_url' => env('NOWCERTS_BASE_URL', 'https://api.momentumamp.com/api/'),
    'timeout'  => env('NOWCERTS_TIMEOUT', 30),

];
