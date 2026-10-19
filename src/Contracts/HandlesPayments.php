<?php

namespace Nyumbani\Daraja\Contracts;

use Nyumbani\Daraja\Payment;

/**
 * Implemented by the app and bound in the container, so the C2B routes can validate and book payments.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
interface HandlesPayments
{
    /**
     * Decide whether Safaricom should accept a payment before the customer is charged
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function validate(Payment $payment): bool;

    /**
     * Book a payment that was made. Safaricom can send the same confirmation more than once, so this must
     * be idempotent on the transaction id.
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function confirm(Payment $payment): void;
}
