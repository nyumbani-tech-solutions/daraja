@extends('docs::layout')

@section('page', 'STK push')
@section('lead', 'Everything about the standard STK push: starting it, following it live, confirming it, and what to do when it goes wrong.')

@section('content')
    <x-docs::section id="overview" title="The flow">
        <p>An STK push is a prompt on the payer's phone. Safaricom answers it twice, and neither answer alone proves the money arrived, so the standard flow follows both. <code>StkPushManager</code> runs it on the server and <code>&lt;x-nyumbani::payment-prompt&gt;</code> shows it, over a websocket or by polling. It supports a <strong>paybill only</strong>.</p>
        <x-docs::steps :steps="[
            ['title' => 'Your app starts the push', 'actor' => '$stkPush->initiate()', 'detail' => 'The payer submits your form, however you built it: a plain request, Livewire or ajax. initiate() keeps an attempt in the cache, makes its id, sends the push and answers with the contract: the channel, the status and how to pay by hand.'],
            ['title' => 'You show the payment prompt', 'actor' => 'The payment prompt', 'detail' => 'Once the push is sent, you put the prompt on the page with the contract. It subscribes to the public channel stk.{id} and asks your check route where the attempt stands, in case a status changed before the subscription held.'],
            ['title' => 'The payer answers on their phone', 'actor' => 'Safaricom', 'detail' => 'They enter the PIN, cancel, lack funds, or ignore the prompt.'],
            ['title' => 'Safaricom posts the STK result', 'actor' => 'Your STK route, $stkPush->handleCallback()', 'detail' => 'A paid push becomes paid and the screen says Payment received, confirming. Anything else becomes failed with a reason and the screen shows how to pay by hand.'],
            ['title' => 'Safaricom posts the C2B confirmation', 'actor' => 'Your C2B route, $stkPush->confirmPayment()', 'detail' => 'This is the real proof of payment. You record the payment first. Then the call matches the attempt by receipt or reference, marks it confirmed and tells the screen.'],
            ['title' => 'The screen shows success', 'actor' => 'The payment prompt', 'detail' => 'The browser hears the confirmed status and shows the success message, then moves on.'],
        ]" />
        <x-docs::callout type="warning" title="Fulfil from the C2B payment only">
            The STK result only says the payer completed the prompt. Mark an order paid from the C2B payment you record in your confirmation route, which is the proof the money arrived. The package fires no event for it: <code>confirmPayment()</code> only updates the attempt and the screen.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="needs" title="What it needs">
        <x-docs::table :columns="['Piece', 'Why']" :rows="[
            ['A paybill ShortCode with a pass key', 'The push is signed with it. A short code with a till number is refused, because the manual instructions only work for a paybill.'],
            ['Reverb and a queue worker, or polling', 'The status is broadcast on a queued event. Without Reverb the prompt polls your check route instead (mode="poll"), which needs neither. See the Payment prompt page of the UI docs.'],
            ['A public https address for the callbacks', 'Safaricom cannot reach a local machine, so use a tunnel locally, or simulate the pushes (see Develop without Safaricom).'],
            ['A cache', 'Attempts live in your default cache store for daraja.stk.ttl minutes. They hold no credentials.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="start" title="Start a push">
        <p>Your app starts the push, in whatever way suits the page: a form post, a Livewire action or an ajax call. Validate the request, decide the amount, and hand the work to <code>$stkPush->initiate()</code>. When it answers, put <code>&lt;x-nyumbani::payment-prompt :attempt="$contract" /&gt;</code> on the page, as <a href="#screen" class="text-primary underline">The screen</a> shows. The payer's browser never sends the amount.</p>
        <x-docs::code title="routes/web.php, the controller">
            use App\Models\Invoice;
            use Illuminate\Contracts\View\View;
            use Illuminate\Http\Request;
            use Nyumbani\Daraja\StkPushManager;

            public function __invoke(Request $request, Invoice $invoice, StkPushManager $stkPush): View
            {
                //  Validate the payer's number
                $validated = $request->validate([
                    'phone' => ['required', 'string'],
                ]);

                //  Start the push
                $attempt = $stkPush->initiate(
                    shortCode: $invoice->shortCode(),
                    phone: $validated['phone'],
                    amount: $invoice->balance,
                    description: 'Invoice payment',
                    callbackUrl: route('hooks.daraja.stk'),
                    reference: $invoice->number,
                );

                //  Show the page with the prompt, which follows the attempt
                return view('invoices.paying', ['attempt' => $stkPush->contract($attempt)]);
            }
        </x-docs::code>
        <x-docs::table :columns="['Argument', 'Use']" :rows="[
            ['shortCode', 'A paybill ShortCode with a pass key. A till is refused with a LogicException.'],
            ['phone', 'The payer\'s number in any common Kenyan format.'],
            ['amount', 'Whole shillings, decided by your route.'],
            ['description', 'A short note about the payment.'],
            ['callbackUrl', 'The public address of your STK route.'],
            ['reference', 'Optional. What the payer types as the account on a manual payment, such as an invoice number. Without it a 12 character token is made.'],
        ]" />
        <p>The attempt gets a version 4 UUID of its own. It names the public channel, so it is the only secret. The answer carries it in the <strong>contract</strong>, which the payment prompt reads:</p>
        <x-docs::code lang="json" title="$stkPush->contract()">
            {
                "id": "6f1f2b4e-3c0a-4b8e-9d5a-1f6a7c8d9e01",
                "channel": "stk.6f1f2b4e-3c0a-4b8e-9d5a-1f6a7c8d9e01",
                "status": "pending",
                "reason": null,
                "phone": "254712345678",
                "timeout": 120,
                "manual": { "paybill": "174379", "account": "INV-1", "amount": 150 }
            }
        </x-docs::code>
        <p>When Daraja refuses the push or cannot be reached, <code>initiate()</code> does not throw. The attempt comes back <code>failed</code> with the reason <code>rejected</code>, so the screen goes straight to the manual instructions.</p>
    </x-docs::section>

    <x-docs::section id="attempt" title="The attempt">
        <p>An attempt is what the package remembers about one push, in your cache for <code>daraja.stk.ttl</code> minutes. It holds no credentials. The contract is the part the screen may see.</p>
        <x-docs::table :columns="['Field', 'What it holds']" :rows="[
            ['id', 'A version 4 UUID the package makes. It names the public channel stk.{id}, so it is the only secret.'],
            ['shortCode, reference, amount, phone', 'What the push was for. The phone is in the 254 format, and the reference is the one you gave or the 12 character token made for it.'],
            ['status, reason', 'Where the attempt stands (pending, paid, failed or confirmed) and why it failed.'],
            ['checkoutRequestId, merchantRequestId', 'The two ids Daraja answers the push with. The callback is matched on the first. The second is kept so you can quote it to Safaricom, and is never sent to the browser.'],
            ['receipt, transactionId, via', 'The M-Pesa receipt of the STK payment, the transaction id of the C2B payment that confirmed it, and whether that was the push (stk) or a payment made by hand (manual).'],
        ]" />
        <x-docs::callout type="note" title="One payment confirms one attempt">
            Once a payment confirms an attempt, it can confirm no other. A reused invoice number therefore cannot be confirmed by the payment of an earlier attempt, whether it arrives on the C2B route or from your I have paid resolver. The refusal is logged as a warning.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="reference" title="The account reference">
        <p>The reference ties a payment back to the attempt, including a payment the payer makes by hand. Give your own, such as an invoice number, when the payer already knows it. Without one, a short token is made.</p>
        <x-docs::playground demo="stk-reference" title="See the references" :fields="[
            ['name' => 'reference', 'label' => 'Your reference (optional)', 'value' => 'INV-2026-000123', 'placeholder' => 'e.g. INV-2026-000123, or leave empty'],
        ]" />
        <x-docs::callout type="tip" title="Twelve characters">
            Daraja accepts at most twelve characters as an STK account reference, so a longer one is cut when the push is sent. The attempt answers to both forms, so the STK payment and a manual payment with the full reference both match.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="callbacks" title="The two callbacks">
        <p>You keep both routes, as <a href="{{ $docs->url('webhooks') }}" class="text-primary underline">Webhooks</a> describes. Each makes one call to <code>StkPushManager</code>, which you inject. There is no facade.</p>
        <x-docs::code title="The STK route and the C2B confirmation route">
            use App\Models\Payment;
            use Illuminate\Http\JsonResponse;
            use Illuminate\Http\Request;
            use Nyumbani\Daraja\StkPushManager;

            public function stk(Request $request, StkPushManager $stkPush): JsonResponse
            {
                //  Tell the attempt what Safaricom said
                $stkPush->handleCallback($request->all());

                return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
            }

            public function confirmation(Request $request, StkPushManager $stkPush): JsonResponse
            {
                //  Record every payment yourself, whether or not a payer is waiting
                Payment::record($request->all());

                //  Then tell a waiting payer, which does nothing when nobody waits
                $stkPush->confirmPayment($request->all());

                return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
            }
        </x-docs::code>
        <x-docs::table :columns="['Call', 'Finds the attempt by', 'Does nothing when']" :rows="[
            ['handleCallback()', 'The CheckoutRequestID.', 'The id is unknown, or the attempt was settled already.'],
            ['confirmPayment()', 'The receipt (TransID), or else the reference and short code (BillRefNumber).', 'No attempt matches, the amount is lower than the attempt asked for, the attempt was confirmed already, or the payment confirmed another attempt.'],
        ]" />
        <p>Both are safe to repeat, since Safaricom retries. A payment below the amount is not confirmed. Your own controller still records it.</p>
        <x-docs::callout type="note" title="confirmPayment() is not every payment">
            <code>confirmPayment()</code> confirms only a payment that matches an attempt. A payment with a reference you never issued returns nothing, which is why your controller records every C2B payment itself.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="states" title="Statuses and reasons">
        <x-docs::table :columns="['Status', 'Meaning', 'The payer sees']" :rows="[
            ['pending', 'The push was sent and nothing has come back.', 'Watch out for an MPESA PIN prompt on your phone: the number prompted, the amount that will be deducted, and a countdown.'],
            ['paid', 'The payer completed the prompt. The C2B has not arrived yet.', 'Payment received, confirming. The manual instructions are never shown.'],
            ['confirmed', 'The C2B payment arrived.', 'The success message.'],
            ['failed', 'The push failed, or Daraja refused it.', 'The reason and how to pay by hand.'],
            ['timed-out', 'Only the screen sets this: no result arrived within its timeout. The server still holds the attempt as pending.', 'The reason and how to pay by hand, with a warning to check your M-Pesa messages first.'],
        ]" />
        <x-docs::table :columns="['Reason', 'Result code', 'Meaning']" :rows="[
            ['cancelled', '1032', 'The payer cancelled the prompt.'],
            ['insufficient-funds', '1', 'The balance is too low.'],
            ['wrong-pin', '2001', 'The PIN was wrong.'],
            ['timeout', '1037', 'The prompt expired on the phone.'],
            ['rejected', 'none', 'Daraja refused the push, or could not be reached.'],
            ['no-response', 'none', 'Set by the screen when no result arrived in time.'],
            ['other', 'any other', 'Any other result code.'],
        ]" />
        <x-docs::playground demo="stk-result" title="What a result code does" :fields="[
            ['name' => 'code', 'label' => 'ResultCode of the callback', 'value' => '1032', 'placeholder' => 'e.g. 0, 1032, 1, 2001, 1037'],
        ]" />
        <p>A failed or timed-out attempt can still become <code>confirmed</code>. The payer may pay by hand against the reference, or the callback may simply have been lost, and a late payment must not be stranded.</p>
    </x-docs::section>

    <x-docs::section id="events" title="The broadcast">
        <p>The package fires one event, <code>StkPushStatusChanged</code>, and it is not for your listeners: it is broadcast so the payment prompt can move on. It fires when an attempt becomes paid, failed or confirmed. There are no other events, so do your own work (fulfil the order, send the receipt) in your C2B confirmation route, where you record the payment.</p>
        <x-docs::code lang="json" title="What the browser receives on stk.{id}, as status.changed">
            { "status": "failed", "reason": "cancelled", "manual": true }
        </x-docs::code>
        <p>The broadcast carries no phone, no receipt and no name. Its channel is public, so keep it that way.</p>
    </x-docs::section>

    <x-docs::section id="lost" title="When the callback never arrives">
        <p>Safaricom sometimes never posts the STK result. The package does not chase it: the attempt stays <code>pending</code>, the screen times out on its own and shows how to pay by hand, and the payer can press <strong>I have paid, check</strong>. A C2B payment still confirms the attempt whenever it arrives. To ask Daraja yourself, call <code>stkQuery()</code>:</p>
        <x-docs::code>
            use Nyumbani\Daraja\Daraja;

            $response = Daraja::for($shortCode)->stkQuery($checkoutRequestId);

            $response->json('ResultCode');   // 0 when it was paid
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="check" title="The I have paid button">
        <p>A payer who paid by hand, or against an invoice with no push at all, can press <strong>I have paid, check</strong>. The browser posts the attempt id to your check route, which calls <code>$stkPush->check()</code>.</p>
        <x-docs::code title="Registered once, in the boot method of a service provider">
            use App\Models\Payment;
            use Nyumbani\Daraja\StkPushManager;

            StkPushManager::resolvePaymentUsing(fn (array $attempt) => Payment::query()
                ->where('reference', $attempt['reference'])
                ->where('amount', '>=', $attempt['amount'])
                ->value('transaction_id'));
        </x-docs::code>
        <x-docs::table :columns="['What check() does', 'When']" :rows="[
            ['Returns the attempt as it is', 'It is unknown or already confirmed, you registered nothing, or the button was pressed again within daraja.stk.check_throttle seconds.'],
            ['Asks your resolver', 'Otherwise. Return the transaction id, true, or nothing.'],
            ['Confirms the attempt', 'The resolver returned something. The attempt is confirmed (via manual) and the screen updates, in every tab.'],
        ]" />
        <p>For a page with no push, such as an invoice the payer will pay from their phone, record the attempt without prompting anyone:</p>
        <x-docs::code>
            use App\Models\Invoice;
            use Illuminate\Contracts\View\View;
            use Nyumbani\Daraja\StkPushManager;

            public function show(Invoice $invoice, StkPushManager $stkPush): View
            {
                $attempt = $stkPush->track($invoice->shortCode()->value, $invoice->balance, $invoice->number);

                return view('invoices.pay', ['attempt' => $stkPush->contract($attempt)]);
            }
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="screen" title="The screen">
        <p>The payment prompt lives in <code>nyumbani/ui</code>, which shares nothing with this package but the contract above. It never starts a push: your app does, and puts the prompt on the page with the contract that <code>$stkPush->contract()</code> returned. The prompt subscribes to the broadcast (or, in poll mode, asks your check route every few seconds), asks your check route where the attempt stands, times out on its own if the websocket is silent, and shows the manual instructions after every failure. See the <a href="{{ route('docs.page', ['package' => 'nyumbani-ui', 'page' => 'payment-prompt']) }}" class="text-primary underline">Payment prompt</a> page.</p>
        <x-docs::code lang="blade" title="resources/views/invoices/paying.blade.php">
            @verbatim
            <x-nyumbani::payment-prompt
                :attempt="$attempt"
                :check-url="route('invoices.pay.check', ['invoice' => $invoice])"
                :redirect="route('invoices.show', ['invoice' => $invoice])"
            />
            @endverbatim
        </x-docs::code>
    </x-docs::section>

    <x-docs::section id="config" title="Settings">
        <x-docs::table :columns="['Key', 'Default', 'What it does']" :rows="[
            ['stk.ttl', '60 minutes', 'How long an attempt is remembered. Set with DARAJA_STK_TTL.'],
            ['stk.timeout', '120 seconds', 'How long the payment prompt waits for the payer to answer before it shows how to pay by hand. It travels to the screen in the contract. Set with DARAJA_STK_TIMEOUT.'],
            ['stk.check_throttle', '10 seconds', 'The least time between two I have paid checks of one attempt. Set with DARAJA_STK_CHECK_THROTTLE.'],
            ['stk.mode', 'unset', 'How the payment prompt follows the payment: websocket (Reverb) or poll (asks the check route). Unset picks the websocket when Reverb is set up, else polling. Set with DARAJA_STK_MODE.'],
            ['stk.poll_interval', '3 seconds', 'The seconds between two polls. Set with DARAJA_STK_POLL_INTERVAL.'],
            ['stk.poll_duration', '300 seconds', 'How long the prompt keeps polling before it stops. Set with DARAJA_STK_POLL_DURATION.'],
            ['stk.hooks', 'hooks.daraja.stk, hooks.daraja.confirmation', 'The route names of your own hooks, which the simulator posts to.'],
            ['stk.simulate', 'off', 'When on, Daraja::stkPush() (which initiate() calls) sends nothing to Safaricom and makes up the answer. See Develop without Safaricom. It throws in production. Set with DARAJA_STK_SIMULATE.'],
        ]" />
    </x-docs::section>

    <x-docs::section id="simulate" title="Develop without Safaricom">
        <p>Safaricom cannot reach a local machine, and a real push rings a real phone. Set <code>DARAJA_STK_SIMULATE=true</code> and <code>Daraja::stkPush()</code> sends nothing: it makes up the answer of an accepted push, with its own <code>CheckoutRequestID</code> and <code>MerchantRequestID</code>, and writes a line to the log. <code>initiate()</code> then works as usual, so the attempt is <code>pending</code>, the prompt follows it, and the only thing missing is Safaricom. You play Safaricom by posting its two payloads to your own routes.</p>
        <p>The package ships the buttons that answer for Safaricom, so you never write them. They post to <strong>your</strong> hooks, which is the point: the real route, middleware and handler run, only Safaricom is missing.</p>

        <x-docs::steps :steps="[
            ['title' => 'Declare your hooks', 'actor' => 'Your app', 'detail' => 'The STK and the C2B confirmation routes are yours, as the Webhooks page shows. The simulator never registers them: it only needs their names.'],
            ['title' => 'Turn simulation on', 'actor' => 'Your .env', 'detail' => 'Set DARAJA_STK_SIMULATE=true. The simulator routes answer 404 otherwise, and are not registered in production.'],
            ['title' => 'Start a push', 'actor' => 'Your app', 'detail' => 'Call initiate() as usual. No phone rings and the attempt is pending, with a made-up CheckoutRequestID.'],
            ['title' => 'Put the panel on the page', 'actor' => 'Your view', 'detail' => 'Add the stk-simulator component beside the payment prompt, with the same contract.'],
            ['title' => 'Press a button', 'actor' => 'The package', 'detail' => 'The server posts the payload to your hook. The prompt moves on through the same broadcast a real payment would send.'],
        ]" />

        <x-docs::code title="The panel, beside the prompt">
            @verbatim
            <x-nyumbani::payment-prompt :$attempt :check-url="route('payments.check')" />

            {{--  Renders nothing unless DARAJA_STK_SIMULATE is on, so it can stay in the view  --}}
            <x-nyumbani::stk-simulator :$attempt />
            @endverbatim
        </x-docs::code>

        <x-docs::table :columns="['Button', 'What is posted to your hook', 'You should see']" :rows="[
            ['Paid', 'The STK hook: ResultCode 0 with a new receipt.', 'The prompt shows payment received, then confirmed once the C2B arrives.'],
            ['Cancelled, No funds, Wrong PIN, Timeout', 'The STK hook: ResultCode 1032, 1, 2001 or 1037.', 'The failure screen with the pay-by-hand card.'],
            ['For the STK payment', 'The C2B hook: TransID equal to the receipt of the paid push. Press Paid first.', 'The attempt confirmed, via stk.'],
            ['Paid by hand', 'The C2B hook: a new TransID and the reference as BillRefNumber.', 'The attempt confirmed, via manual.'],
            ['Paid by hand, KES 1 short', 'The C2B hook with an amount below the attempt.', 'Nothing confirmed.'],
        ]" />
        <p>Under the buttons the panel prints the url it posted to, the status your hook answered, the payload it sent, and the attempt as it stands now. If the status is not 200, the fault is in your hook, not in the package.</p>

        <h3>If your hooks are named differently</h3>
        <p>The simulator finds your hooks by route name, from <code>daraja.stk.hooks</code>. The defaults are <code>hooks.daraja.stk</code> and <code>hooks.daraja.confirmation</code>, the names the Webhooks page uses. If you named yours otherwise, publish the config and change them; a name that is not registered fails with Route not defined.</p>
        <x-docs::code title="config/daraja.php">
            'stk' => [
                'simulate' => env('DARAJA_STK_SIMULATE', false),
                'hooks' => ['stk' => 'payments.stk', 'confirmation' => 'payments.c2b'],
            ],
        </x-docs::code>

        <h3>Without nyumbani/ui, or from your own code</h3>
        <p>The buttons post <code>{ id, outcome }</code> as JSON to <code>daraja.simulate.stk</code> (outcomes <code>paid</code>, <code>cancelled</code>, <code>insufficient-funds</code>, <code>wrong-pin</code>, <code>timeout</code>) or <code>daraja.simulate.confirmation</code> (<code>stk</code>, <code>manual</code>, <code>short</code>), with the session and CSRF token. Both answer 422 for an attempt nobody knows. The class behind them is injectable.</p>
        <x-docs::code title="Answer as Safaricom from code">
            use Nyumbani\Daraja\Enums\ConfirmationKind;
            use Nyumbani\Daraja\Enums\StkPushOutcome;
            use Nyumbani\Daraja\StkPushSimulator;

            public function simulate(StkPushSimulator $simulator, array $attempt): array
            {
                //  Answer the push as paid, which posts to your STK hook
                $simulator->stk($attempt, StkPushOutcome::PAID, route('hooks.daraja.stk'));

                //  Then confirm the payment, which posts to your C2B hook and returns what it answered
                return $simulator->confirmation($attempt, ConfirmationKind::STK, route('hooks.daraja.confirmation'));
            }
        </x-docs::code>

        <x-docs::callout type="warning" title="Never on in production">
            In production, a push that simulates would never prompt a payer, so <code>Daraja::stkPush()</code> throws a <code>LogicException</code> when the flag is on, the simulator routes are not registered, they answer 404 if reached, <code>StkPushSimulator</code> throws, and the simulator panel renders nothing. Pin it to <code>false</code> in <code>phpunit.xml</code> so a developer's <code>.env</code> never changes a test.
        </x-docs::callout>
    </x-docs::section>

    <x-docs::section id="testing" title="Test it without Safaricom">
        <p>Fake the token, push and query endpoints, call the routes you wrote, and post the payloads Safaricom would.</p>
        <x-docs::code title="tests/Feature/PayInvoiceTest.php">
            use Illuminate\Support\Facades\Http;
            use Nyumbani\Daraja\StkPushManager;

            it('confirms the attempt on the C2B payment', function () {
                Http::fake([
                    '*/oauth/v1/generate*' => Http::response(['access_token' => 'token']),
                    '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_CO_1']),
                ]);

                $this->post(route('invoices.pay', ['invoice' => $invoice]), ['phone' => '0712345678'])->assertOk();

                $attempt = app(StkPushManager::class)->confirmPayment(['TransID' => 'RKT1', 'TransAmount' => '150', 'BusinessShortCode' => '174379', 'BillRefNumber' => $invoice->number]);

                expect($attempt)->status->toBe('confirmed');
            });
        </x-docs::code>
    </x-docs::section>
@endsection
