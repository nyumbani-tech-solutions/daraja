<?php

namespace Nyumbani\Daraja\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nyumbani\Daraja\Contracts\HandlesPayments;
use Nyumbani\Daraja\Daraja;
use Nyumbani\Daraja\Events\PaymentReceived;
use Nyumbani\Daraja\Payment;

class C2BController
{
    /**
     * Answer Safaricom's validation request with the app's decision
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function validation(Request $request, HandlesPayments $handler): JsonResponse
    {
        return $handler->validate($this->payment($request))
            ? $this->accepted()
            : response()->json(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
    }

    /**
     * Hand a confirmed payment to the app and acknowledge it
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function confirmation(Request $request, HandlesPayments $handler): JsonResponse
    {
        //  Get the payment
        $payment = $this->payment($request);

        //  Let the app book it
        $handler->confirm($payment);

        //  Announce it
        PaymentReceived::dispatch($payment);

        return $this->accepted();
    }

    /**
     * Acknowledge a Pull notification, which only announces that transactions are available
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function pull(): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    /**
     * Build the payment from the request, resolving its phone number
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function payment(Request $request): Payment
    {
        return Daraja::resolvePhone(Payment::fromC2B($request->all()));
    }

    /**
     * Get the response that accepts a request
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function accepted(): JsonResponse
    {
        return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
    }
}
