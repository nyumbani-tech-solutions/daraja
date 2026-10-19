<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The C2B validation and confirmation, STK push callback and Pull routes
    | are off until enabled. They need a bound HandlesPayments implementation.
    |
    */
    'routes' => [
        'enabled' => false,
        'prefix' => 'safaricom',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast prefix
    |--------------------------------------------------------------------------
    |
    | STK push results are broadcast on "{prefix}.{channel key}".
    |
    */
    'broadcast_prefix' => 'daraja',

    /*
    |--------------------------------------------------------------------------
    | Safaricom IP addresses
    |--------------------------------------------------------------------------
    |
    | The addresses allowed to reach the C2B routes, and the environments in
    | which the check is skipped.
    |
    */
    'allowed_ips' => [
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.114',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.44',
        '196.201.212.127',
        '196.201.212.138',
        '196.201.212.129',
        '196.201.212.136',
        '196.201.212.74',
        '196.201.212.69',
    ],

    'skip_ip_check_in' => ['local', 'testing'],
];
