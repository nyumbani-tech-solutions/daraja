<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Nyumbani\Daraja\Contracts\HandlesPayments;
use Nyumbani\Daraja\Credentials;
use Nyumbani\Daraja\Daraja;
use Nyumbani\Daraja\Environment;
use Nyumbani\Daraja\Events\PaymentReceived;
use Nyumbani\Daraja\Events\StkPushProcessed;
use Nyumbani\Daraja\Exceptions\DarajaException;
use Nyumbani\Daraja\Payment;
use Nyumbani\Daraja\StkCallback;

function credentials(array $overrides = []): Credentials
{
    return new Credentials(...array_merge([
        'shortCode' => '174379',
        'consumerKey' => 'key',
        'consumerSecret' => 'secret',
        'passKey' => 'pass',
        'environment' => Environment::Sandbox,
    ], $overrides));
}

function fakeToken(): void
{
    Http::fake(['*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => '3599'])]);
}

function c2bPayload(array $overrides = []): array
{
    return array_merge([
        'TransactionType' => 'Pay Bill',
        'TransID' => 'RKTQDM7W6S',
        'TransTime' => '20260301101530',
        'TransAmount' => '150.00',
        'BusinessShortCode' => '174379',
        'BillRefNumber' => 'INV-1',
        'OrgAccountBalance' => '1500.00',
        'MSISDN' => 'abc123hash',
        'FirstName' => 'Jane',
        'MiddleName' => '',
        'LastName' => 'Doe',
    ], $overrides);
}

beforeEach(function () {
    Cache::flush();
    Daraja::resolveMsisdnUsing(null);
});

it('caches the access token per shortcode and environment', function () {
    fakeToken();

    expect(Daraja::for(credentials())->accessToken())->toBe('token-1')
        ->and(Daraja::for(credentials())->accessToken())->toBe('token-1');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sandbox.safaricom.co.ke/oauth/v1/generate')
        && $request->hasHeader('Authorization'));
});

it('never caches a failed token request', function () {
    Http::fake(['*/oauth/v1/generate*' => Http::response(['errorMessage' => 'Bad credentials'], 400)]);

    expect(fn () => Daraja::for(credentials())->accessToken())->toThrow(DarajaException::class);
    expect(Cache::has('daraja.token.sandbox.174379'))->toBeFalse();
});

it('sends an STK push with a Nairobi timestamp, a normalised phone and a signed callback', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1']),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => 'm-1', 'CheckoutRequestID' => 'c-1', 'ResponseCode' => '0', 'CustomerMessage' => 'Success. Request accepted for processing',
        ]),
    ]);

    $response = Daraja::for(credentials())->stkPush('0712 345 678', 99.6, 'INV-1', 'Order', 'invoice-9');

    expect($response->successful)->toBeTrue()
        ->and($response->checkoutRequestId)->toBe('c-1')
        ->and($response->merchantRequestId)->toBe('m-1');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'stkpush')) {
            return false;
        }

        $data = $request->data();

        return $data['PhoneNumber'] === '254712345678'
            && $data['PartyA'] === '254712345678'
            && $data['Amount'] === 100
            && preg_match('/^\d{14}$/', $data['Timestamp']) === 1
            && $data['Password'] === base64_encode('174379pass'.$data['Timestamp'])
            && $data['TransactionType'] === 'CustomerPayBillOnline'
            && URL::hasValidSignature(Request::create($data['CallBackURL']))
            && str_contains($data['CallBackURL'], 'key=invoice-9');
    });
});

it('pays a till when the credentials have a till number', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1']),
        '*/stkpush/*' => Http::response(['ResponseCode' => '0']),
    ]);

    Daraja::for(credentials(['tillNumber' => '555000']))->stkPush('0712345678', 10, 'ref', 'desc', 'k');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'stkpush')
        && $request->data()['TransactionType'] === 'CustomerBuyGoodsOnline'
        && $request->data()['PartyB'] === '555000');
});

it('reports a refused STK push as failed', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1']),
        '*/stkpush/*' => Http::response(['errorMessage' => 'Invalid Access Token'], 400),
    ]);

    $response = Daraja::for(credentials())->stkPush('0712345678', 10, 'ref', 'desc', 'k');

    expect($response->failed())->toBeTrue()->and($response->message)->toBe('Invalid Access Token');
});

it('registers the C2B urls and pulls transactions', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1']),
        '*/c2b/v2/registerurl' => Http::response(['ResponseDescription' => 'Success']),
        '*/pulltransactions/v1/register' => Http::response(['ResponseCode' => '1000', 'ResponseMessage' => 'Registered']),
        '*/pulltransactions/v1/query' => Http::response(['ResponseCode' => '1000', 'ResponseMessage' => 'Success', 'Response' => [[
            ['transactionId' => 'T1', 'trxDate' => '2026-03-01T10:15:30+03:00', 'msisdn' => '254712345678', 'sender' => 'JANE', 'transactiontype' => 'c2b-pay-bill-debit', 'billreference' => 'INV-1', 'amount' => '150.00', 'organizationname' => 'X'],
        ]]]),
    ]);

    $daraja = Daraja::for(credentials());

    expect($daraja->registerC2BUrls()->successful)->toBeTrue()
        ->and($daraja->registerPullUrl('0712345678')->successful)->toBeTrue();

    $pull = $daraja->pullTransactions();

    expect($pull->payments)->toHaveCount(1)
        ->and($pull->payments->first()->transactionId)->toBe('T1')
        ->and($pull->payments->first()->amount)->toBe(150.0)
        ->and($pull->payments->first()->reference)->toBe('INV-1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'registerurl')
        && str_ends_with($request->data()['ConfirmationURL'], '/safaricom/c2b/confirm'));
});

it('builds a payment from a C2B payload', function () {
    $payment = Payment::fromC2B(c2bPayload());

    expect($payment->transactionId)->toBe('RKTQDM7W6S')
        ->and($payment->amount)->toBe(150.0)
        ->and($payment->payerName)->toBe('Jane Doe')
        ->and($payment->shortCode)->toBe('174379')
        ->and($payment->balance)->toBe(1500.0)
        ->and($payment->time->format('Y-m-d H:i:s'))->toBe('2026-03-01 10:15:30');
});

it('accepts or rejects a validation through the bound handler', function (bool $accepts, string $code) {
    app()->instance(HandlesPayments::class, new class($accepts) implements HandlesPayments
    {
        public function __construct(private bool $accepts) {}

        public function validate(Payment $payment): bool
        {
            return $this->accepts;
        }

        public function confirm(Payment $payment): void {}
    });

    $this->postJson('/safaricom/c2b/validate', c2bPayload())->assertJson(['ResultCode' => $code]);
})->with([[true, '0'], [false, 'C2B00012']]);

it('hands a confirmed payment to the handler, with the resolved phone, and announces it', function () {
    Event::fake([PaymentReceived::class]);
    Daraja::resolveMsisdnUsing(fn (string $reported) => $reported === 'abc123hash' ? '254712345678' : null);

    $handler = new class implements HandlesPayments
    {
        public ?Payment $confirmed = null;

        public function validate(Payment $payment): bool
        {
            return true;
        }

        public function confirm(Payment $payment): void
        {
            $this->confirmed = $payment;
        }
    };
    app()->instance(HandlesPayments::class, $handler);

    $this->postJson('/safaricom/c2b/confirm', c2bPayload())->assertJson(['ResultCode' => '0']);

    expect($handler->confirmed->phone)->toBe('254712345678');
    Event::assertDispatched(PaymentReceived::class, fn ($event) => $event->payment->transactionId === 'RKTQDM7W6S');
});

it('rejects C2B requests from other addresses outside the skipped environments', function () {
    config(['daraja.skip_ip_check_in' => []]);

    $this->postJson('/safaricom/c2b/confirm', c2bPayload())->assertJson(['ResultCode' => 'C2B00016']);

    $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200']);
    app()->instance(HandlesPayments::class, new class implements HandlesPayments
    {
        public function validate(Payment $payment): bool
        {
            return true;
        }

        public function confirm(Payment $payment): void {}
    });

    $this->postJson('/safaricom/c2b/confirm', c2bPayload())->assertJson(['ResultCode' => '0']);
});

it('broadcasts an STK result only for an untampered signed url', function () {
    Event::fake([StkPushProcessed::class]);

    $payload = ['Body' => ['stkCallback' => [
        'MerchantRequestID' => 'm-1', 'CheckoutRequestID' => 'c-1', 'ResultCode' => 0, 'ResultDesc' => 'Done',
        'CallbackMetadata' => ['Item' => [['Name' => 'Amount', 'Value' => 100], ['Name' => 'MpesaReceiptNumber', 'Value' => 'R1'], ['Name' => 'PhoneNumber', 'Value' => 254712345678]]],
    ]]];

    $url = URL::signedRoute('daraja.stk-callback', ['key' => 'invoice-9']);

    $this->postJson(str_replace('key=invoice-9', 'key=invoice-1', $url), $payload)->assertForbidden();
    Event::assertNotDispatched(StkPushProcessed::class);

    $this->postJson($url, $payload)->assertOk();
    Event::assertDispatched(StkPushProcessed::class, fn ($event) => $event->key === 'invoice-9'
        && $event->callback->successful()
        && $event->callback->receipt === 'R1'
        && $event->broadcastWith()['status'] === 'success');
});

it('treats a string result code as a failure unless it is zero', function () {
    $callback = StkCallback::fromPayload(['Body' => ['stkCallback' => ['ResultCode' => '1032', 'ResultDesc' => 'Cancelled']]]);
    $paid = StkCallback::fromPayload(['Body' => ['stkCallback' => ['ResultCode' => '0']]]);

    expect($callback->successful())->toBeFalse()->and($paid->successful())->toBeTrue();
});
