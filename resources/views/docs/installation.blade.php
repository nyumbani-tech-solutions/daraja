@extends('essentials::docs.layout')

@section('page', 'Installation')
@section('lead', 'Require the package and choose an environment. There is nothing to register.')

@section('content')
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

    <x-essentials::docs.section id="publish" title="3. Publish the config (optional)">
        <x-essentials::docs.code lang="bash" title="Terminal">
            php artisan vendor:publish --tag=daraja-config
        </x-essentials::docs.code>
        <p>Only needed to change the allowed addresses or the environments in which the address check is skipped.</p>
    </x-essentials::docs.section>
@endsection
