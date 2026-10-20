@extends('essentials::docs.layout')

@section('page', 'Introduction')
@section('lead', 'A small Laravel client for Safaricom Daraja: take M-Pesa payments and know when they land.')

@section('content')
    <x-essentials::docs.section id="what-it-is" title="What Daraja is">
        <p>The package does two jobs. It makes the calls <strong>to</strong> Safaricom (ask a customer to pay, register your callback addresses, query transactions), and it guards the routes Safaricom calls <strong>back</strong>. Everything else, such as which invoice a payment belongs to, stays in your app. It needs only Laravel.</p>
        <x-essentials::docs.table :columns="['Piece', 'What it does']" :rows="[
            ['Daraja', 'The client: STK push, C2B URL registration and Pull transactions.'],
            ['ShortCode', 'One paybill or till with its credentials, built wherever your app keeps them.'],
            ['EnsureRequestIsFromSafaricom', 'Middleware that lets only Safaricom addresses reach your callback routes.'],
            ['daraja_phone()', 'Turns a Kenyan number into the 254XXXXXXXXX format Daraja expects.'],
            ['DarajaException', 'Thrown when Daraja cannot be reached or refuses the credentials.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="flow" title="How a payment travels">
        <p>Every payment is a round trip. Your app asks, the customer approves on their phone, and Safaricom tells your app the result on a route you own. The package covers the arrows into Safaricom and the guard on the arrow back.</p>
        <x-essentials::docs.steps :steps="[
            ['title' => 'Your app asks', 'actor' => 'Your app to Safaricom', 'detail' => 'You call stkPush() with the phone, amount, an account reference and a public callback address.', 'code' => 'Daraja::for($shortCode)->stkPush($phone, 100, \'INV-1\', \'Order\', $callbackUrl);'],
            ['title' => 'Safaricom accepts', 'actor' => 'Safaricom to your app', 'detail' => 'The answer comes straight back and only says the prompt was sent. Save the CheckoutRequestID with a pending payment.', 'code' => 'ResponseCode 0, plus the CheckoutRequestID (ws_CO_...)'],
            ['title' => 'The customer is prompted', 'actor' => 'Safaricom to the phone', 'detail' => 'The customer sees an M-Pesa prompt for the amount and the account reference you sent.'],
            ['title' => 'The customer decides', 'actor' => 'The customer', 'detail' => 'They enter their PIN, cancel, or ignore it until it times out.'],
            ['title' => 'Safaricom reports the result', 'actor' => 'Safaricom to your callback route', 'detail' => 'It posts to your callback address. EnsureRequestIsFromSafaricom checks the sender first, then your controller stores the payload and answers at once.', 'code' => 'Body.stkCallback.ResultCode is 0 when the customer paid'],
            ['title' => 'Your app settles the payment', 'actor' => 'Your queued job', 'detail' => 'Find the payment by CheckoutRequestID and mark it paid or failed, once, even if the callback arrives twice.'],
        ]" />
        <x-essentials::docs.callout type="note" title="Step 2 is not the payment">
            "Request accepted" only means Safaricom will try to prompt the customer. The money has moved only when step 5 reports a success.
        </x-essentials::docs.callout>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="choose" title="Which call do I need?">
        <x-essentials::docs.table :columns="['I want to...', 'Use', 'Your app then receives']" :rows="[
            ['Ask a customer to pay a specific amount now', 'stkPush()', 'One STK callback with the result.'],
            ['Be told whenever anyone pays my paybill or till', 'registerC2BUrls()', 'A validation and a confirmation callback per payment.'],
            ['Fetch payments I may have missed', 'registerPullUrl(), pullTransactions()', 'A Pull notification, and the list you query.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="explore" title="Read in this order">
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-essentials::docs.card title="1. Installation" href="{{ $docs->url('installation') }}">Require the package, set the environment and prove the credentials work.</x-essentials::docs.card>
            <x-essentials::docs.card title="2. Configuration" href="{{ $docs->url('configuration') }}">What each key does, and why credentials are not config.</x-essentials::docs.card>
            <x-essentials::docs.card title="3. Walkthrough" href="{{ $docs->url('walkthrough') }}">Take a first payment: push, callback, mark paid, test.</x-essentials::docs.card>
            <x-essentials::docs.card title="4. Payments" href="{{ $docs->url('payments') }}">Every outgoing call with its arguments and responses.</x-essentials::docs.card>
            <x-essentials::docs.card title="5. Webhooks" href="{{ $docs->url('webhooks') }}">The callbacks Safaricom sends, what to answer, and how to guard them.</x-essentials::docs.card>
        </div>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="ai" title="For AI agents">
        <p>The package ships two Boost skills, <code>nyumbani-daraja-payments</code> and <code>nyumbani-daraja-webhooks</code>. Add <code>nyumbani/daraja</code> to the packages in <code>boost.json</code> and run <code>php artisan boost:update</code>.</p>
    </x-essentials::docs.section>
@endsection
