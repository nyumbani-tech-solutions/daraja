# nyumbani/daraja

A small Laravel client for Safaricom's Daraja API: STK push, C2B URL registration and Pull transactions, plus the payloads, events, middleware and optional routes to receive them. It requires only Laravel.

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

$daraja->stkPush('0712345678', 100, 'INV-1', 'Order payment', channelKey: 'invoice-9');
$daraja->registerC2BUrls();
$daraja->registerPullUrl('0712345678');
$daraja->pullTransactions();           // last 48 hours, or pass from and to
```

Each call returns a readonly response (`successful`, `message`, `failed()`); `pullTransactions()` also carries a collection of `Payment`s.

## Receiving payments

Enable the routes in the published config (`php artisan vendor:publish --tag=daraja-config`), then bind your handler:

```php
$this->app->bind(HandlesPayments::class, InvoicePaymentHandler::class);
```

- `validate(Payment): bool` accepts or rejects a payment before the customer is charged.
- `confirm(Payment): void` books it. Safaricom can send a confirmation more than once, so make it idempotent on `transactionId`.

The C2B routes sit behind `EnsureRequestIsFromSafaricom`, which checks `daraja.allowed_ips` (skipped in `daraja.skip_ip_check_in` environments). A `PaymentReceived` event is dispatched after each confirmation.

## STK push results

`stkPush()` sends Safaricom a signed callback url carrying your `channelKey`. When the customer pays or cancels, `StkPushProcessed` is broadcast on `daraja.{channelKey}` (prefix in config) with `status`, `merchantRequestId`, `checkoutRequestId` and `description`. Pass your own `callbackUrl` instead if you do not enable the routes.

Safaricom reports hashed phone numbers on C2B payments. Resolve them with `Daraja::resolveMsisdnUsing(fn (string $reported) => ...)`.

## Testing

`vendor/bin/pest`
