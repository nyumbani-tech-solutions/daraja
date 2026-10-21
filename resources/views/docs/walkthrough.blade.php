@extends('docs::layout')

@section('page', 'Walkthrough')
@section('lead', 'Take a first payment end to end: prompt the customer, receive the result and mark an invoice paid.')

@section('content')
    <x-docs::section id="plan" title="What we will build">
        <p>A customer opens an invoice and presses <strong>Pay with M-Pesa</strong>. We ask Safaricom to prompt their phone, remember that a payment is pending, and when Safaricom reports the result we mark the payment paid or failed. Four pieces of your app are involved.</p>
        <x-docs::code lang="text" title="The pieces">
            PaymentController@store     asks Safaricom, saves a pending Payment
            DarajaHookController@stk    receives the result, stores it, queues a job
            SettleStkPaymentJob         finds the Payment by CheckoutRequestID and settles it
            payments table              checkout_request_id, status, receipt_number
        </x-docs::code>
        <p>Do <a href="{{ $docs->url('installation') }}" class="text-primary underline">Installation</a> first, so <code>DARAJA_ENVIRONMENT=sandbox</code> is set and your credentials work.</p>
    </x-docs::section>

    <x-docs::section id="table" title="1. A table for payments">
        <x-docs::code title="database/migrations/create_payments_table.php">
            use App\Models\Invoice;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->ulid()->unique();
                $table->foreignIdFor(Invoice::class)->constrained();
                $table->string('checkout_request_id')->unique();
                $table->string('status');
                $table->string('receipt_number')->nullable();
                $table->timestamps();
            });
        </x-docs::code>
        <p><code>checkout_request_id</code> is unique because it is the key that ties Safaricom's callback to your row, and it makes a repeated callback harmless.</p>
    </x-docs::section>

    <x-docs::section id="hooks" title="2. Declare the callback routes">
        <p>Copy the route group from <a href="{{ $docs->url('webhooks') }}#routes" class="text-primary underline">Webhooks</a>. The one that matters here is <code>hooks.daraja.stk</code>.</p>
    </x-docs::section>

    <x-docs::section id="push" title="3. Ask the customer to pay">
        <x-docs::code title="app/Http/Controllers/PaymentController.php">
            use App\Enums\PaymentStatus;
            use App\Http\Requests\StorePaymentRequest;
            use App\Models\Invoice;
            use Illuminate\Http\RedirectResponse;
            use Illuminate\Support\Facades\Gate;
            use Nyumbani\Daraja\Daraja;
            use Nyumbani\Daraja\Exceptions\DarajaException;
            use Nyumbani\Daraja\ShortCode;

            class PaymentController
            {
                public function store(StorePaymentRequest $request, Invoice $invoice): RedirectResponse
                {
                    Gate::authorize('pay', $invoice);

                    //  Build the short code from the stored credentials
                    $shortCode = new ShortCode(
                        value: config('services.mpesa.short_code'),
                        consumerKey: config('services.mpesa.key'),
                        consumerSecret: config('services.mpesa.secret'),
                        passKey: config('services.mpesa.pass_key'),
                    );

                    //  Prompt the customer
                    try {
                        $response = Daraja::for($shortCode)->stkPush(
                            phone: $request->safe()->phone_number,
                            amount: $invoice->balance,
                            accountReference: $invoice->number,
                            description: 'Invoice payment',
                            callbackUrl: route('hooks.daraja.stk'),
                        );
                    } catch (DarajaException) {
                        return back()->alert('error', trans('alerts.payments.unreachable'));
                    }

                    //  Daraja can answer 200 with a refusal
                    if ($response->failed() || $response->json('ResponseCode') !== '0') {
                        return back()->alert('error', trans('alerts.payments.refused'));
                    }

                    //  Remember the payment, keyed by the id the callback will carry
                    $invoice->payments()->create([
                        'checkout_request_id' => $response->json('CheckoutRequestID'),
                        'status' => PaymentStatus::PENDING,
                    ]);

                    return back()->alert('success', trans('alerts.payments.prompted'));
                }
            }
        </x-docs::code>
        <p>The success toast says the <em>prompt was sent</em>, not that the invoice is paid. The customer may still cancel.</p>
    </x-docs::section>

    <x-docs::section id="receive" title="4. Receive the result">
        <p>Store the raw payload, queue the job and answer at once. The full controller and job are on the <a href="{{ $docs->url('webhooks') }}#answer" class="text-primary underline">Webhooks</a> page, along with the shape of the payload and what each <code>ResultCode</code> means.</p>
    </x-docs::section>

    <x-docs::section id="try" title="5. Try it in the sandbox">
        <ol>
            <li>Expose the app through a tunnel and set <code>APP_URL</code> to its address, so the callback address is public.</li>
            <li>Press the button. The sandbox does not message a real phone; use Safaricom's simulator, or post a sample payload at <code>hooks/daraja/stk</code> yourself.</li>
            <li>Watch the <code>payments</code> row move from <code>pending</code> to <code>paid</code> and the receipt number appear.</li>
        </ol>
    </x-docs::section>

    <x-docs::section id="test" title="6. Test it">
        <p>Fake the HTTP client to test step 3 and post a payload to test step 4. Neither touches Safaricom. Examples are on <a href="{{ $docs->url('payments') }}#testing" class="text-primary underline">Payments</a> and <a href="{{ $docs->url('webhooks') }}#local" class="text-primary underline">Webhooks</a>.</p>
    </x-docs::section>

    <x-docs::section id="live" title="7. Go live">
        <ul>
            <li>Set <code>DARAJA_ENVIRONMENT=production</code> and use the production short code's key, secret and pass key. Sandbox credentials do not work in production.</li>
            <li>Register your production callback addresses with Safaricom.</li>
            <li>Leave <code>daraja.skip_ip_check_in</code> alone: it only skips the guard in <code>local</code> and <code>testing</code>.</li>
        </ul>
    </x-docs::section>
@endsection
