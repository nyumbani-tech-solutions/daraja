<?php

namespace Nyumbani\Daraja\Responses;

use Illuminate\Support\Collection;
use Nyumbani\Daraja\Payment;

/**
 * The outcome of a Pull transactions query.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
final readonly class PullResponse
{
    /**
     * Create the response from Daraja's reply
     *
     * @param  Collection<int, Payment>  $payments
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public bool $successful,
        public string $message,
        public Collection $payments,
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
