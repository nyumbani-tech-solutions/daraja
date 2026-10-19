---
name: nyumbani-daraja-webhooks
description: "Invoke when a Nyumbani app receives Safaricom callbacks: C2B validation and confirmation, STK push results, Pull notifications, protecting those routes, the Safaricom IP allow-list, CSRF exemption, or what to answer Safaricom. Skip for making outgoing Daraja calls."
metadata:
  author: nyumbani
---

# Nyumbani Daraja Webhooks

Safaricom posts to the app's routes, so the app owns the routes, the parsing and the business action. The package only guards them.

## Route
Put the hooks outside any auth group, behind `EnsureRequestIsFromSafaricom`, and exempt from CSRF:
```php
use App\Http\Controllers\DarajaHookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Nyumbani\Daraja\Http\Middleware\EnsureRequestIsFromSafaricom;

Route::prefix('hooks/daraja')
    ->name('hooks.daraja.')
    ->middleware(EnsureRequestIsFromSafaricom::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->group(function () {
        Route::post('validation', [DarajaHookController::class, 'validation']);
        Route::post('confirmation', [DarajaHookController::class, 'confirmation']);
        Route::post('stk', [DarajaHookController::class, 'stk']);
        Route::post('pull', [DarajaHookController::class, 'pull']);
    });
```
With several paybills, include the paybill in the URL, for example `hooks/{paybill}/confirm`, so the controller knows which short code a request belongs to.

## Guard
`EnsureRequestIsFromSafaricom` allows the addresses in `daraja.allowed_ips`, skips the check in `daraja.skip_ip_check_in` (`local`, `testing`), and answers any other address with `ResultCode` `C2B00016` and a warning log. Publish the config only to change those lists; never remove the middleware to get a callback through.

## Answering Safaricom
Respond quickly with HTTP 200 JSON:
```php
return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
```
Keep the raw payload first (type, payload, IP), then do the slow work in a queued job. Do not make Safaricom wait on business logic.

## Handling
- Callbacks can arrive more than once. Make the handler idempotent by keying on the transaction id (`TransID`, or the STK `CheckoutRequestID`).
- Treat the payload as untrusted input: validate and cast amounts and phone numbers before using them, and normalise numbers with `daraja_phone()`.
- Validation hooks decide whether a payment is accepted. Reject with a non-zero `ResultCode` only when the account reference is unknown.

## Local development
Safaricom cannot reach a local machine. Expose the app through a tunnel, register the tunnel URLs with `registerC2BUrls()` or `registerPullUrl()` in the sandbox, and test handlers with `$this->postJson('/hooks/daraja/confirmation', $payload)`, which passes the guard in `testing`.

## Testing
Test the guard in a non-skipped environment by setting `daraja.skip_ip_check_in` to `[]`: an unknown IP gets `C2B00016`, an allowed IP reaches the controller.
