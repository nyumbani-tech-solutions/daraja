<?php

namespace Nyumbani\Daraja\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EnsureRequestIsFromSafaricom
{
    /**
     * Reject a request that does not come from a Safaricom IP address
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        //  Skip the check in the configured environments
        if (app()->environment(config('daraja.skip_ip_check_in', []))) {
            return $next($request);
        }

        //  Let Safaricom's addresses through
        if (in_array($request->ip(), config('daraja.allowed_ips', []), true)) {
            return $next($request);
        }

        //  Log the rejection
        Log::warning('Rejected a Daraja request', ['ip' => $request->ip()]);

        //  Return a failure response
        return response()->json([
            'ResultCode' => 'C2B00016',
            'ResultDesc' => 'Rejected',
        ]);
    }
}
