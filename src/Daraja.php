<?php

namespace Nyumbani\Daraja;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;
use Nyumbani\Daraja\Exceptions\DarajaException;

/**
 * A client for one paybill or till on Safaricom's Daraja API.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class Daraja
{
    /**
     * Create the client
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(protected Credentials $credentials) {}

    /**
     * Get a client for the credentials
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function for(Credentials $credentials): static
    {
        return new static($credentials);
    }

    /**
     * Get the OAuth access token, which is cached until shortly before it expires
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function accessToken(): string
    {
        //  Build the cache key
        $key = "daraja.token.{$this->credentials->environment->value}.{$this->credentials->shortCode}";

        //  Return the cached token
        if ($token = Cache::get($key)) {
            return $token;
        }

        //  Request a new token
        $response = $this->request()
            ->withBasicAuth($this->credentials->consumerKey, $this->credentials->consumerSecret)
            ->get('oauth/v1/generate', ['grant_type' => 'client_credentials']);

        //  Never cache a failure
        if ($response->failed() || ! $response->json('access_token')) {
            throw new DarajaException('Could not get a Daraja access token: '.$response->body());
        }

        //  Cache the token for a little less than the hour it lives
        Cache::put($key, $token = $response->json('access_token'), 3300);

        //  Return the token
        return $token;
    }

    /**
     * Register the urls Safaricom calls to validate and confirm payments
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function registerC2BUrls(string $validationUrl, string $confirmationUrl): Response
    {
        //  Send the request
        return $this->authorised()->post('mpesa/c2b/v2/registerurl', [
            'ShortCode' => $this->credentials->shortCode,
            'ResponseType' => 'Completed',
            'ValidationURL' => $validationUrl,
            'ConfirmationURL' => $confirmationUrl,
        ]);
    }

    /**
     * Register the url that Pull transactions are announced on, along with the number notified about them
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function registerPullUrl(string $nominatedNumber, string $callbackUrl): Response
    {
        return $this->authorised()->post('pulltransactions/v1/register', [
            'ShortCode' => $this->credentials->shortCode,
            'RequestType' => 'Pull',
            'NominatedNumber' => daraja_phone($nominatedNumber),
            'CallBackURL' => $callbackUrl,
        ]);
    }

    /**
     * Prompt a customer to pay, with the result posted to the callback url
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function stkPush(
        string $phone,
        float|int $amount,
        string $accountReference,
        string $description,
        string $callbackUrl,
    ): Response {
        //  The pass key is what signs the request
        if (! $this->credentials->passKey) {
            throw new LogicException('An STK push needs the pass key of the shortcode.');
        }

        //  Daraja checks the password against the timestamp, which is in East Africa Time
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        //  Send the request
        return $this->authorised()->post('mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $this->credentials->shortCode,
            'Password' => base64_encode($this->credentials->shortCode.$this->credentials->passKey.$timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => $this->credentials->transactionType(),
            'Amount' => (int) round($amount),
            'PartyA' => $phone = daraja_phone($phone),
            'PartyB' => $this->credentials->receiver(),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description,
        ]);
    }

    /**
     * Pull the transactions made in a period, which is the last 48 hours unless given
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function pullTransactions(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        int $offset = 0
    ): Response {
        //  Get the start time
        $to = ($to ?? now())->copy()->timezone('Africa/Nairobi');

        //  Get the end time
        $from = ($from ?? $to->copy()->subHours(48))->copy()->timezone('Africa/Nairobi');

        //  Send the request
        return $this->authorised()->post('pulltransactions/v1/query', [
            'ShortCode' => $this->credentials->shortCode,
            'StartDate' => $from->format('Y-m-d H:i:s'),
            'EndDate' => $to->format('Y-m-d H:i:s'),
            'OffSetValue' => (string) $offset,
        ]);
    }

    /**
     * Get a request authorised with the access token
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function authorised(): PendingRequest
    {
        return $this
            ->request()
            ->withToken($this->accessToken())->asJson();
    }

    /**
     * Get a request against the base url of the environment
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->credentials->environment->baseUrl())
            ->acceptJson()
            ->timeout(30);
    }
}
