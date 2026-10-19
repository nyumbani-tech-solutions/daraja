<?php

namespace Nyumbani\Daraja\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Nyumbani\Daraja\Payment;

/**
 * Dispatched after the app's handler has confirmed a C2B payment.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class PaymentReceived
{
    use Dispatchable;

    /**
     * Create the event
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(public Payment $payment) {}
}
