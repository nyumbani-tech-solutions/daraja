@extends('essentials::docs.layout')

@section('page', 'Webhooks')
@section('lead', 'Receive what Safaricom posts, and let only Safaricom through.')

@section('content')
    <x-essentials::docs.section id="routes" title="Routes">
        <p>Safaricom posts to your app, so your app owns the routes and the handling. Keep them outside the signed-in group, guarded, and exempt from CSRF.</p>
        <x-essentials::docs.code title="routes/web.php">
            use App\Http\Controllers\DarajaHookController;
            use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
            use Illuminate\Support\Facades\Route;
            use Nyumbani\Daraja\Http\Middleware\EnsureRequestIsFromSafaricom;

            Route::prefix('hooks/daraja')
                ->middleware(EnsureRequestIsFromSafaricom::class)
                ->withoutMiddleware(PreventRequestForgery::class)
                ->group(function () {
                    Route::post('validation', [DarajaHookController::class, 'validation']);
                    Route::post('confirmation', [DarajaHookController::class, 'confirmation']);
                    Route::post('stk', [DarajaHookController::class, 'stk']);
                    Route::post('pull', [DarajaHookController::class, 'pull']);
                });
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="guard" title="The guard">
        <p><code>EnsureRequestIsFromSafaricom</code> lets through the addresses in <code>daraja.allowed_ips</code> and skips the check in <code>daraja.skip_ip_check_in</code>, which is <code>local</code> and <code>testing</code>. Any other address gets <code>C2B00016</code> and a warning in the log.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="answer" title="Answer quickly">
        <x-essentials::docs.code>
            use App\Models\DarajaPayload;

            DarajaPayload::create([
                'type' => 'c2b-confirmation',
                'payload' => $request->all(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
        </x-essentials::docs.code>
        <ul>
            <li>Keep the raw payload first, then do the slow work in a queued job.</li>
            <li>Callbacks can repeat, so key the handler on the transaction id.</li>
            <li>Treat the payload as untrusted and validate amounts and numbers.</li>
        </ul>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="local" title="Locally">
        <p>Safaricom cannot reach your machine. Expose the app through a tunnel, register the tunnel URLs in the sandbox, and test handlers with <code>$this->postJson('/hooks/daraja/confirmation', $payload)</code>.</p>
    </x-essentials::docs.section>
@endsection
