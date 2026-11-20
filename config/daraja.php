<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The Daraja environment every call is made against: "sandbox" or
    | "production".
    |
    */
    'environment' => env('DARAJA_ENVIRONMENT', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Base urls
    |--------------------------------------------------------------------------
    |
    | The base url of the Daraja API for each environment.
    |
    */
    'base_urls' => [
        'sandbox' => 'https://sandbox.safaricom.co.ke',
        'production' => 'https://api.safaricom.co.ke',
    ],

    /*
    |--------------------------------------------------------------------------
    | MSISDN resolver
    |--------------------------------------------------------------------------
    |
    | The MSISDN resolver MsisdnResolver asks to turn the hash Safaricom
    | sends in place of a number back into the number. The url is its API root
    | (https://msisdn.nyumbani.ke/api/v1), the token a Sanctum bearer token
    | issued to your app, and the timeout is in seconds. Without a url or a
    | token the resolver is never called and a hash is returned as it came.
    |
    */
    'msisdn_resolver' => [
        'url' => env('DARAJA_MSISDN_RESOLVER_URL', 'https://msisdn.nyumbani.ke/api/v1'),
        'token' => env('DARAJA_MSISDN_RESOLVER_TOKEN'),
        'timeout' => env('DARAJA_MSISDN_RESOLVER_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safaricom IP addresses
    |--------------------------------------------------------------------------
    |
    | The addresses EnsureRequestIsFromSafaricom lets through.
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

    /*
    |--------------------------------------------------------------------------
    | Skip the IP check in
    |--------------------------------------------------------------------------
    |
    | The environments in which EnsureRequestIsFromSafaricom accepts requests from any
    | address, since Safaricom cannot reach a local machine.
    |
    */
    'skip_ip_check_in' => ['local', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Docs credentials
    |--------------------------------------------------------------------------
    |
    | The short code the live buttons of the docs call Safaricom with. Only the
    | docs read these; your app builds its own ShortCode.
    |
    */
    'docs' => [
        'short_code' => env('DARAJA_SHORT_CODE'),
        'consumer_key' => env('DARAJA_CONSUMER_KEY'),
        'consumer_secret' => env('DARAJA_CONSUMER_SECRET'),
        'pass_key' => env('DARAJA_PASS_KEY'),
        'till_number' => env('DARAJA_TILL_NUMBER'),
    ],
];
