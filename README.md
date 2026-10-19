# nyumbani/daraja

A small Laravel client for Safaricom's Daraja API: STK push, C2B URL registration and Pull transactions, plus the payload parsers, middleware, and response helpers to receive them. It requires only Laravel.

## Credentials

Build a `Credentials` object wherever your credentials live (env, config or a database row):

```php
$credentials = new Credentials(
    shortCode: '174379',
    consumerKey: '...',
    consumerSecret: '...',
    passKey: '...',
    tillNumber: null,                  // set for Buy Goods
    environment: Environment::Production,
);
```

## Calls

```php
$daraja = Daraja::for($credentials);

$daraja->stkPush('0712345678', 100, 'INV-1', 'Order payment', $callbackUrl);
$daraja->registerC2BUrls($validationUrl, $confirmationUrl);
$daraja->registerPullUrl('0712345678', $callbackUrl);
$daraja->pullTransactions();           // last 48 hours, or pass from and to
```

Each call returns Laravel's HTTP client response, so use `successful()`, `failed()` and `json()`. Daraja can answer 200 with a failure code, so also check `ResponseCode` (`0` for an STK push, `1000` for the Pull calls).

## Receiving payments

Validation, confirmation, Pull and STK push callbacks are your app's routes, so it decides which paybill a request belongs to, how to read the payload and what to do with it. The package only guards them:

```php
Route::post('hooks/{paybill}/confirm', [ConfirmationController::class, 'store'])
    ->middleware(EnsureRequestIsFromSafaricom::class);
```

`EnsureRequestIsFromSafaricom` checks `daraja.allowed_ips` (skipped in `daraja.skip_ip_check_in` environments) and answers other addresses with `C2B00016`.

`stkPush()` needs the `callbackUrl` Safaricom posts the result to. `daraja_phone()` normalises a Kenyan number to the `254XXXXXXXXX` format Daraja expects.
