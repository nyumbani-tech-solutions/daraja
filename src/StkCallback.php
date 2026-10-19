<?php

namespace Nyumbani\Daraja;

/**
 * The result of an STK push that Safaricom posts back once the customer has paid, cancelled or timed out.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
final readonly class StkCallback
{
    /**
     * Create the callback
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public ?string $merchantRequestId,
        public ?string $checkoutRequestId,
        public int $resultCode,
        public string $resultDescription,
        public ?float $amount = null,
        public ?string $receipt = null,
        public ?string $phone = null,
    ) {}

    /**
     * Build the callback from the payload Safaricom posts
     *
     * @param  array<string, mixed>  $payload
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function fromPayload(array $payload): self
    {
        //  Get the callback body
        $body = $payload['Body']['stkCallback'] ?? [];

        //  Index the metadata items by name, which only exist on a successful payment
        $items = collect($body['CallbackMetadata']['Item'] ?? [])->pluck('Value', 'Name');

        return new self(
            merchantRequestId: $body['MerchantRequestID'] ?? null,
            checkoutRequestId: $body['CheckoutRequestID'] ?? null,
            resultCode: (int) ($body['ResultCode'] ?? -1),
            resultDescription: (string) ($body['ResultDesc'] ?? ''),
            amount: $items->has('Amount') ? (float) $items->get('Amount') : null,
            receipt: $items->get('MpesaReceiptNumber'),
            phone: $items->has('PhoneNumber') ? (string) $items->get('PhoneNumber') : null,
        );
    }

    /**
     * Get whether the customer paid
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function successful(): bool
    {
        return $this->resultCode === 0;
    }
}
