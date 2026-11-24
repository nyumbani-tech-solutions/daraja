<?php

namespace Nyumbani\Daraja;

use Illuminate\Support\Facades\Http;
use LogicException;
use Nyumbani\Daraja\Enums\ConfirmationKind;
use Nyumbani\Daraja\Enums\StkPushOutcome;

class StkPushSimulator
{
    /**
     * Create the simulator
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(protected StkPushAttempts $attempts) {}

    /**
     * Post the result of an attempt's STK push to the app's STK callback url, as Safaricom would
     *
     * @return array{url: string, sent: array, status: int, answer: mixed, attempt: array}
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function stk(array $attempt, StkPushOutcome $outcome, string $url): array
    {
        //  Never answer for Safaricom in production
        $this->refuseInProduction();

        //  Only a push that was sent has a checkout request to answer
        if (! ($attempt['checkoutRequestId'] ?? null)) {
            throw new LogicException(__('This payment has no STK push to answer.'));
        }

        //  Get the id Safaricom gave the request, or make one when the push was never sent
        $merchantRequestId = $attempt['merchantRequestId'] ?? str()->uuid()->toString();

        //  Start the callback with what every result carries
        $callback = [
            'MerchantRequestID' => $merchantRequestId,
            'CheckoutRequestID' => $attempt['checkoutRequestId'],
            'ResultCode' => $outcome->resultCode(),
            'ResultDesc' => $outcome->resultDescription(),
        ];

        //  A paid push also carries the amount, a new receipt, the time and the payer's number
        if ($outcome === StkPushOutcome::PAID) {
            $callback['CallbackMetadata'] = ['Item' => [
                ['Name' => 'Amount', 'Value' => $attempt['amount']],
                ['Name' => 'MpesaReceiptNumber', 'Value' => $this->receipt('S')],
                ['Name' => 'TransactionDate', 'Value' => (int) now()->format('YmdHis')],
                ['Name' => 'PhoneNumber', 'Value' => (int) $attempt['phone']],
            ]];
        }

        //  Wrap it the way Safaricom does
        $payload = ['Body' => ['stkCallback' => $callback]];

        //  Post the result of the attempt's STK push to the app's STK callback
        return $this->post($url, $payload, $attempt['id']);
    }

    /**
     * Post a C2B confirmation for an attempt to the app's confirmation url, as Safaricom would
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function confirmation(array $attempt, ConfirmationKind $kind, string $url): array
    {
        //  Never answer for Safaricom in production
        $this->refuseInProduction();

        //  A confirmation of the STK payment needs the receipt of a paid push
        if ($kind === ConfirmationKind::STK && ! ($attempt['receipt'] ?? null)) {
            throw new LogicException(__('Simulate a paid STK result first.'));
        }

        //  Get the receipt: the one the STK push gave, or a new one for a payment made by hand
        $transactionId = $kind === ConfirmationKind::STK ? $attempt['receipt'] : $this->receipt('M');

        //  Get the time of the payment
        $transactionTime = now()->format('YmdHis');

        //  Get the amount paid, which is KES 1 short when asked
        $amount = $kind === ConfirmationKind::SHORT ? max($attempt['amount'] - 1, 0) : $attempt['amount'];

        //  Get the account the payer typed, cut to the length Safaricom allows
        $reference = str($attempt['reference'])
            ->limit(StkPushAttempts::REFERENCE_LENGTH, '')
            ->toString();

        //  Build the confirmation the way Safaricom does
        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => $transactionTime,
            'TransAmount' => $amount,
            'BusinessShortCode' => $attempt['shortCode'],
            'BillRefNumber' => $reference,
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '0.00',
            'ThirdPartyTransID' => '',
            'MSISDN' => hash('sha256', (string) $attempt['phone']),
            'FirstName' => 'SIMULATED',
            'MiddleName' => '',
            'LastName' => '',
        ];

        //  Post a C2B confirmation of the attempt to the app's confirmation hookx
        return $this->post($url, $payload, $attempt['id']);
    }

    /**
     * Post the payload and describe the call, the answer and where the attempt stands afterwards
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function post(string $url, array $payload, string $id): array
    {
        //  Post to the app itself, which may be served with a certificate this machine does not trust
        $response = Http::withoutVerifying()->post($url, $payload);

        //  Describe the call
        return [
            'url' => $url,
            'sent' => $payload,
            'status' => $response->status(),
            'answer' => $response->json() ?? $response->body(),
            'attempt' => collect($this->attempts->find($id))->only(['status', 'reason', 'via', 'receipt', 'transactionId'])->all(),
        ];
    }

    /**
     * Refuse to run in production, where a made-up payment would be taken for a real one
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function refuseInProduction(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('The STK push simulator must not run in production.');
        }
    }

    /**
     * Make an M-Pesa receipt such as SAB1C2D3E4
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function receipt(string $prefix): string
    {
        return str(str()->random(10))
            ->upper()
            ->prepend($prefix)
            ->toString();
    }
}
