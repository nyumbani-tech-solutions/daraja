---
name: nyumbani-daraja-payments
description: "Invoke when a Nyumbani app needs M-Pesa through Safaricom Daraja: STK push, C2B URL registration, Pull transaction registration or queries, building a ShortCode, switching sandbox and production, or handling a failed Daraja response. Skip for receiving callbacks (see nyumbani-daraja-webhooks)."
metadata:
  author: nyumbani
---

# Nyumbani Daraja Payments

Use `nyumbani/daraja` for every M-Pesa call. Never write another Daraja HTTP client, and never copy a gateway class from another app.

## Setup
- `DARAJA_ENVIRONMENT=sandbox|production` in `.env`. It defaults to `production`, so set sandbox explicitly while developing.
- For one client only, call `->sandbox()` or `->live()` on it.
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
- Pull queries default to the last 48 hours.

## Reading responses
Each call returns Laravel's HTTP `Response`. Daraja can answer HTTP 200 with a failure, so check both `successful()` and the code in the body: `ResponseCode` is `0` for an STK push and `1000` for the Pull calls.
```php
if ($response->failed() || ! in_array($response->json('ResponseCode'), ['0', '1000'], true)) { ... }
```

## Testing
Use `Http::fake()` for `sandbox.safaricom.co.ke` and `api.safaricom.co.ke`. Never hit Daraja from the test suite.

## Rules
- Business logic (which order a payment belongs to) lives in the app's own service or action, not in the controller that calls Daraja.
- Validate input with a Form Request, and flash results as toasts.
