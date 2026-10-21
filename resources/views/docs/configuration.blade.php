@extends('docs::layout')

@section('page', 'Configuration')
@section('lead', 'Four keys, all with working defaults, and credentials that deliberately live elsewhere.')

@section('content')
    <x-docs::section id="options" title="Options">
        <p>Apps inherit these defaults. Publish the file with <code>php artisan vendor:publish --tag=daraja-config</code> only to change one.</p>
        <x-docs::table :columns="['Key', 'Default', 'What it does']" :rows="[
            ['environment', 'production', 'sandbox or production. Set with DARAJA_ENVIRONMENT.'],
            ['base_urls', 'Safaricom sandbox and live', 'The base address of the API for each environment.'],
            ['allowed_ips', 'Safaricom addresses', 'The addresses EnsureRequestIsFromSafaricom lets through.'],
            ['skip_ip_check_in', 'local, testing', 'Environments where the middleware accepts any address, since Safaricom cannot reach a local machine.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="docs-credentials" title="Credentials for the docs">
        <p>The live buttons in these docs read <code>daraja.docs.*</code>, which come from <code>DARAJA_SHORT_CODE</code>, <code>DARAJA_CONSUMER_KEY</code>, <code>DARAJA_CONSUMER_SECRET</code>, <code>DARAJA_PASS_KEY</code> and <code>DARAJA_TILL_NUMBER</code>. Nothing else in the package reads them: your app still builds its own <code>ShortCode</code>.</p>
    </x-docs::section>

    <x-docs::section id="environment" title="Choosing the environment">
        <p>The environment decides which Safaricom address every call goes to. It is the configured one unless a client overrides it, and the override wins.</p>
        <x-docs::code title="Per client">
            use Nyumbani\Daraja\Daraja;

            Daraja::for($shortCode)->sandbox()->stkPush(...);   // always the sandbox
            Daraja::for($shortCode)->live()->stkPush(...);      // always production
            Daraja::for($shortCode)->stkPush(...);              // whatever DARAJA_ENVIRONMENT says
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="short-code" title="Credentials are not config">
        <p>The package does not read credentials from config. Build a <code>ShortCode</code> wherever your app keeps them, so one app can serve several paybills. Reading them from the environment is fine for an app with one paybill; an app with many keeps them in a database row.</p>
        <x-docs::tabs :labels="['One paybill', 'Many paybills']">
            <x-docs::tab label="One paybill">
                <x-docs::code title="From the environment">
                    use Nyumbani\Daraja\ShortCode;

                    $shortCode = new ShortCode(
                        value: '174379',
                        consumerKey: env('MPESA_CONSUMER_KEY'),
                        consumerSecret: env('MPESA_CONSUMER_SECRET'),
                        passKey: env('MPESA_PASS_KEY'),
                    );
                </x-docs::code>
            </x-docs::tab>
            <x-docs::tab label="Many paybills">
                <x-docs::code title="From a model">
                    use Nyumbani\Daraja\ShortCode;

                    $shortCode = new ShortCode(
                        value: $paybill->number,
                        consumerKey: $paybill->consumer_key,
                        consumerSecret: $paybill->consumer_secret,
                        passKey: $paybill->pass_key,
                        tillNumber: $paybill->till_number,
                    );
                </x-docs::code>
            </x-docs::tab>
        </x-docs::tabs>
        <x-docs::table :columns="['Argument', 'Use']" :rows="[
            ['value', 'The paybill or short code.'],
            ['consumerKey, consumerSecret', 'The OAuth credentials of the Daraja app.'],
            ['passKey', 'Signs an STK push. Required for stkPush(), otherwise optional.'],
            ['tillNumber', 'Set it for Buy Goods, so an STK push pays the till instead of the paybill.'],
        ]" />
        <x-docs::playground demo="short-code" title="Paybill or till?" :fields="[
            ['name' => 'value', 'label' => 'Short code (value)', 'value' => '174379', 'placeholder' => 'e.g. 174379'],
            ['name' => 'till', 'label' => 'Till number (optional)', 'value' => '', 'placeholder' => 'Leave empty for a paybill, or e.g. 5551234'],
        ]" />
        <x-docs::callout type="tip" title="Paybill or till?">
            With no <code>tillNumber</code> an STK push is a <em>Customer PayBill</em> payment to <code>value</code>. With one, it is a <em>Customer Buy Goods</em> payment to the till, while <code>value</code> still identifies your Daraja short code.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="token" title="The access token">
        <p>Every call needs an OAuth token. The client fetches it for you the first time, and caches it for 55 minutes in your default cache store, per environment and short code. A failed token request is never cached. Calls time out after 30 seconds.</p>
    </x-docs::section>
@endsection
