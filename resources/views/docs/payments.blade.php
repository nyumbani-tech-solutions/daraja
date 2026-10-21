@extends('docs::layout')

@section('page', 'Payments')
@section('lead', 'Every call you can make to Safaricom: STK push, C2B registration and Pull transactions.')

@section('content')
    <x-docs::section id="live" title="Live buttons">
        <p>Several examples on this page have a button that calls Safaricom for real. They use the short code in your <code>.env</code> (<code>DARAJA_SHORT_CODE</code>, <code>DARAJA_CONSUMER_KEY</code>, <code>DARAJA_CONSUMER_SECRET</code>, <code>DARAJA_PASS_KEY</code> and, for a till, <code>DARAJA_TILL_NUMBER</code>) and always go to Safaricom's <strong>live</strong> environment, whatever <code>DARAJA_ENVIRONMENT</code> says. Nothing runs until you press the button.</p>
        <x-docs::callout type="note" title="These are real calls">
            An STK push prompts a real phone, and registering C2B or Pull addresses changes your live short code. C2B addresses can only be registered once. The docs are available only in the local environment.
        </x-docs::callout>
        <p>For Safaricom to post results back, <code>APP_URL</code> must be a public https address, such as a tunnel. The callback fields below already point at a receiver built into these docs, and <a href="{{ $docs->url('webhooks') }}#received" class="text-primary underline">Webhooks</a> shows what it received.</p>
        <x-docs::playground demo="live-token" manual="Get a live token" title="Check the live credentials" :fields="[
        ]" />
    </x-docs::section>

    <x-docs::section id="client" title="The client">
        <p>Every call goes through a <code>Daraja</code> client for one short code. Build the <a href="{{ $docs->url('configuration') }}" class="text-primary underline">ShortCode</a> from your credentials, then ask for a client.</p>
        <x-docs::code>
            use Nyumbani\Daraja\Daraja;

            $daraja = Daraja::for($shortCode);
        </x-docs::code>
        <p>Each call returns Laravel's HTTP client response, so use <code>successful()</code>, <code>failed()</code> and <code>json()</code> on it. Numbers you pass are normalised to <code>254XXXXXXXXX</code> with <code>daraja_phone()</code>, which accepts <code>0712345678</code>, <code>712345678</code> and <code>+254712345678</code> alike.</p>
    </x-docs::section>

    <x-docs::playground demo="phone" title="Try a phone number" :fields="[
        ['name' => 'phone', 'label' => 'A phone number', 'value' => '0712 345 678', 'placeholder' => 'e.g. 0712345678 or +254712345678'],
    ]" />

    <x-docs::section id="stk" title="STK push: ask a customer to pay">
        <p>The customer gets an M-Pesa prompt on their phone. Use it when you know who is paying and how much.</p>
        <x-docs::code>
            $response = $daraja->stkPush(
                phone: '0712345678',
                amount: 100,
                accountReference: 'INV-1',
                description: 'Order payment',
                callbackUrl: route('hooks.daraja.stk'),
            );
        </x-docs::code>
        <x-docs::table :columns="['Argument', 'Use']" :rows="[
            ['phone', 'The customer\'s number, in any common Kenyan format.'],
            ['amount', 'Whole shillings. A fraction is rounded to the nearest shilling.'],
            ['accountReference', 'What the customer sees as the account, such as an invoice number.'],
            ['description', 'A short note about the payment.'],
            ['callbackUrl', 'A public https address where Safaricom posts the result. See Webhooks.'],
        ]" />
        <x-docs::playground demo="stk-push" title="See the request an STK push sends" :fields="[
            ['name' => 'phone', 'label' => 'Phone', 'value' => '0712345678', 'placeholder' => 'e.g. 0712345678'],
            ['name' => 'amount', 'label' => 'Amount', 'value' => '100', 'placeholder' => 'e.g. 100'],
            ['name' => 'reference', 'label' => 'Account reference', 'value' => 'INV-1', 'placeholder' => 'e.g. INV-1'],
            ['name' => 'till', 'label' => 'Till number (optional)', 'value' => '', 'placeholder' => 'Leave empty for a paybill'],
        ]" />
        <p>Daraja answers straight away, and that answer only says whether the prompt was sent:</p>
        <x-docs::code lang="json" title="Accepted">
            {
                "MerchantRequestID": "29115-34620561-1",
                "CheckoutRequestID": "ws_CO_191220191020363925",
                "ResponseCode": "0",
                "ResponseDescription": "Success. Request accepted for processing",
                "CustomerMessage": "Success. Request accepted for processing"
            }
        </x-docs::code>
        <p>Save the <code>CheckoutRequestID</code> with your pending payment. The callback carries the same id, which is how you find the payment again.</p>
        <x-docs::callout type="warning" title="The pass key is required">
            Without <code>passKey</code> on the short code, <code>stkPush()</code> throws a <code>LogicException</code> before anything is sent. The password Safaricom checks is built from the short code, the pass key and a timestamp in East Africa Time, which the package does for you.
        </x-docs::callout>
            <x-docs::playground demo="live-stk-push" manual="Send the prompt" title="Send a real STK push" :fields="[
            ['name' => 'phone', 'label' => 'Phone to prompt', 'value' => '', 'placeholder' => 'e.g. 0712345678'],
            ['name' => 'amount', 'label' => 'Amount', 'value' => '1', 'placeholder' => 'e.g. 1'],
            ['name' => 'reference', 'label' => 'Account reference', 'value' => 'DOCS-1', 'placeholder' => 'e.g. DOCS-1'],
            ['name' => 'callback', 'label' => 'Callback URL (the docs receiver)', 'value' => route('daraja.docs.hook', ['hook' => 'stk']), 'placeholder' => 'A public https address'],
        ]" />
    </x-docs::section>

    <x-docs::section id="c2b" title="C2B: hear about every payment to a paybill">
        <p>Register the addresses Safaricom should call whenever someone pays your short code by hand (paybill and account number). Do this once per short code and environment, not on every request.</p>
        <x-docs::code>
            $response = $daraja->registerC2BUrls(
                validationUrl: route('hooks.daraja.validation'),
                confirmationUrl: route('hooks.daraja.confirmation'),
            );
        </x-docs::code>
        <p>The package registers with <code>ResponseType</code> <code>Completed</code>, so a payment goes through even if your validation address is unreachable. What to answer on each address is under <a href="{{ $docs->url('webhooks') }}" class="text-primary underline">Webhooks</a>.</p>
            <x-docs::playground demo="register-c2b" title="See the C2B registration request" :fields="[
            ['name' => 'validation', 'label' => 'Validation URL', 'value' => 'https://app.test/hooks/daraja/validation', 'placeholder' => 'e.g. https://app.test/hooks/daraja/validation'],
            ['name' => 'confirmation', 'label' => 'Confirmation URL', 'value' => 'https://app.test/hooks/daraja/confirmation', 'placeholder' => 'e.g. https://app.test/hooks/daraja/confirmation'],
        ]" />
            <x-docs::playground demo="live-register-c2b" manual="Register for real" title="Register the C2B addresses (once per short code)" :fields="[
            ['name' => 'validation', 'label' => 'Validation URL', 'value' => route('daraja.docs.hook', ['hook' => 'validation']), 'placeholder' => 'A public https address'],
            ['name' => 'confirmation', 'label' => 'Confirmation URL', 'value' => route('daraja.docs.hook', ['hook' => 'confirmation']), 'placeholder' => 'A public https address'],
        ]" />
    </x-docs::section>

    <x-docs::section id="pull" title="Pull: fetch payments you missed">
        <p>Pull transactions is a safety net for a callback that never arrived. Register once, then query a period whenever you need to reconcile.</p>
        <x-docs::code>
            use Illuminate\Support\Carbon;

            //  Register the number notified about Pull transactions, and the callback address
            $daraja->registerPullUrl('0712345678', route('hooks.daraja.pull'));

            //  The last 48 hours
            $response = $daraja->pullTransactions();

            //  A period, from the n-th result onward
            $response = $daraja->pullTransactions(
                from: Carbon::parse('2026-10-01 00:00:00'),
                to: Carbon::parse('2026-10-02 00:00:00'),
                offset: 0,
            );
        </x-docs::code>
        <p>Times are converted to East Africa Time before they are sent, so pass any timezone you like. Use <code>offset</code> to page through a long period.</p>
            <x-docs::playground demo="register-pull" title="See the Pull registration request" :fields="[
            ['name' => 'number', 'label' => 'Nominated number', 'value' => '0712345678', 'placeholder' => 'e.g. 0712345678'],
            ['name' => 'callback', 'label' => 'Callback URL', 'value' => 'https://app.test/hooks/daraja/pull', 'placeholder' => 'e.g. https://app.test/hooks/daraja/pull'],
        ]" />
        <x-docs::playground demo="pull" title="See the Pull query request" :fields="[
            ['name' => 'from', 'label' => 'From (empty for 48 hours ago)', 'type' => 'datetime', 'value' => ''],
            ['name' => 'to', 'label' => 'To (empty for now)', 'type' => 'datetime', 'value' => ''],
            ['name' => 'offset', 'label' => 'Offset', 'value' => '0', 'placeholder' => 'e.g. 0'],
        ]" />
            <x-docs::playground demo="live-register-pull" manual="Register for real" title="Register the Pull address" :fields="[
            ['name' => 'number', 'label' => 'Nominated number', 'value' => '', 'placeholder' => 'e.g. 0712345678'],
            ['name' => 'callback', 'label' => 'Callback URL (the docs receiver)', 'value' => route('daraja.docs.hook', ['hook' => 'pull']), 'placeholder' => 'A public https address'],
        ]" />
        <x-docs::playground demo="live-pull" manual="Query Safaricom" title="Pull the transactions of a period" :fields="[
            ['name' => 'from', 'label' => 'From (empty for 48 hours ago)', 'type' => 'datetime', 'value' => ''],
            ['name' => 'to', 'label' => 'To (empty for now)', 'type' => 'datetime', 'value' => ''],
            ['name' => 'offset', 'label' => 'Offset', 'value' => '0', 'placeholder' => 'e.g. 0'],
        ]" />
    </x-docs::section>

    <x-docs::section id="responses" title="Read the response">
        <p>Daraja can answer HTTP 200 and still refuse the request, so check the code in the body as well as the status.</p>
        <x-docs::code>
            if ($response->failed() || $response->json('ResponseCode') !== '0') {
                return back()->alert('error', trans('alerts.payments.failed'));
            }
        </x-docs::code>
        <x-docs::table :columns="['Call', 'Success code']" :rows="[
            ['stkPush', '0'],
            ['registerPullUrl, pullTransactions', '1000'],
        ]" />
        <p>When a call fails, log <code>$response->body()</code>. Safaricom explains the reason there, such as a wrong pass key or a number that is not on M-Pesa.</p>
    </x-docs::section>

    <x-docs::section id="errors" title="When something goes wrong">
        <x-docs::table :columns="['What you see', 'Why', 'What to do']" :rows="[
            ['DarajaException: Could not get a Daraja access token', 'Daraja could not be reached, or the consumer key and secret do not match the environment.', 'Check DARAJA_ENVIRONMENT first, then the credentials. A sandbox key does not work in production.'],
            ['LogicException: An STK push needs the pass key', 'The ShortCode has no passKey.', 'Pass the short code\'s pass key.'],
            ['ResponseCode other than 0 on an STK push', 'Safaricom refused the request.', 'Read ResponseDescription in the body.'],
            ['STK push accepted but no callback arrives', 'The callback address is not public, or is blocked by the guard.', 'Use a tunnel locally and check the webhook log for a Rejected a Daraja request warning.'],
        ]" />
        <p>Catch <code>DarajaException</code> where the user triggers the call, and flash an error toast. It means the call could not be made at all, as opposed to Daraja refusing a request.</p>
    </x-docs::section>

    <x-docs::section id="testing" title="Test it without calling Daraja">
        <p>Never call Daraja from the test suite. Fake the HTTP client, and the package takes its token and its answer from the fake.</p>
        <x-docs::code title="tests/Feature/PaymentTest.php">
            use Illuminate\Support\Facades\Http;
            use Nyumbani\Daraja\Daraja;
            use Nyumbani\Daraja\ShortCode;

            it('prompts the customer', function () {
                Http::fake([
                    '*/oauth/v1/generate*' => Http::response(['access_token' => 'token']),
                    '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_CO_1']),
                ]);

                $shortCode = new ShortCode('174379', 'key', 'secret', passKey: 'pass');

                $response = Daraja::for($shortCode)->sandbox()->stkPush('0712345678', 100, 'INV-1', 'Order payment', 'https://app.test/hooks/daraja/stk');

                expect($response->json('ResponseCode'))->toBe('0');

                Http::assertSent(fn ($request) => $request['PartyA'] === '254712345678' && $request['Amount'] === 100);
            });
        </x-docs::code>
    </x-docs::section>
@endsection
