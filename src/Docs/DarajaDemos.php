<?php

namespace Nyumbani\Daraja\Docs;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nyumbani\Daraja\Daraja;
use Nyumbani\Daraja\Http\Middleware\EnsureRequestIsFromSafaricom;
use Nyumbani\Daraja\ShortCode;
use Throwable;

/**
 * The interactive demos of the Daraja docs, each running the real code on what a reader typed and never calling Safaricom
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class DarajaDemos
{
    /**
     * The cache key the docs receiver keeps what Safaricom posted under
     */
    public const RECEIVED_KEY = 'daraja.docs.received';

    /**
     * Normalise a phone number the way daraja_phone() does
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function phone(array $input): array
    {
        return [$this->row('daraja_phone()', daraja_phone($input['phone'] ?? ''))];
    }

    /**
     * Show where an STK push is paid for a short code and an optional till
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function shortCode(array $input): array
    {
        //  Build the short code the reader described
        $shortCode = new ShortCode(
            value: $input['value'] ?? '',
            consumerKey: 'key',
            consumerSecret: 'secret',
            tillNumber: filled($input['till'] ?? null) ? $input['till'] : null,
        );

        return [
            $this->row('Transaction type', $shortCode->transactionType()),
            $this->row('BusinessShortCode (who pushes)', $shortCode->value),
            $this->row('PartyB (who is paid)', $shortCode->receiver()),
        ];
    }

    /**
     * Build the request an STK push sends, with the HTTP client faked so Safaricom is never called
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function stkPush(array $input): array
    {
        return $this->capture('processrequest', fn (Daraja $daraja) => $daraja->stkPush(
            $input['phone'] ?? '',
            (float) ($input['amount'] ?? 0),
            $input['reference'] ?? '',
            'Demo payment',
            'https://app.test/hooks/daraja/stk',
        ), filled($input['till'] ?? null) ? $input['till'] : null);
    }

    /**
     * Build the request that registers the C2B validation and confirmation addresses
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function registerC2b(array $input): array
    {
        return $this->capture('registerurl', fn (Daraja $daraja) => $daraja->registerC2BUrls($input['validation'] ?? '', $input['confirmation'] ?? ''));
    }

    /**
     * Build the request that registers the Pull notification address
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function registerPull(array $input): array
    {
        return $this->capture('pulltransactions/v1/register', fn (Daraja $daraja) => $daraja->registerPullUrl($input['number'] ?? '', $input['callback'] ?? ''));
    }

    /**
     * Build the request that queries the transactions of a period, which is the last 48 hours when none is typed
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function pull(array $input): array
    {
        return $this->capture('pulltransactions/v1/query', fn (Daraja $daraja) => $daraja->pullTransactions(
            filled($input['from'] ?? null) ? Carbon::parse($input['from']) : null,
            filled($input['to'] ?? null) ? Carbon::parse($input['to']) : null,
            (int) ($input['offset'] ?? 0),
        ));
    }

    /**
     * Run a call against a faked HTTP client and return the request it sent as rows
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function capture(string $endpoint, callable $call, ?string $till = null): array
    {
        //  Keep the demo token out of the real cache
        $cache = config('cache.default');
        config(['cache.default' => 'array']);

        try {
            //  Answer the token and every other request with made-up replies
            Http::fake([
                '*/oauth/v1/generate*' => Http::response(['access_token' => 'demo-token']),
                '*' => Http::response(['ResponseCode' => '0']),
            ]);

            //  Run the real call for a demo short code
            $call(Daraja::for(new ShortCode('600000', 'key', 'secret', 'demo-pass-key', $till))->sandbox());

            //  Read the request that was sent
            $sent = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->first(fn ($request) => str_contains($request->url(), $endpoint));
        } finally {
            config(['cache.default' => $cache]);
        }

        //  Show where it went and what it carried
        return [
            $this->row('POST', $sent->url()),
            ...collect($sent->data())->map(fn ($value, $key) => $this->row($key, $value))->values()->all(),
        ];
    }

    /**
     * Ask Safaricom for an access token with the credentials of the docs
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function liveToken(array $input): array
    {
        return $this->live($input, fn () => $this->describe(
            Http::baseUrl(config('daraja.base_urls.production'))
                ->acceptJson()
                ->withBasicAuth(config('daraja.docs.consumer_key'), config('daraja.docs.consumer_secret'))
                ->get('oauth/v1/generate', ['grant_type' => 'client_credentials'])
        ));
    }

    /**
     * Prompt a phone to pay, for real
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function liveStkPush(array $input): array
    {
        return $this->live($input, fn (Daraja $daraja) => $this->describe($daraja->stkPush(
            $input['phone'] ?? '',
            (float) ($input['amount'] ?? 0),
            $input['reference'] ?? '',
            'Docs payment',
            $input['callback'] ?? '',
        )));
    }

    /**
     * Register the C2B validation and confirmation addresses, for real
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function liveRegisterC2b(array $input): array
    {
        return $this->live($input, fn (Daraja $daraja) => $this->describe($daraja->registerC2BUrls($input['validation'] ?? '', $input['confirmation'] ?? '')));
    }

    /**
     * Register the Pull notification address, for real
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function liveRegisterPull(array $input): array
    {
        return $this->live($input, fn (Daraja $daraja) => $this->describe($daraja->registerPullUrl($input['number'] ?? '', $input['callback'] ?? '')));
    }

    /**
     * Query the transactions of a period, for real
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function livePull(array $input): array
    {
        return $this->live($input, fn (Daraja $daraja) => $this->describe($daraja->pullTransactions(
            filled($input['from'] ?? null) ? Carbon::parse($input['from']) : null,
            filled($input['to'] ?? null) ? Carbon::parse($input['to']) : null,
            (int) ($input['offset'] ?? 0),
        )));
    }

    /**
     * List what Safaricom posted to the docs receiver, newest first
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function received(array $input): array
    {
        //  Read what the receiver kept
        $received = Cache::get(self::RECEIVED_KEY, []);

        if ($received === []) {
            return [$this->row('Received', 'Nothing yet. Make a live call whose callback address is the docs receiver, from a public address.')];
        }

        return collect($received)->map(fn (array $item) => $this->row(
            "{$item['hook']} at {$item['at']} from {$item['ip']}",
            json_encode($item['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ))->all();
    }

    /**
     * Make a live call against Safaricom with the docs credentials
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function live(array $input, callable $call): array
    {
        //  Read the credentials of the docs
        $config = config('daraja.docs');

        if (blank($config['short_code'] ?? null) || blank($config['consumer_key'] ?? null) || blank($config['consumer_secret'] ?? null)) {
            return [$this->row('Not configured', 'Set DARAJA_SHORT_CODE, DARAJA_CONSUMER_KEY, DARAJA_CONSUMER_SECRET (and DARAJA_PASS_KEY for an STK push) in .env', false)];
        }

        $daraja = Daraja::for(new ShortCode(
            value: $config['short_code'],
            consumerKey: $config['consumer_key'],
            consumerSecret: $config['consumer_secret'],
            passKey: $config['pass_key'] ?? null,
            tillNumber: filled($config['till_number'] ?? null) ? $config['till_number'] : null,
        ))->live();

        //  Call Safaricom and show what it answered
        try {
            $answer = $call($daraja);
        } catch (Throwable $exception) {
            return [$this->row('Failed', $exception->getMessage(), false)];
        }

        return [
            $this->row('Environment', 'live (production)'),
            ...collect($answer)->map(fn ($value, $label) => $this->row($label, $value, $label === 'HTTP status' ? str_starts_with((string) $value, '2') : null))->values()->all(),
        ];
    }

    /**
     * Describe a Daraja response as rows: the status and the body
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function describe(Response $response): array
    {
        return [
            'HTTP status' => $response->status(),
            'Response' => json_encode($response->json() ?? $response->body(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Run a callback request through the Safaricom guard
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function guard(array $input): array
    {
        //  Pretend to be the chosen environment and keep the rejection out of the log
        $environment = app()->environment();
        $channel = config('logging.default');
        app()->detectEnvironment(fn () => $input['environment'] ?? 'production');
        config(['logging.default' => 'null']);

        try {
            //  Send a request from the typed address
            $request = Request::create('/hooks/daraja/stk', 'POST', server: ['REMOTE_ADDR' => $input['ip'] ?? '']);
            $response = (new EnsureRequestIsFromSafaricom)->handle($request, fn () => response()->json(['reached' => true]));
        } finally {
            app()->detectEnvironment(fn () => $environment);
            config(['logging.default' => $channel]);
        }

        //  Describe what happened to it
        $reached = $response->getData(true)['reached'] ?? false;

        return [
            $this->row('Reaches your controller', $reached ? 'Yes' : 'No', $reached),
            $this->row('Answer', $reached ? 'Whatever your controller returns' : $response->getContent()),
        ];
    }

    /**
     * Build one row of an answer
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function row(string $label, mixed $value, ?bool $passes = null): array
    {
        return [
            'label' => $label,
            'value' => (string) $value,
            'state' => $passes === null ? null : ($passes ? 'pass' : 'fail'),
        ];
    }
}
