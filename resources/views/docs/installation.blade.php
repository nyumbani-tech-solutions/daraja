@extends('essentials::docs.layout')

@section('page', 'Installation')
@section('lead', 'Require the package, choose an environment and prove your credentials work. There is nothing to register.')

@section('content')
    <x-essentials::docs.section id="before" title="Before you start">
        <p>You need a Daraja app from the <a href="https://developer.safaricom.co.ke" class="text-primary underline" target="_blank" rel="noopener">Safaricom developer portal</a>. It gives you a <strong>consumer key</strong> and <strong>consumer secret</strong>. For STK push you also need the short code's <strong>pass key</strong>. The portal's sandbox short code is <code>174379</code>, which is the one the examples use.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="require" title="1. Require the package">
        <x-essentials::docs.code lang="bash" title="Terminal">
            composer require nyumbani/daraja
        </x-essentials::docs.code>
        <p>Laravel discovers the service provider on its own.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="environment" title="2. Set the environment">
        <x-essentials::docs.code lang="bash" title=".env">
            DARAJA_ENVIRONMENT=sandbox
        </x-essentials::docs.code>
        <p>It defaults to <code>production</code>, so set <code>sandbox</code> while developing. For one client only, call <code>sandbox()</code> or <code>live()</code>.</p>
        <x-essentials::docs.callout type="warning" title="Credentials">
            Keep the consumer key, secret and pass key in the git-ignored <code>.env</code> or in a database row. Never in code, config defaults or <code>.env.example</code>.
        </x-essentials::docs.callout>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="check" title="3. Check the credentials">
        <p>Ask Daraja for an access token. If you get one back, the key, secret and environment are right.</p>
        <x-essentials::docs.code lang="bash" title="Terminal">
            php artisan tinker --execute 'echo strlen(Nyumbani\Daraja\Daraja::for(new Nyumbani\Daraja\ShortCode("174379", "YOUR_KEY", "YOUR_SECRET"))->accessToken()) > 0 ? "Credentials work" : "No token";'
        </x-essentials::docs.code>
        <p>A wrong key or secret throws a <code>DarajaException</code> that carries Safaricom's answer. The token is cached for 55 minutes, so repeat calls cost nothing.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="publish" title="4. Publish the config (optional)">
        <x-essentials::docs.code lang="bash" title="Terminal">
            php artisan vendor:publish --tag=daraja-config
        </x-essentials::docs.code>
        <p>Only needed to change the allowed addresses or the environments in which the address check is skipped.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="next" title="Next">
        <p>Read <a href="{{ $docs->url('configuration') }}" class="text-primary underline">Configuration</a> to see where credentials belong, then follow the <a href="{{ $docs->url('walkthrough') }}" class="text-primary underline">Walkthrough</a> to take a first payment.</p>
    </x-essentials::docs.section>
@endsection
