# nyumbani/daraja

A small Laravel client for Safaricom's Daraja API: STK push, C2B URL registration and Pull transactions, plus the payload parsers, middleware, and response helpers to receive them. It requires only Laravel.

## Requirements

- PHP 8.3 or newer and Laravel 12 or 13
- A Safaricom Daraja app with a short code, consumer key and secret, and a pass key for STK push

## Install

1. Require it:

```
composer require nyumbani/daraja
```

2. Set the credentials in `.env`. Credentials normally live wherever your app keeps them (see Short code below); these keys feed the local docs only:

```
DARAJA_SHORT_CODE=
DARAJA_CONSUMER_KEY=
DARAJA_CONSUMER_SECRET=
DARAJA_PASS_KEY=
DARAJA_TILL_NUMBER=
DARAJA_MSISDN_RESOLVER_URL=https://msisdn.nyumbani.ke/api/v1
DARAJA_MSISDN_RESOLVER_TOKEN=
DARAJA_MSISDN_RESOLVER_TIMEOUT=5
DARAJA_STK_TTL=60
DARAJA_STK_TIMEOUT=120
DARAJA_STK_CHECK_THROTTLE=10
DARAJA_STK_MODE=
DARAJA_STK_POLL_INTERVAL=3
DARAJA_STK_POLL_DURATION=300
DARAJA_STK_SIMULATE=false
```

The `DARAJA_MSISDN_RESOLVER_*` keys are read by the package itself (see Masked numbers below); the token is a Sanctum bearer token issued to your app.

3. Optionally publish the configuration (the Safaricom IP allow-list and the base url):

```
php artisan vendor:publish --tag=daraja-config
```

4. Call it, and guard the routes Safaricom posts to (see below). In an app with `nyumbani/essentials`, `php artisan nyumbani:install --with=daraja` does steps 1 to 3 for you.

## Short code

Every call goes to Safaricom's production API (`daraja.base_url`). There is no sandbox, so develop with `DARAJA_STK_SIMULATE=true` (see below) and fake the HTTP client in tests.

Build a `ShortCode` wherever your credentials live (env, config or a database row):

```php
use Nyumbani\Daraja\ShortCode;

$shortCode = new ShortCode(
    value: '174379',
    consumerKey: '...',
    consumerSecret: '...',
    passKey: '...',
    tillNumber: null,                  // set for Buy Goods
);
```

## Calls

```php
use Nyumbani\Daraja\Daraja;

$daraja = Daraja::for($shortCode);

$daraja->stkPush('0712345678', 100, 'INV-1', 'Order payment', $callbackUrl);
$daraja->registerC2BUrls($validationUrl, $confirmationUrl);
$daraja->registerPullUrl('0712345678', $callbackUrl);
$daraja->pullTransactions();           // last 48 hours, or pass from and to
```

Each call returns Laravel's HTTP client response, so use `successful()`, `failed()` and `json()`. Daraja can answer 200 with a failure code, so also check `ResponseCode` (`0` for an STK push, `1000` for the Pull calls).

## The standard STK push flow

For a payer waiting on a screen, `StkPushManager` starts the push, takes the STK callback and the C2B confirmation, and broadcasts `StkPushStatusChanged` on the public channel `stk.{id}`. It supports a paybill only, keeps attempts in the cache, and needs a queue worker for the broadcast.

Inject `StkPushManager` where you need it. There is no facade.

```php
use Nyumbani\Daraja\StkPushManager;

//  public function store(StkPushManager $stkPush)
$attempt = $stkPush->initiate(
    shortCode: $shortCode,
    phone: $phone,
    amount: $amount,
    description: 'Invoice payment',
    callbackUrl: route('hooks.daraja.stk'),
    reference: $invoice->number,
);

return view('invoices.paying', ['attempt' => $stkPush->contract($attempt)]);

$stkPush->handleCallback($request->all());   // in the STK route
$stkPush->confirmPayment($request->all());   // in the C2B confirmation route, after recording the payment
```

Register the "I've paid" resolver once in a service provider's `boot()`, with the static `StkPushManager::resolvePaymentUsing(fn (array $attempt) => ...)`. The package fires no events but the `StkPushStatusChanged` broadcast the screen follows: fulfil orders in your C2B confirmation route, from the payment you record there, never from the STK result. The `DARAJA_STK_TTL`, `DARAJA_STK_TIMEOUT` (how long the screen waits for the payer) and `DARAJA_STK_CHECK_THROTTLE` keys tune the timings. Set `DARAJA_STK_SIMULATE=true` to skip Safaricom: `Daraja::stkPush()` then makes up Daraja's answer (checkout and merchant request ids) and nothing is sent, so `initiate()` works as usual. The webhook simulation needs no flag: the Daraja docs (local only) have a panel that lists the payments your app started lately, fake or real, and posts Safaricom-shaped payloads to *your* hooks, named by `daraja.stk.hooks` (default `hooks.daraja.stk` and `hooks.daraja.confirmation`; the app declares them, the package does not). Inject `StkPushSimulator` in tests. `Daraja::stkPush()` throws in production when the flag is on. A payment confirms one attempt only, the contract carries the waiting time (`DARAJA_STK_TIMEOUT`) to the payment prompt of `nyumbani/ui`, and a lost STK callback is not chased: the screen times out and the payer can press "I have paid, check". See the STK push page of the docs.

## Receiving payments

Validation, confirmation, Pull and STK push callbacks are your app's routes, so it decides which paybill a request belongs to, how to read the payload and what to do with it. The package only guards them:

```php
use App\Http\Controllers\ConfirmationController;
use Illuminate\Support\Facades\Route;
use Nyumbani\Daraja\Http\Middleware\EnsureRequestIsFromSafaricom;

Route::post('hooks/{paybill}/confirm', [ConfirmationController::class, 'store'])
    ->middleware(EnsureRequestIsFromSafaricom::class);
```

`EnsureRequestIsFromSafaricom` checks `daraja.allowed_ips` (skipped in `daraja.skip_ip_check_in` environments) and answers other addresses with `C2B00016`.

`stkPush()` needs the `callbackUrl` Safaricom posts the result to. `daraja_phone()` normalises a Kenyan number to the `254XXXXXXXXX` format Daraja expects.

## Masked numbers

Safaricom's newer C2B registration sends `MSISDN` as a SHA-256 hash instead of the number. `MsisdnResolver` turns it back into the number through the Nyumbani MSISDN resolver:

```php
use Nyumbani\Daraja\MsisdnResolver;

$phone = MsisdnResolver::resolve($request->input('MSISDN'));
```

It asks the resolver only for a 64-character hexadecimal value, and returns the value untouched when it is not a hash, when `DARAJA_MSISDN_RESOLVER_URL` or `DARAJA_MSISDN_RESOLVER_TOKEN` is empty, when the resolver cannot be reached or times out, and when the resolver does not know the hash. It therefore never throws, so test the result with `daraja_phone()` or `(new MsisdnResolver)->isHash()` before trusting it as a number.

## Docs

The package suggests `nyumbani/docs` (`composer require --dev nyumbani/docs`), which then serves the docs locally at `/nyumbani-daraja/docs`, in any Laravel app. The package works without it.
