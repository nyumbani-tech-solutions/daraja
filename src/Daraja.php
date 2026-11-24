<?php

namespace Nyumbani\Daraja;

use Carbon\CarbonInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
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
    public function __construct(protected ShortCode $shortCode) {}

    /**
     * Get a client for the short code
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function for(ShortCode $shortCode): static
    {
        return new static($shortCode);
    }

    /**
     * Get the OAuth access token, which is cached until shortly before it expires
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function accessToken(): string
    {
        //  Build the cache key
        $key = "daraja.token.{$this->shortCode->value}";

        //  Return the cached token
        if ($token = Cache::get($key)) {
            return $token;
        }

        //  Request a new token
        $response = $this->request()
            ->withBasicAuth($this->shortCode->consumerKey, $this->shortCode->consumerSecret)
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
        return $this->authorised()->post('mpesa/c2b/v2/registerurl', [
            'ShortCode' => $this->shortCode->value,
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
            'ShortCode' => $this->shortCode->value,
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
        //  Sign the request
        ['Password' => $password, 'Timestamp' => $timestamp] = $this->stkSignature();

        //  Answer as Daraja would, without calling it, when simulating
        if ($this->isSimulatingStkPush()) {
            return $this->fakeStkPush();
        }

        //  Send the request
        return $this->authorised()->post('mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $this->shortCode->value,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $this->shortCode->transactionType(),
            'Amount' => (int) round($amount),
            'PartyA' => $phone = daraja_phone($phone),
            'PartyB' => $this->shortCode->receiver(),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description,
        ]);
    }

    /**
     * Ask Daraja what became of an STK push, for when its callback never arrived
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function stkQuery(string $checkoutRequestId): Response
    {
        //  Sign the request
        ['Password' => $password, 'Timestamp' => $timestamp] = $this->stkSignature();

        //  Send the request
        return $this->authorised()->post('mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $this->shortCode->value,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
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
            'ShortCode' => $this->shortCode->value,
            'StartDate' => $from->format('Y-m-d H:i:s'),
            'EndDate' => $to->format('Y-m-d H:i:s'),
            'OffSetValue' => (string) $offset,
        ]);
    }

    /**
     * Check whether STK pushes are simulated, which is refused in production since no payer would be prompted
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function isSimulatingStkPush(): bool
    {
        //  Simulating is off unless asked for
        if (! config('daraja.stk.simulate')) {
            return false;
        }

        //  A production app that skipped its pushes would never prompt a payer
        if (app()->isProduction()) {
            throw new LogicException('DARAJA_STK_SIMULATE must not be on in production.');
        }

        return true;
    }

    /**
     * Make the response Daraja gives to an accepted STK push, which never reaches Safaricom
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function fakeStkPush(): Response
    {
        return new Response(new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode([
            'MerchantRequestID' => str()->random(4).'-'.random_int(1000000, 9999999).'-1',
            'CheckoutRequestID' => 'ws_CO_'.now('Africa/Nairobi')->format('dmYHis').random_int(100000000, 999999999),
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ])));
    }

    /**
     * Get the password and timestamp that sign an STK push or an STK query
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function stkSignature(): array
    {
        //  The pass key is what signs the request
        if (! $this->shortCode->passKey) {
            throw new LogicException('An STK push needs the pass key of the shortcode.');
        }

        //  Daraja checks the password against the timestamp, which is in East Africa Time
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        //  Return the signature
        return [
            'Password' => base64_encode($this->shortCode->value.$this->shortCode->passKey.$timestamp),
            'Timestamp' => $timestamp,
        ];
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
     * Get a request against the base url of Daraja
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl(config('daraja.base_url'))
            ->acceptJson()
            ->timeout(30);
    }
}
