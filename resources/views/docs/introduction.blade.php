@extends('essentials::docs.layout')

@section('page', 'Introduction')
@section('lead', 'A small Laravel client for Safaricom Daraja: take M-Pesa payments and know when they land.')

@section('content')
    <x-essentials::docs.section id="what-it-is" title="What Daraja is">
        <p>The package makes the calls to Safaricom and guards the routes Safaricom calls back. Everything else, such as which order a payment belongs to, stays in your app. It needs only Laravel.</p>
        <x-essentials::docs.table :columns="['Piece', 'What it does']" :rows="[
            ['Daraja', 'The client: STK push, C2B URL registration and Pull transactions.'],
            ['ShortCode', 'One paybill or till with its credentials, built wherever your app keeps them.'],
            ['EnsureRequestIsFromSafaricom', 'Middleware that lets only Safaricom addresses reach your callback routes.'],
            ['daraja_phone()', 'Turns a Kenyan number into the 254XXXXXXXXX format Daraja expects.'],
            ['DarajaException', 'Thrown when Daraja cannot be reached or refuses the credentials.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="explore" title="Explore by concept">
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-essentials::docs.card title="Installation" href="{{ $docs->url('installation') }}">Require the package and set the environment.</x-essentials::docs.card>
            <x-essentials::docs.card title="Payments" href="{{ $docs->url('payments') }}">Build a short code and make STK push, C2B and Pull calls.</x-essentials::docs.card>
            <x-essentials::docs.card title="Webhooks" href="{{ $docs->url('webhooks') }}">Receive Safaricom callbacks and guard the routes.</x-essentials::docs.card>
            <x-essentials::docs.card title="Configuration" href="{{ $docs->url('configuration') }}">Environment, addresses and what each key does.</x-essentials::docs.card>
        </div>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="ai" title="For AI agents">
        <p>The package ships two Boost skills, <code>nyumbani-daraja-payments</code> and <code>nyumbani-daraja-webhooks</code>. Add <code>nyumbani/daraja</code> to the packages in <code>boost.json</code> and run <code>php artisan boost:update</code>.</p>
    </x-essentials::docs.section>
@endsection
