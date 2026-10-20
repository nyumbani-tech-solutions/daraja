@extends('essentials::docs.layout')

@section('page', 'Webhooks')
@section('lead', 'Receive what Safaricom posts, answer it quickly, and let only Safaricom through.')

@section('content')
    <x-essentials::docs.section id="overview" title="Overview">
        <p>Safaricom posts to your app, so your app owns the routes and the handling. The package supplies one thing: a guard that rejects anyone who is not Safaricom. Four kinds of callback exist.</p>
        <x-essentials::docs.table :columns="['Callback', 'Sent when', 'You answer']" :rows="[
            ['STK push result', 'The customer approved, cancelled or ignored a prompt from stkPush().', 'Anything with HTTP 200. Safaricom does not act on it.'],
            ['C2B validation', 'Someone pays your paybill, before it completes (only if validation is enabled on your short code).', 'ResultCode 0 to accept, or a C2B error code to refuse.'],
            ['C2B confirmation', 'A C2B payment completed.', 'ResultCode 0.'],
            ['Pull notification', 'Transactions are available to pull.', 'Anything with HTTP 200.'],
        ]" />
    </x-essentials::docs.section>

    <x-essentials::docs.section id="routes" title="1. Declare the routes">
        <p>Keep them outside the signed-in group, guarded, and exempt from CSRF. Safaricom is not a browser, so it has no session and no token.</p>
        <x-essentials::docs.code title="routes/web.php">
            use App\Http\Controllers\DarajaHookController;
            use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
            use Illuminate\Support\Facades\Route;
            use Nyumbani\Daraja\Http\Middleware\EnsureRequestIsFromSafaricom;

            Route::prefix('hooks/daraja')
                ->name('hooks.daraja.')
                ->middleware(EnsureRequestIsFromSafaricom::class)
                ->withoutMiddleware(PreventRequestForgery::class)
                ->group(function () {
                    Route::post('validation', [DarajaHookController::class, 'validation'])->name('validation');
                    Route::post('confirmation', [DarajaHookController::class, 'confirmation'])->name('confirmation');
                    Route::post('stk', [DarajaHookController::class, 'stk'])->name('stk');
                    Route::post('pull', [DarajaHookController::class, 'pull'])->name('pull');
                });
        </x-essentials::docs.code>
        <p>The names let the rest of your app write <code>route('hooks.daraja.stk')</code> when it asks for a payment. With several paybills, put the paybill in the address, such as <code>hooks/{paybill}/confirm</code>, so the controller knows which short code a request belongs to.</p>
        <x-essentials::docs.callout type="warning" title="Safaricom needs a public https address">
            Register addresses on a real domain with a valid certificate. Safaricom will not call <code>localhost</code> or a self-signed address. See <a href="#local">Locally</a>.
        </x-essentials::docs.callout>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="guard" title="2. The guard">
        <p><code>EnsureRequestIsFromSafaricom</code> compares the request's IP address with <code>daraja.allowed_ips</code>.</p>
        <x-essentials::docs.table :columns="['Situation', 'Result']" :rows="[
            ['The environment is in daraja.skip_ip_check_in (local, testing)', 'The request goes through, whatever its address.'],
            ['The address is in daraja.allowed_ips', 'The request goes through.'],
            ['Any other address', 'HTTP 200 with ResultCode C2B00016 and ResultDesc Rejected, and a warning in the log: Rejected a Daraja request.'],
        ]" />
        <x-essentials::docs.callout type="warning" title="Behind a proxy or load balancer">
            The guard reads <code>$request->ip()</code>. If your app sits behind a proxy, configure Laravel's trusted proxies, or every request looks like it comes from the proxy and is rejected.
        </x-essentials::docs.callout>
        <x-essentials::docs.playground demo="guard" title="Try the guard" :fields="[
            ['name' => 'ip', 'label' => 'Address the request comes from', 'value' => '1.2.3.4', 'placeholder' => 'e.g. 196.201.214.200 (a Safaricom address) or 1.2.3.4'],
            ['name' => 'environment', 'label' => 'Your app\'s environment', 'type' => 'select', 'value' => 'production', 'options' => ['production' => 'production', 'staging' => 'staging', 'local' => 'local (check skipped)', 'testing' => 'testing (check skipped)']],
        ]" />
        <p>Never remove the middleware to get a callback through. Fix the address list instead, in a published <code>config/daraja.php</code>.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="middleware" title="How the middleware works">
        <p>Laravel runs <code>EnsureRequestIsFromSafaricom</code> before your controller. It asks one question, <em>did this request come from a Safaricom address?</em>, and either passes the request on or answers for you. Step through what happens to a request:</p>
        <x-essentials::docs.steps :steps="[
            ['title' => 'A request reaches your route', 'actor' => 'Laravel, before your controller', 'detail' => 'Safaricom posts to one of your callback routes. Laravel runs the middleware listed on that route first. Your controller has not run yet.'],
            ['title' => 'Is this environment exempt?', 'actor' => 'daraja.skip_ip_check_in', 'detail' => 'If the app environment is in this list (local and testing by default), the request goes straight through, whatever its address. Safaricom cannot reach a local machine, so developers and tests would otherwise be blocked.'],
            ['title' => 'Is the address on the list?', 'actor' => 'daraja.allowed_ips', 'detail' => 'The middleware reads the request IP and compares it, exactly, with the list of Safaricom addresses. A match goes through to your controller.'],
            ['title' => 'Otherwise it is turned away', 'actor' => 'The middleware answers', 'detail' => 'Your controller never runs. The middleware writes a warning to the log with the address, and answers HTTP 200 with ResultCode C2B00016 and ResultDesc Rejected.'],
        ]" />

        <h3>The code</h3>
        <p>The whole middleware is this short. Nothing is hidden, so you can read it any time in <code>src/Http/Middleware/EnsureRequestIsFromSafaricom.php</code>.</p>
        <x-essentials::docs.code title="EnsureRequestIsFromSafaricom::handle()">
            use Closure;
            use Illuminate\Http\JsonResponse;
            use Illuminate\Http\Request;
            use Illuminate\Http\Response;
            use Illuminate\Support\Facades\Log;

            public function handle(Request $request, Closure $next): JsonResponse|Response
            {
                //  Skip the check in the configured environments
                if (app()->environment(config('daraja.skip_ip_check_in', []))) {
                    return $next($request);
                }

                //  Let Safaricom's addresses through
                if (in_array($request->ip(), config('daraja.allowed_ips', []), true)) {
                    return $next($request);
                }

                //  Log the rejection
                Log::warning('Rejected a Daraja request', ['ip' => $request->ip()]);

                //  Return a failure response
                return response()->json(['ResultCode' => 'C2B00016', 'ResultDesc' => 'Rejected']);
            }
        </x-essentials::docs.code>

        <h3>Why it answers 200 and not 403</h3>
        <p>A rejected request still gets HTTP 200, with Safaricom's own failure code <code>C2B00016</code> (<em>other error</em>). The caller is either a stranger, who learns nothing useful, or a real Safaricom address that was missing from your list, which Safaricom reads as a refusal in a format it understands. The warning in your log tells you which it was.</p>

        <h3>What it does and does not protect</h3>
        <x-essentials::docs.table :columns="['It does', 'It does not']" :rows="[
            ['Stop anyone outside the Safaricom address list from reaching the controller.', 'Prove who sent the payload. Daraja callbacks carry no signature, so the address is the only check.'],
            ['Log every rejected address, so you can spot probing or a stale list.', 'Make the payload trustworthy. Validate amounts and numbers yourself, and ignore a repeated transaction id.'],
            ['Keep local development and tests working without a tunnel.', 'Work behind a proxy until Laravel trusts it. Otherwise every request looks like it comes from the proxy.'],
        ]" />

        <h3>Using it</h3>
        <ul>
            <li><strong>Put it on the callback routes only.</strong> Not on your signed-in pages: those are for browsers, not Safaricom.</li>
            <li><strong>Keep it before the controller</strong> and apply it to the whole route group, as in the routes above, so a new callback route cannot forget it.</li>
            <li><strong>Add it to the address list, not around it.</strong> When Safaricom adds an address, publish <code>config/daraja.php</code> and add it to <code>allowed_ips</code>.</li>
            <li><strong>Prove it in a test.</strong> Set <code>daraja.skip_ip_check_in</code> to <code>[]</code>: an unknown address gets <code>C2B00016</code> and an allowed one reaches the controller. See <a href="#local">Locally</a>.</li>
        </ul>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="answer" title="3. Answer quickly, work later">
        <p>Safaricom waits for your answer and retries when it is slow. Store the raw payload first, answer, and do the real work in a queued job.</p>
        <x-essentials::docs.code title="app/Http/Controllers/DarajaHookController.php">
            use App\Jobs\SettleStkPaymentJob;
            use App\Models\DarajaPayload;
            use Illuminate\Http\JsonResponse;
            use Illuminate\Http\Request;

            class DarajaHookController
            {
                public function stk(Request $request): JsonResponse
                {
                    //  Keep the raw payload first
                    $payload = DarajaPayload::create([
                        'type' => 'stk',
                        'payload' => $request->all(),
                        'ip_address' => $request->ip(),
                    ]);

                    //  Do the slow work later
                    SettleStkPaymentJob::dispatch($payload);

                    //  Answer Safaricom
                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
                }
            }
        </x-essentials::docs.code>
        <ul>
            <li><strong>Be idempotent.</strong> Callbacks can arrive more than once. Key the handler on the transaction id: <code>CheckoutRequestID</code> for an STK push, <code>TransID</code> for a C2B payment.</li>
            <li><strong>Distrust the payload.</strong> Validate and cast amounts and phone numbers before you use them, and normalise numbers with <code>daraja_phone()</code>.</li>
            <li><strong>Never answer with an error to hide a bug.</strong> Answer success once the payload is stored, and fix the job.</li>
        </ul>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="stk" title="STK push result">
        <p>The result sits under <code>Body.stkCallback</code>. <code>ResultCode</code> <code>0</code> is a paid prompt; anything else is a failure with no <code>CallbackMetadata</code>.</p>
        <x-essentials::docs.code lang="json" title="The customer paid">
            {
                "Body": {
                    "stkCallback": {
                        "MerchantRequestID": "29115-34620561-1",
                        "CheckoutRequestID": "ws_CO_191220191020363925",
                        "ResultCode": 0,
                        "ResultDesc": "The service request is processed successfully.",
                        "CallbackMetadata": {
                            "Item": [
                                {"Name": "Amount", "Value": 1.00},
                                {"Name": "MpesaReceiptNumber", "Value": "NLJ7RT61SV"},
                                {"Name": "TransactionDate", "Value": 20191219102115},
                                {"Name": "PhoneNumber", "Value": 254708374149}
                            ]
                        }
                    }
                }
            }
        </x-essentials::docs.code>
        <x-essentials::docs.code lang="json" title="The customer cancelled">
            {
                "Body": {
                    "stkCallback": {
                        "MerchantRequestID": "29115-34620561-1",
                        "CheckoutRequestID": "ws_CO_191220191020363925",
                        "ResultCode": 1032,
                        "ResultDesc": "Request cancelled by user"
                    }
                }
            }
        </x-essentials::docs.code>
        <x-essentials::docs.table :columns="['ResultCode', 'Meaning']" :rows="[
            ['0', 'Paid.'],
            ['1', 'The customer\'s balance was too low.'],
            ['1032', 'The customer cancelled the prompt.'],
            ['1037', 'The prompt timed out with no answer.'],
            ['2001', 'The customer entered a wrong PIN.'],
        ]" />
        <p>Read the metadata as a name-to-value map:</p>
        <x-essentials::docs.code title="app/Jobs/SettleStkPaymentJob.php">
            use App\Enums\PaymentStatus;
            use App\Models\Payment;

            $callback = $payload->payload['Body']['stkCallback'];

            //  Find the pending payment the prompt was for
            $payment = Payment::query()->where('checkout_request_id', $callback['CheckoutRequestID'])->firstOrFail();

            //  Callbacks repeat, so settle each payment once
            if ($payment->status !== PaymentStatus::PENDING) {
                return;
            }

            //  A non-zero code is a failure with no metadata
            if ($callback['ResultCode'] !== 0) {
                $payment->update(['status' => PaymentStatus::FAILED]);

                return;
            }

            //  Turn the metadata items into a map of names to values
            $items = collect($callback['CallbackMetadata']['Item'])->pluck('Value', 'Name');

            $payment->update([
                'status' => PaymentStatus::PAID,
                'receipt_number' => $items['MpesaReceiptNumber'],
            ]);
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="c2b" title="C2B validation and confirmation">
        <p>Both callbacks carry the same fields. Validation arrives first and lets you refuse; confirmation arrives once the money has moved.</p>
        <x-essentials::docs.code lang="json" title="A C2B payload">
            {
                "TransactionType": "Pay Bill",
                "TransID": "RKTQDM7W6S",
                "TransTime": "20191122063845",
                "TransAmount": "10",
                "BusinessShortCode": "600638",
                "BillRefNumber": "invoice008",
                "InvoiceNumber": "",
                "OrgAccountBalance": "49197.00",
                "ThirdPartyTransID": "",
                "MSISDN": "254708374149",
                "FirstName": "John",
                "MiddleName": "",
                "LastName": "Doe"
            }
        </x-essentials::docs.code>
        <x-essentials::docs.table :columns="['Field', 'Use']" :rows="[
            ['TransID', 'The M-Pesa receipt. Unique, so use it to ignore repeats.'],
            ['TransAmount', 'The amount, as a string. Cast it before you compare it.'],
            ['BillRefNumber', 'The account number the customer typed. Match it to your invoice or tenant.'],
            ['BusinessShortCode', 'Which of your short codes was paid.'],
            ['MSISDN', 'The payer\'s number. Safaricom\'s newer registration may send it masked, so do not rely on it to identify the payer.'],
        ]" />
        <p>To <strong>accept</strong> a validation, answer <code>ResultCode</code> <code>0</code>. To <strong>refuse</strong> it, answer one of Safaricom's codes:</p>
        <x-essentials::docs.table :columns="['ResultCode', 'Refuses because']" :rows="[
            ['C2B00011', 'The phone number is invalid.'],
            ['C2B00012', 'The account number is invalid.'],
            ['C2B00013', 'The amount is invalid.'],
            ['C2B00014', 'The customer details are invalid.'],
            ['C2B00015', 'The short code is invalid.'],
            ['C2B00016', 'Any other reason. The guard answers this to a caller that is not Safaricom.'],
        ]" />
        <x-essentials::docs.code title="Validating an account number">
            use App\Models\Invoice;
            use Illuminate\Http\JsonResponse;
            use Illuminate\Http\Request;

            public function validation(Request $request): JsonResponse
            {
                //  Refuse only when the account reference is unknown
                $isKnown = Invoice::query()->where('number', $request->input('BillRefNumber'))->exists();

                return response()->json($isKnown
                    ? ['ResultCode' => '0', 'ResultDesc' => 'Accepted']
                    : ['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
            }
        </x-essentials::docs.code>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="received" title="See what Safaricom sends">
        <p>These docs include a receiver for the live buttons on <a href="{{ $docs->url('payments') }}#live" class="text-primary underline">Payments</a>. It sits behind the same guard as your own routes, keeps the last ten payloads, and answers <code>ResultCode</code> <code>0</code>. Press a live button with the receiver as its callback, approve the prompt on the phone, then show what arrived.</p>
        <x-essentials::docs.playground demo="received" manual="Show what Safaricom sent" title="Received from Safaricom" :fields="[]" />
        <p>The receiver addresses are <code>{{ route('daraja.docs.hook', ['hook' => 'stk']) }}</code>, and the same path ending in <code>validation</code>, <code>confirmation</code> or <code>pull</code>. They work only when <code>APP_URL</code> is reachable from the internet.</p>
    </x-essentials::docs.section>

    <x-essentials::docs.section id="local" title="Locally">
        <p>Safaricom cannot reach your machine. Expose the app through a tunnel (Herd Share, Expose or ngrok), register the tunnel's addresses in the <strong>sandbox</strong> with <code>registerC2BUrls()</code>, and set <code>APP_URL</code> to the tunnel so <code>route('hooks.daraja.stk')</code> builds a public address. The guard is skipped in <code>local</code>, so the callbacks get through.</p>
        <p>You do not need Safaricom to test your handlers. Post a payload yourself:</p>
        <x-essentials::docs.code title="tests/Feature/DarajaHookTest.php">
            use App\Jobs\SettleStkPaymentJob;
            use Illuminate\Support\Facades\Queue;

            it('stores an stk callback', function () {
                Queue::fake();

                $this->postJson(route('hooks.daraja.stk'), ['Body' => ['stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_1',
                    'ResultCode' => 0,
                ]]])->assertOk()->assertJson(['ResultCode' => 0]);

                Queue::assertPushed(SettleStkPaymentJob::class);
            });

            it('rejects a stranger', function () {
                Queue::fake();
                config(['daraja.skip_ip_check_in' => []]);

                $this->postJson(route('hooks.daraja.stk'), [])
                    ->assertJson(['ResultCode' => 'C2B00016']);

                $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])
                    ->postJson(route('hooks.daraja.stk'), [])
                    ->assertJson(['ResultCode' => 0]);
            });
        </x-essentials::docs.code>
    </x-essentials::docs.section>
@endsection
