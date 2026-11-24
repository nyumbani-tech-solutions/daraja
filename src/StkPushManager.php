<?php

namespace Nyumbani\Daraja;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use LogicException;
use Nyumbani\Daraja\Enums\StkPushFailureReason;
use Nyumbani\Daraja\Enums\StkPushStatus;
use Nyumbani\Daraja\Events\StkPushStatusChanged;
use Nyumbani\Daraja\Exceptions\DarajaException;

/**
 * Runs the standard STK push flow: it starts a push, takes the STK callback and the C2B confirmation, and tells the payer's browser.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class StkPushManager
{
    /**
     * Tells whether the app has received a payment for an attempt, for the payer's "I've paid" button, set once for the app like a macro
     */
    protected static ?Closure $paymentResolver = null;

    /**
     * Create the manager
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(protected StkPushAttempts $attempts) {}

    /**
     * Register how the app tells that an attempt was paid, returning the transaction id, true or nothing
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function resolvePaymentUsing(?Closure $resolver): void
    {
        static::$paymentResolver = $resolver;
    }

    /**
     * Start tracking a payment without prompting the payer, who pays by hand against the reference
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function track(string $shortCode, float|int $amount, ?string $reference = null, ?string $phone = null): array
    {
        //  Define the reference length
        $string = str()->random(StkPushAttempts::REFERENCE_LENGTH);

        //  Define the reference
        $reference = $reference ?: str($string)->upper()->toString();

        //  Define the attempt
        $attempt = [
            'id' => str()->uuid()->toString(),
            'shortCode' => $shortCode,
            'reference' => $reference,
            'amount' => (int) round($amount),
            'phone' => $phone ? daraja_phone($phone) : null,
            'status' => StkPushStatus::PENDING->value,
            'reason' => null,
            'checkoutRequestId' => null,
            'merchantRequestId' => null,
            'receipt' => null,
            'transactionId' => null,
            'via' => null,
        ];

        //  Refuse an id that is already used
        $this->attempts->add($attempt);

        //  Track the attempt
        return $attempt;
    }

    /**
     * Prompt the payer to pay, which a short code with a till number cannot do
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function initiate(
        ShortCode $shortCode,
        string $phone,
        float|int $amount,
        string $description,
        string $callbackUrl,
        ?string $reference = null,
    ): array {
        //  The manual instructions only work for a paybill
        if ($shortCode->tillNumber) {
            throw new LogicException('The standard STK push flow supports a paybill only.');
        }

        //  Track the attempt
        $attempt = $this->track($shortCode->value, $amount, $reference, $phone);

        //  Ask Daraja to prompt the payer
        $answer = $this->send($shortCode, $attempt, $description, $callbackUrl);

        //  A push Daraja did not accept has nothing to wait for
        if (! $answer) {
            return $this->reject($attempt);
        }

        //  Keep the checkout request, which the callback carries, and the merchant request, which Safaricom quotes
        $attempt = $this->attempts->save([
            ...$attempt,
            'checkoutRequestId' => $answer['CheckoutRequestID'],
            'merchantRequestId' => $answer['MerchantRequestID'] ?? null,
        ]);

        //  Return the attempt
        return $attempt;
    }

    /**
     * Take the STK callback, once however many times Safaricom posts it
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handleCallback(array $payload): ?array
    {
        //  Find the attempt
        $checkoutRequestId = data_get($payload, 'Body.stkCallback.CheckoutRequestID');

        //  Find the attempt
        $attempt = $checkoutRequestId
            ? $this->attempts->findByCheckoutRequestId($checkoutRequestId)
            : null;

        //  Refuse an attempt nobody knows
        if (! $attempt) {
            return null;
        }

        //  Keep the merchant request when the push response did not carry it
        $attempt['merchantRequestId'] ??= data_get($payload, 'Body.stkCallback.MerchantRequestID');

        //  Read the receipt of a payment
        $receipt = collect(data_get($payload, 'Body.stkCallback.CallbackMetadata.Item', []))
            ->pluck('Value', 'Name')
            ->get('MpesaReceiptNumber');

        //  Apply the result
        return $this->applyResult($attempt, (int) data_get($payload, 'Body.stkCallback.ResultCode', -1), $receipt);
    }

    /**
     * Take a C2B confirmation and, when the payer is waiting on it, confirm the attempt
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function confirmPayment(array $payload): ?array
    {
        //  The M-Pesa receipt of this payment, such as SAB1C2D3E4
        $transactionId = (string) ($payload['TransID'] ?? '');

        //  The account the payer typed, which is the reference the push was started with
        $reference = (string) ($payload['BillRefNumber'] ?? '');

        //  The paybill that was paid, which keeps one reference apart from the same one on another paybill
        $shortCode = (string) ($payload['BusinessShortCode'] ?? '');

        //  First, a payment made on the STK prompt: its receipt is the one the STK result already gave the attempt
        $attempt = $transactionId !== ''
            ? $this->attempts->findByReceipt($transactionId)
            : null;

        //  Otherwise, a payment made by hand: the account the payer typed is the only link to the attempt
        if (! $attempt && $reference !== '') {
            $attempt = $this->attempts->findByReference($shortCode, $reference);
        }

        //  No attempt means nobody is waiting on this payment, which is fine: the app records it anyway
        if (! $attempt) {
            return null;
        }

        //  Do nothing twice
        if ($attempt['status'] === StkPushStatus::CONFIRMED->value) {
            return $attempt;
        }

        //  An attempt is not confirmed by less than it asked for
        if ((float) ($payload['TransAmount'] ?? 0) < $attempt['amount']) {
            return null;
        }

        //  Confirm the attempt
        return $this->confirm($attempt, $transactionId);
    }

    /**
     * Check whether the payer has paid, for their "I've paid" button, asking the app at most once in a while
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function check(string $id): ?array
    {
        //  Find the attempt
        $attempt = $this->attempts->find($id);

        //  There is nothing to ask when it is confirmed or the app cannot tell
        if (! $attempt || $attempt['status'] === StkPushStatus::CONFIRMED->value || ! static::$paymentResolver) {
            return $attempt;
        }

        //  Do not let the button hammer the app
        if (! cache()->add("daraja.stk.check.{$id}", true, config('daraja.stk.check_throttle', 10))) {
            return $attempt;
        }

        //  Ask the app
        $payment = (static::$paymentResolver)($attempt);

        //  Confirm the attempt
        return $payment
            ? $this->confirm($attempt, is_string($payment) ? $payment : '')
            : $attempt;
    }

    /**
     * Describe an attempt for the UI: where to listen, where it stands, the number prompted, how long to wait for the payer, how to follow the payment (websocket or polling) and how to pay by hand
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function contract(array $attempt): array
    {
        return [
            'id' => $attempt['id'],
            'channel' => "stk.{$attempt['id']}",
            'status' => $attempt['status'],
            'reason' => $attempt['reason'],
            'phone' => $attempt['phone'],
            'timeout' => (int) config('daraja.stk.timeout', 120),
            'mode' => config('daraja.stk.mode'),
            'interval' => (int) config('daraja.stk.poll_interval', 3),
            'duration' => (int) config('daraja.stk.poll_duration', 300),
            'manual' => [
                'paybill' => $attempt['shortCode'],
                'account' => $attempt['reference'],
                'amount' => $attempt['amount'],
            ],
        ];
    }

    /**
     * Send the push to Daraja, answering with what it said or nothing when it did not accept the push
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function send(ShortCode $shortCode, array $attempt, string $description, string $callbackUrl): ?array
    {
        //  Try to send
        try {
            //  Define the reference
            $reference = str($attempt['reference'])
                ->limit(StkPushAttempts::REFERENCE_LENGTH, '')
                ->toString();

            //  Send the push, with the reference as Daraja cuts it
            $response = Daraja::for($shortCode)->stkPush(
                phone: $attempt['phone'],
                amount: $attempt['amount'],
                accountReference: $reference,
                description: $description,
                callbackUrl: $callbackUrl,
            );

            //  Catch any exception
        } catch (ConnectionException|DarajaException) {
            return null;
        }

        //  Define the conditions
        $conditions = $response->failed() || $response->json('ResponseCode') !== '0';

        //  Daraja can answer HTTP 200 with a failure
        return $conditions ? null : $response->json();
    }

    /**
     * Fail an attempt Daraja refused, which the UI learns from the response to its request
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function reject(array $attempt): array
    {
        //  Keep the failure
        $attempt = $this->attempts->save([
            ...$attempt,
            'status' => StkPushStatus::FAILED->value,
            'reason' => StkPushFailureReason::REJECTED->value,
        ]);

        //  Return the attempt
        return $attempt;
    }

    /**
     * Move an attempt to the result of an STK callback or query, unless it has been decided already
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function applyResult(array $attempt, int $resultCode, ?string $receipt): array
    {
        //  Do nothing twice
        if (! StkPushStatus::from($attempt['status'])->awaitsStkResult()) {
            return $attempt;
        }

        //  The payer paid
        if ($resultCode === 0) {
            //  Save the receipt
            $attempt = $this->attempts->save([
                ...$attempt,
                'status' => StkPushStatus::PAID->value,
                'receipt' => $receipt,
            ]);

            //  Dispatch a status change to the ws
            StkPushStatusChanged::dispatch($attempt);

            //  Return
            return $attempt;
        }

        //  The payer did not
        $attempt = $this->attempts->save([
            ...$attempt,
            'status' => StkPushStatus::FAILED->value,
            'reason' => StkPushFailureReason::fromResultCode($resultCode)->value,
        ]);

        //  Dispatch a status change to the ws
        StkPushStatusChanged::dispatch($attempt);

        //  Return
        return $attempt;
    }

    /**
     * Confirm an attempt, which stays possible after it failed or timed out
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function confirm(array $attempt, string $transactionId): array
    {
        //  A payment that confirmed another attempt is not this attempt's, such as an earlier payment for a reused reference
        $claimedBy = $transactionId !== ''
            ? $this->attempts->findByTransactionId($transactionId)
            : null;

        //  A payment with the receipt of the push is the push, any other is a manual payment
        if ($claimedBy && $claimedBy['id'] !== $attempt['id']) {
            return $attempt;
        }

        //  A payment with the receipt of the push is the push, any other is a manual payment
        $attempt = $this->attempts->save([
            ...$attempt,
            'status' => StkPushStatus::CONFIRMED->value,
            'reason' => null,
            'transactionId' => $transactionId ?: null,
            'via' => $attempt['receipt'] && $attempt['receipt'] === $transactionId
                ? 'stk'
                : 'manual',
        ]);

        //  Dispatch a status change to the ws
        StkPushStatusChanged::dispatch($attempt);

        //  Return the attempt
        return $attempt;
    }
}
