@extends('essentials::docs.layout')

@section('page', 'Payments')
@section('lead', 'STK push, C2B registration and Pull transactions through Safaricom Daraja.')

@section('content')
    <x-essentials::docs.section id="setup" title="Set up">
        <x-essentials::docs.code lang="bash" title=".env">
            DARAJA_ENVIRONMENT=sandbox
        </x-essentials::docs.code>
        <p>The environment defaults to <code>production</code>, so set <code>sandbox</code> while developing. Call <code>sandbox()</code> or <code>live()</code> to change it for one client.</p>
        <x-essentials::docs.callout type="warning">Credentials stay in <code>.env</code> or in a database row. Never put them in code, config defaults or <code>.env.example</code>.</x-essentials::docs.callout>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="short-code" title="A short code">
        <x-essentials::docs.code>
            use Nyumbani\Daraja\ShortCode;

            $shortCode = new ShortCode(
                value: '174379',
                consumerKey: $key,
                consumerSecret: $secret,
                passKey: $passKey,
                tillNumber: null, // set for Buy Goods
            );
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="calls" title="Calls">
        <x-essentials::docs.code>
            use Nyumbani\Daraja\Daraja;

            $daraja = Daraja::for($shortCode);

            $daraja->stkPush('0712345678', 100, 'INV-1', 'Order payment', $callbackUrl);
            $daraja->registerC2BUrls($validationUrl, $confirmationUrl);
            $daraja->registerPullUrl('0712345678', $callbackUrl);
            $daraja->pullTransactions();   // the last 48 hours, or pass from and to
        </x-essentials::docs.code>
        <p>An STK push needs the pass key and a public callback URL. Numbers are normalised to <code>254XXXXXXXXX</code> with <code>daraja_phone()</code>.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="responses" title="Read the response">
        <p>Each call returns Laravel's HTTP response. Daraja can answer 200 with a failure, so check the code as well.</p>
        <x-essentials::docs.code>
            $response = $daraja->stkPush($phone, $amount, $reference, $description, $callbackUrl);

            if ($response->failed() || $response->json('ResponseCode') !== '0') {
                return back()->alert('error', __('alerts.payment_failed'));
            }
        </x-essentials::docs.code>
        <x-essentials::docs.table :columns="['Call', 'Success code']" :rows="[
            ['stkPush', '0'],
            ['registerPullUrl, pullTransactions', '1000'],
        ]" />
        <p>A <code>DarajaException</code> means Daraja could not be reached or refused the credentials. In tests, fake the HTTP client; never call Daraja from the suite.</p>
    </x-essentials::docs.section>
@endsection
