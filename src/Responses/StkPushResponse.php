<?php

namespace Nyumbani\Daraja\Responses;

/**
 * The outcome of an STK push, where success only means the prompt reached the phone, not that it was paid.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
final readonly class StkPushResponse
{
    /**
     * Create the response from Daraja's reply
     *
     * @param  array<string, mixed>  $data
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public bool $successful,
        public string $message,
        public ?string $merchantRequestId = null,
        public ?string $checkoutRequestId = null,
        public array $data = [],
    ) {}

    /**
     * Get whether Daraja refused the request
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function failed(): bool
    {
        return ! $this->successful;
    }
}
