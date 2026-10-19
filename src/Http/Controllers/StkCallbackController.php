<?php

namespace Nyumbani\Daraja\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nyumbani\Daraja\Events\StkPushProcessed;
use Nyumbani\Daraja\StkCallback;

class StkCallbackController
{
    /**
     * Broadcast the result of an STK push on the channel named in the signed url
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __invoke(Request $request): JsonResponse
    {
        StkPushProcessed::dispatch((string) $request->query('key'), StkCallback::fromPayload($request->all()));

        return response()->json(['message' => 'Success']);
    }
}
