<?php

namespace Nyumbani\Daraja;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use LogicException;
use Nyumbani\Daraja\Exceptions\DarajaException;
use Nyumbani\Daraja\Responses\ApiResponse;
use Nyumbani\Daraja\Responses\PullResponse;
use Nyumbani\Daraja\Responses\StkPushResponse;

/**
 * A client for one paybill or till on Safaricom's Daraja API.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class Daraja
{
    /**
     * Resolves the phone number Safaricom reports for a payment, which can be a hash
     */
    protected static ?Closure $msisdnResolver = null;

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
     * Set how a phone number Safaricom reports is turned into the real one, receiving the reported value
     * and returning the number or null when it cannot be resolved
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function resolveMsisdnUsing(?Closure $resolver): void
    {
        static::$msisdnResolver = $resolver;
    }

    /**
     * Resolve the phone number of a payment, keeping the reported one when it cannot be resolved
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function resolvePhone(Payment $payment): Payment
    {
        //  Skip payments without a phone number or when no resolver is set
        if (! static::$msisdnResolver || ! $payment->phone) {
            return $payment;
        }

        return $payment->withPhone((static::$msisdnResolver)($payment->phone) ?? $payment->phone);
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

        return $token;
    }

    /**
     * Prompt a customer to pay, with the result posted to a signed callback that broadcasts on the channel key
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function stkPush(
        string $phone,
        float|int $amount,
        string $accountReference,
        string $description,
        string $channelKey,
        ?string $callbackUrl = null,
    ): StkPushResponse {
        //  The pass key is what signs the request
        if (! $this->credentials->passKey) {
            throw new LogicException('An STK push needs the pass key of the shortcode.');
        }

        //  Daraja checks the password against the timestamp, which is in East Africa Time
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        //  Normalise the phone number
        $phone = static::normalisePhone($phone);

        //  Send the request
        $response = $this->authorised()->post('mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $this->credentials->shortCode,
            'Password' => base64_encode($this->credentials->shortCode.$this->credentials->passKey.$timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => $this->credentials->transactionType(),
            'Amount' => (int) round($amount),
            'PartyA' => $phone,
            'PartyB' => $this->credentials->receiver(),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl ?? $this->callbackUrl($channelKey),
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description,
        ]);

        return new StkPushResponse(
            successful: $response->successful() && (string) $response->json('ResponseCode') === '0',
            message: (string) ($response->json('CustomerMessage') ?? $response->json('errorMessage') ?? $response->json('ResponseDescription') ?? 'The request failed'),
            merchantRequestId: $response->json('MerchantRequestID'),
            checkoutRequestId: $response->json('CheckoutRequestID'),
            data: $response->json() ?? [],
        );
    }

    /**
     * Register the urls Safaricom calls to validate and confirm payments, defaulting to the package's routes
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function registerC2BUrls(?string $validationUrl = null, ?string $confirmationUrl = null): ApiResponse
    {
        //  Send the request
        $response = $this->authorised()->post('mpesa/c2b/v2/registerurl', [
            'ShortCode' => $this->credentials->shortCode,
            'ResponseType' => 'Completed',
            'ValidationURL' => $validationUrl ?? static::route('daraja.c2b.validate'),
            'ConfirmationURL' => $confirmationUrl ?? static::route('daraja.c2b.confirm'),
        ]);

        return new ApiResponse(
            successful: $response->successful(),
            message: (string) ($response->json('ResponseDescription') ?? $response->json('errorMessage') ?? 'The request failed'),
            data: $response->json() ?? [],
        );
    }

    /**
     * Register the url that Pull transactions are announced on, along with the number notified about them
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function registerPullUrl(string $nominatedNumber, ?string $callbackUrl = null): ApiResponse
    {
        //  Send the request
        $response = $this->authorised()->post('pulltransactions/v1/register', [
            'ShortCode' => $this->credentials->shortCode,
            'RequestType' => 'Pull',
            'NominatedNumber' => static::normalisePhone($nominatedNumber),
            'CallBackURL' => $callbackUrl ?? static::route('daraja.pull'),
        ]);

        return new ApiResponse(
            successful: $response->successful() && (string) $response->json('ResponseCode') === '1000',
            message: (string) ($response->json('ResponseMessage') ?? $response->json('errorMessage') ?? 'The request failed'),
            data: $response->json() ?? [],
        );
    }

    /**
     * Pull the transactions made in a period, which is the last 48 hours unless given
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function pullTransactions(?CarbonInterface $from = null, ?CarbonInterface $to = null, int $offset = 0): PullResponse
    {
        //  Default to the last 48 hours, in East Africa Time
        $to = ($to ?? now())->copy()->timezone('Africa/Nairobi');
        $from = ($from ?? $to->copy()->subHours(48))->copy()->timezone('Africa/Nairobi');

        //  Send the request
        $response = $this->authorised()->post('pulltransactions/v1/query', [
            'ShortCode' => $this->credentials->shortCode,
            'StartDate' => $from->format('Y-m-d H:i:s'),
            'EndDate' => $to->format('Y-m-d H:i:s'),
            'OffSetValue' => (string) $offset,
        ]);

        //  Get the successful state
        $successful = $response->successful() && (string) $response->json('ResponseCode') === '1000';

        return new PullResponse(
            successful: $successful,
            message: (string) ($response->json('ResponseMessage') ?? $response->json('errorMessage') ?? 'The request failed'),
            payments: collect($successful ? ($response->json('Response.0') ?? []) : [])
                ->map(fn (array $entry) => Payment::fromPull($entry, $this->credentials->shortCode))
                ->values(),
        );
    }

    /**
     * Normalise a Kenyan phone number to the 254XXXXXXXXX format Daraja expects
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function normalisePhone(string $phone): string
    {
        return '254'.substr(preg_replace('/\D/', '', $phone), -9);
    }

    /**
     * Build the signed url Safaricom posts an STK push result to
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function callbackUrl(string $channelKey): string
    {
        //  The route only exists when the package's routes are enabled
        if (! Route::has('daraja.stk-callback')) {
            throw new LogicException('Enable the Daraja routes or pass a callback url to the STK push.');
        }

        return URL::signedRoute('daraja.stk-callback', ['key' => $channelKey]);
    }

    /**
     * Get the url of one of the package's routes
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected static function route(string $name): string
    {
        //  The route only exists when the package's routes are enabled
        if (! Route::has($name)) {
            throw new LogicException('Enable the Daraja routes or pass the url to register.');
        }

        return route($name);
    }

    /**
     * Get a request authorised with the access token
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function authorised(): PendingRequest
    {
        return $this->request()->withToken($this->accessToken())->asJson();
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
