---
name: nyumbani-daraja-payments
description: "Invoke when a Nyumbani app needs M-Pesa through Safaricom Daraja: STK push, C2B URL registration, Pull transaction registration or queries, building a ShortCode, or handling a failed Daraja response. Skip for receiving callbacks (see nyumbani-daraja-webhooks)."
metadata:
  author: nyumbani
---

# Nyumbani Daraja Payments

Use `nyumbani/daraja` for every M-Pesa call. Never write another Daraja HTTP client, and never copy a gateway class from another app.

## Setup
- Every call goes to production (`daraja.base_url`). There is no sandbox and no `DARAJA_ENVIRONMENT`: develop with `DARAJA_STK_SIMULATE=true` and fake the HTTP client in tests.
- Credentials never go in code, config defaults or `.env.example`. Keep them in the gitignored `.env`, or in a database row for apps that serve several paybills, and never log them.

## Build a ShortCode
```php
use Nyumbani\Daraja\ShortCode;

$shortCode = new ShortCode(
    value: '174379',
    consumerKey: $key,
    consumerSecret: $secret,
    passKey: $passKey,
    tillNumber: null, // set for Buy Goods
);
```
Build it wherever the credentials live. The package does not read them itself.

## Calls
```php
use Nyumbani\Daraja\Daraja;

$daraja = Daraja::for($shortCode);

$daraja->stkPush($phone, $amount, 'INV-1', 'Order payment', $callbackUrl);
$daraja->registerC2BUrls($validationUrl, $confirmationUrl);
$daraja->registerPullUrl($nominatedNumber, $callbackUrl);
$daraja->pullTransactions($from, $to, $offset);
```
- `stkPush` needs the short code's pass key (otherwise a `LogicException`) and a public `$callbackUrl`. Phones are normalised with `daraja_phone()`; use it yourself for any stored number.
- `accessToken()` throws `DarajaException` when Daraja cannot be reached or refuses the credentials. Catch it where the user triggers the call and flash an error toast with `back()->alert('error', ...)`.
- Pull queries default to the last 48 hours; the times are converted to East Africa Time. `amount` is rounded to whole shillings.
- The access token is cached for 55 minutes per short code in the default cache store, and a failed token request is never cached.
- A `ShortCode` with a `tillNumber` makes an STK push a Buy Goods payment to the till; without one it is a PayBill payment.

## The standard STK push flow
For a payer waiting on a screen, inject `StkPushManager` (there is no facade) instead of calling `stkPush()` yourself. Paybill only: a `ShortCode` with a till number is refused.
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
```
- The attempt gets a version 4 UUID of its own, which names the public channel `stk.{id}`. `reference` is optional (without it a 12 character token is made); Daraja takes 12 characters, so a longer one is cut when sent and the attempt answers to both forms.
- Start the push however the page needs (request, Livewire or ajax), validate the number, decide the amount on the server, then render `<x-nyumbani::payment-prompt :attempt="$attempt" />` with the contract (see `nyumbani-payment-prompt`).
- The contract holds `id`, `channel`, `status`, `reason`, `phone`, `timeout` (`DARAJA_STK_TIMEOUT`, 120) and `manual` (`paybill`, `account`, `amount`). The attempt also keeps `checkoutRequestId` and `merchantRequestId` (for Safaricom support), `receipt`, `transactionId` and `via` (`stk` or `manual`), none of which reach the browser.
- A refused or unreachable push does not throw: the attempt comes back `failed` with the reason `rejected`.
- Register the "I've paid" resolver once in a provider's `boot()` with the static `StkPushManager::resolvePaymentUsing(fn (array $attempt) => ...)`, like a macro (it returns the transaction id, `true` or nothing). It must check the amount and that the payment is newer than the attempt.
- One payment confirms one attempt: a payment that already confirmed another attempt is refused, so a reused invoice number is never confirmed by an old payment.
- Fulfil from the C2B payment you record in your confirmation route only. The STK result is not proof of payment, the package fires no events except the `StkPushStatusChanged` broadcast, and there is no partial payment handling.
- For a page with no push, `$stkPush->track()` records the reference (render the prompt with `:pushed="false"`) and `$stkPush->check($id)` backs the check button.
- A lost STK callback is not chased by a job: the screen times out by itself and the payer can press "I have paid, check". `Daraja::stkQuery($checkoutRequestId)` stays available as a plain call.

## Develop without Safaricom
`DARAJA_STK_SIMULATE=true` makes `Daraja::stkPush()` send nothing and answer with an accepted push (its own `CheckoutRequestID` and `MerchantRequestID`), so `initiate()` works as usual and no phone rings. It throws in production; pin it to `false` in `phpunit.xml`. The webhook simulation is separate and needs no flag: the Daraja docs (local only, 404 elsewhere) have a panel, `<x-daraja::stk-simulator />`, that lists the payments your app started lately (fake or real) and posts Safaricom-shaped STK results and C2B confirmations to *your* hooks, named by `daraja.stk.hooks` (default `hooks.daraja.stk` and `hooks.daraja.confirmation`; the app declares them, the package does not). Its routes (`daraja.simulate.*`) exist only in the docs. Use `StkPushSimulator` in tests.

## Settings
`daraja.stk.ttl` (`DARAJA_STK_TTL`, 60 minutes an attempt is remembered), `timeout` (`DARAJA_STK_TIMEOUT`, 120 seconds the screen waits), `check_throttle` (`DARAJA_STK_CHECK_THROTTLE`, 10 seconds between checks), `mode` (`DARAJA_STK_MODE`: `websocket` or `poll`, unset picks the websocket when Reverb is set up), `poll_interval` (`DARAJA_STK_POLL_INTERVAL`, 3 seconds between polls) and `poll_duration` (`DARAJA_STK_POLL_DURATION`, 300 seconds it keeps polling; all three travel to the prompt in the contract) and `simulate` (`DARAJA_STK_SIMULATE`, off).

## Reading responses
Each call returns Laravel's HTTP `Response`. Daraja can answer HTTP 200 with a failure, so check both `successful()` and the code in the body: `ResponseCode` is `0` for an STK push and `1000` for the Pull calls.
```php
if ($response->failed() || ! in_array($response->json('ResponseCode'), ['0', '1000'], true)) { ... }
```

## Testing
Use `Http::fake()` for `api.safaricom.co.ke`, faking the token request (`*/oauth/v1/generate*`) as well as the call. Never hit Daraja from the test suite.
```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/oauth/v1/generate*' => Http::response(['access_token' => 'token']),
    '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_CO_1']),
]);
```
Save the `CheckoutRequestID` of an accepted push with a pending payment: the STK callback carries the same id.

## Rules
- Business logic (which order a payment belongs to) lives in the app's own service or action, not in the controller that calls Daraja.
- Validate input with a Form Request, and flash results as toasts.
