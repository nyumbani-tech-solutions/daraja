<?php

return [
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
];
