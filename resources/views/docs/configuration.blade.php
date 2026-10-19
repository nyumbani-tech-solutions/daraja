@extends('essentials::docs.layout')

@section('page', 'Configuration')
@section('lead', 'Four keys, all with working defaults.')

@section('content')
    <x-essentials::docs.section id="options" title="Options">
        <x-essentials::docs.table :columns="['Key', 'Default', 'What it does']" :rows="[
            ['environment', 'production', 'sandbox or production. Set with DARAJA_ENVIRONMENT.'],
            ['base_urls', 'Safaricom sandbox and live', 'The base address of the API for each environment.'],
            ['allowed_ips', 'Safaricom addresses', 'The addresses EnsureRequestIsFromSafaricom lets through.'],
            ['skip_ip_check_in', 'local, testing', 'Environments where the middleware accepts any address, since Safaricom cannot reach a local machine.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="short-code" title="Credentials are not config">
        <p>The package does not read credentials from config. Build a <code>ShortCode</code> wherever your app keeps them, so one app can serve several paybills.</p>
        <x-essentials::docs.code>
            use Nyumbani\Daraja\ShortCode;

            new ShortCode(
                value: '174379',
                consumerKey: $key,
                consumerSecret: $secret,
                passKey: $passKey,
                tillNumber: null,
            );
        </x-essentials::docs.code>
        <x-essentials::docs.table :columns="['Argument', 'Use']" :rows="[
            ['value', 'The paybill or short code.'],
            ['consumerKey, consumerSecret', 'The OAuth credentials of the Daraja app.'],
            ['passKey', 'Signs an STK push. Required for stkPush(), otherwise optional.'],
            ['tillNumber', 'Set it for Buy Goods, so an STK push pays the till instead of the paybill.'],
        ]" />
    </x-essentials::docs.section>
@endsection
