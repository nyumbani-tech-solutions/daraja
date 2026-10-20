<?php

namespace Nyumbani\Daraja\Docs;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReceivedHookController
{
    /**
     * Keep what Safaricom posted, so the docs can show it, and answer that it was accepted
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __invoke(Request $request, string $hook): JsonResponse
    {
        //  Keep the latest ten, newest first
        $received = collect(Cache::get(DarajaDemos::RECEIVED_KEY, []))
            ->prepend(['hook' => $hook, 'at' => now()->toDateTimeString(), 'ip' => $request->ip(), 'payload' => $request->all()])
            ->take(10)
            ->all();

        Cache::put(DarajaDemos::RECEIVED_KEY, $received, now()->addDay());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
