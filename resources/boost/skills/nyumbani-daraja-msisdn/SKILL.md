---
name: nyumbani-daraja-msisdn
description: "Invoke when a Nyumbani app reads the payer's number from a Safaricom callback and it is a 64-character hash instead of 2547..., or when using MsisdnResolver, the DARAJA_MSISDN_RESOLVER_URL, DARAJA_MSISDN_RESOLVER_TOKEN or DARAJA_MSISDN_RESOLVER_TIMEOUT settings, or debugging a hash that is not resolved. Skip for the callbacks themselves (see nyumbani-daraja-webhooks)."
metadata:
  author: nyumbani
---

# Nyumbani Daraja MSISDN resolver

Safaricom's newer C2B registration sends `MSISDN` as a SHA-256 hash, not the number. `Nyumbani\Daraja\MsisdnResolver` asks the Nyumbani MSISDN resolver for the number behind it. Never hash-match or store a lookup table in the app, and never write another client for the resolver.

## Setup
- `DARAJA_MSISDN_RESOLVER_URL` defaults to `https://msisdn.nyumbani.ke/api/v1`.
- `DARAJA_MSISDN_RESOLVER_TOKEN` is a Sanctum bearer token issued to the app. It has no default and belongs only in the gitignored `.env`; `.env.example` carries the key empty.
- `DARAJA_MSISDN_RESOLVER_TIMEOUT` is in seconds and defaults to `5`.
- Config keys live under `daraja.msisdn_resolver.{url,token,timeout}`. An app that published `config/daraja.php` before this existed must republish it (`php artisan vendor:publish --tag=daraja-config --force`) or add the block.

## Use
```php
use Nyumbani\Daraja\MsisdnResolver;

$phone = MsisdnResolver::resolve($request->input('MSISDN'));

if ((new MsisdnResolver)->isHash($phone)) {
    //  Still a hash: the resolver is off, down or does not know it
}
```
`resolve()` is static; `handle()` and `isHash()` are instance methods.

## Behaviour to rely on
- Only a 64-character hexadecimal value is sent to the resolver, lower-cased. Numbers and anything else return untouched with no HTTP call.
- With no url or token configured it returns the value untouched, so it is safe in local and testing.
- A connection failure or timeout, a non-2xx answer, or an answer without a non-empty string `phone` all return the value untouched. It never throws.
- The request is `POST {url}/number/resolve` with `{"hash": "..."}`, a bearer token and JSON headers.

## Handling
- Always check the result: an unresolved hash is returned as a "number". Use `isHash()` before saving it, and `daraja_phone()` to normalise a real one.
- Resolve once, in the confirmation handler, off the payload, and key idempotency on `TransID`, not on the phone.
- The resolver call is synchronous inside a Safaricom callback. Keep the timeout low, or dispatch a job when the payer is not needed to answer `ResultCode 0`.
- Do not log the token or the hash lookups' full responses.

## Testing
Fake the resolver with `Http::fake(['*/number/resolve' => Http::response(['phone' => '254712345678'])])` and set `config(['daraja.msisdn_resolver.url' => 'https://resolver.test/api/v1', 'daraja.msisdn_resolver.token' => 'token'])`. Cover a known hash, an unknown hash (`404`), a number (`Http::assertNothingSent()`), and `Http::failWith`/a `ConnectionException` for an unreachable resolver.
