<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base url
    |--------------------------------------------------------------------------
    |
    | The base url of the Daraja API. Only production is supported: there is
    | no sandbox, so develop with DARAJA_STK_SIMULATE (see the stk block).
    |
    */
    'base_url' => 'https://api.safaricom.co.ke',

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
    | STK push attempts
    |--------------------------------------------------------------------------
    |
    | How long an attempt is remembered, in minutes, how long the screen waits
    | for the payer to answer the prompt before it shows how to pay by hand,
    | in seconds, and the least seconds between two "I've paid" checks of one
    | attempt. The screen follows the payment over a websocket, or by polling
    | when mode is poll (null picks the websocket when Reverb is set up, else
    | polling): poll_interval is the seconds between two polls and poll_duration
    | the seconds it keeps polling for before it stops. With simulate on, a push
    | is never sent to Safaricom and Daraja's answer is made up, so the
    | callbacks must be posted by hand, or by the simulator routes the package
    | then registers (daraja.simulate.stk and daraja.simulate.confirmation),
    | which post to the hooks named below. It is refused in production.
    |
    */
    'stk' => [
        'ttl' => env('DARAJA_STK_TTL', 60),
        'timeout' => env('DARAJA_STK_TIMEOUT', 60),
        'check_throttle' => env('DARAJA_STK_CHECK_THROTTLE', 10),
        'mode' => env('DARAJA_STK_MODE', 'websocket'),
        'poll_interval' => env('DARAJA_STK_POLL_INTERVAL', 3),
        'poll_duration' => env('DARAJA_STK_POLL_DURATION', 300),
        'simulate' => env('DARAJA_STK_SIMULATE', false),
        'hooks' => [
            'stk' => 'hooks.daraja.stk',
            'confirmation' => 'hooks.daraja.confirmation',
        ],
    ],

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
