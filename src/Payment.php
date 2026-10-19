<?php

namespace Nyumbani\Daraja;

use Carbon\CarbonImmutable;

/**
 * A payment received on a paybill or till, as reported by a C2B confirmation or a Pull query.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
final readonly class Payment
{
    /**
     * Create the payment
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public string $transactionId,
        public ?string $reference,
        public float $amount,
        public CarbonImmutable $time,
        public ?string $payerName,
        public ?string $phone,
        public string $shortCode,
        public ?string $type = null,
        public ?float $balance = null,
    ) {}

    /**
     * Build a payment from a C2B validation or confirmation payload
     *
     * @param  array<string, mixed>  $payload
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function fromC2B(array $payload): self
    {
        //  Join the names Safaricom reports, which may be missing
        $name = trim(implode(' ', array_filter([
            $payload['FirstName'] ?? null,
            $payload['MiddleName'] ?? null,
            $payload['LastName'] ?? null,
        ])));

        return new self(
            transactionId: (string) ($payload['TransID'] ?? ''),
            reference: isset($payload['BillRefNumber']) ? (string) $payload['BillRefNumber'] : null,
            amount: (float) ($payload['TransAmount'] ?? 0),
            time: isset($payload['TransTime']) ? CarbonImmutable::parse((string) $payload['TransTime']) : CarbonImmutable::now(),
            payerName: $name ?: null,
            phone: isset($payload['MSISDN']) ? (string) $payload['MSISDN'] : null,
            shortCode: (string) ($payload['BusinessShortCode'] ?? ''),
            type: $payload['TransactionType'] ?? null,
            balance: isset($payload['OrgAccountBalance']) ? (float) $payload['OrgAccountBalance'] : null,
        );
    }

    /**
     * Build a payment from one entry of a Pull query
     *
     * @param  array<string, mixed>  $entry
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function fromPull(array $entry, string $shortCode): self
    {
        return new self(
            transactionId: (string) ($entry['transactionId'] ?? ''),
            reference: isset($entry['billreference']) ? (string) $entry['billreference'] : null,
            amount: (float) ($entry['amount'] ?? 0),
            time: CarbonImmutable::parse((string) ($entry['trxDate'] ?? 'now')),
            payerName: null,
            phone: isset($entry['msisdn']) ? (string) $entry['msisdn'] : null,
            shortCode: $shortCode,
            type: 'Pay Bill',
        );
    }

    /**
     * Get a copy of the payment with the phone number replaced, for resolving Safaricom's hashed numbers
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function withPhone(?string $phone): self
    {
        return new self(
            $this->transactionId,
            $this->reference,
            $this->amount,
            $this->time,
            $this->payerName,
            $phone,
            $this->shortCode,
            $this->type,
            $this->balance,
        );
    }
}
