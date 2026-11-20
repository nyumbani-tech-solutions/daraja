<?php

namespace Nyumbani\Daraja;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Resolves the SHA-256 hash Safaricom sends in place of a customer's number to the number itself, through the MSISDN resolver.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class MsisdnResolver
{
    /**
     * Resolve a value, which is the number if the resolver knows the hash and the value untouched otherwise
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function resolve(string $value): string
    {
        return (new static)->handle($value);
    }

    /**
     * Resolve a value, which is the number if the resolver knows the hash and the value untouched otherwise
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handle(string $value): string
    {
        //  Only a SHA-256 hash is worth a call, so a number or anything else is returned as it came
        if (! $this->isHash($value)) {
            return $value;
        }

        //  Without a resolver to call there is nothing to ask
        if (! config('daraja.msisdn_resolver.url') || ! config('daraja.msisdn_resolver.token')) {
            return $value;
        }

        //  Ask the resolver, treating an unreachable resolver as a hash it does not know
        try {
            $response = $this->request()->post('number/resolve', ['hash' => strtolower($value)]);
        } catch (ConnectionException) {
            return $value;
        }

        //  Return the number when there is one, and the value when there is not
        return $response->successful() && is_string($phone = $response->json('phone')) && $phone !== ''
            ? $phone
            : $value;
    }

    /**
     * Determine whether a value is a SHA-256 hash, which is 64 hexadecimal characters
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function isHash(string $value): bool
    {
        return preg_match('/^[a-fA-F0-9]{64}$/', $value) === 1;
    }

    /**
     * Get a request against the resolver, authorised with its token
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('daraja.msisdn_resolver.url'), '/'))
            ->withToken(config('daraja.msisdn_resolver.token'))
            ->acceptJson()
            ->asJson()
            ->timeout(config('daraja.msisdn_resolver.timeout', 5));
    }
}
