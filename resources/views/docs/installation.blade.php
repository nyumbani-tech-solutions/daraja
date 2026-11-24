@extends('docs::layout')

@section('page', 'Installation')
@section('lead', 'Require the package, keep the credentials safe and prove your credentials work. There is nothing to register.')

@section('content')
    <x-docs::section id="before" title="Before you start">
        <p>You need a Daraja app from the <a href="https://developer.safaricom.co.ke" class="text-primary underline" target="_blank" rel="noopener">Safaricom developer portal</a>. It gives you a <strong>consumer key</strong> and <strong>consumer secret</strong>. For STK push you also need the short code's <strong>pass key</strong>. The examples use <code>174379</code> as a sample short code.</p>
    </x-docs::section>

    <x-docs::section id="require" title="1. Require the package">
        <x-docs::code lang="bash" title="Terminal">
            composer require nyumbani/daraja
        </x-docs::code>
        <p>Laravel discovers the service provider on its own.</p>
    </x-docs::section>

    <x-docs::section id="credentials" title="2. Keep the credentials safe">
        <p>Every call goes to Safaricom's production API. There is no sandbox, so a push to a real number prompts a real phone. While developing, set <code>DARAJA_STK_SIMULATE=true</code> so no push is sent (see STK push), and fake the HTTP client in tests.</p>
        <x-docs::callout type="warning" title="Credentials">
            Keep the consumer key, secret and pass key in the git-ignored <code>.env</code> or in a database row. Never in code, config defaults or <code>.env.example</code>.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="check" title="3. Check the credentials">
        <p>Ask Daraja for an access token. If you get one back, the key and secret are right.</p>
        <x-docs::code lang="bash" title="Terminal">
            php artisan tinker --execute 'echo strlen(Nyumbani\Daraja\Daraja::for(new Nyumbani\Daraja\ShortCode("174379", "YOUR_KEY", "YOUR_SECRET"))->accessToken()) > 0 ? "Credentials work" : "No token";'
        </x-docs::code>
        <p>A wrong key or secret throws a <code>DarajaException</code> that carries Safaricom's answer. The token is cached for 55 minutes, so repeat calls cost nothing.</p>
    </x-docs::section>

    <x-docs::section id="publish" title="4. Publish the config (optional)">
        <x-docs::code lang="bash" title="Terminal">
            php artisan vendor:publish --tag=daraja-config
        </x-docs::code>
        <p>Only needed to change the allowed addresses, the base url or the app environments in which the address check is skipped. In an app that uses Nyumbani Essentials, <code>php artisan nyumbani:install</code> publishes it for you.</p>
    </x-docs::section>

    <x-docs::section id="next" title="Next">
        <p>Read <a href="{{ $docs->url('configuration') }}" class="text-primary underline">Configuration</a> to see where credentials belong, then follow the <a href="{{ $docs->url('walkthrough') }}" class="text-primary underline">Walkthrough</a> to take a first payment.</p>
    </x-docs::section>
@endsection
