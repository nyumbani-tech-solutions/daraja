<?php

namespace Nyumbani\Daraja\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;
use Nyumbani\Daraja\Enums\ConfirmationKind;
use Nyumbani\Daraja\Enums\StkPushOutcome;
use Nyumbani\Daraja\StkPushAttempts;
use Nyumbani\Daraja\StkPushSimulator;

class StkPushSimulationController
{
    /**
     * Create the controller
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        protected StkPushSimulator $simulator,
        protected StkPushAttempts $attempts
    ) {}

    /**
     * Post the result of the attempt's STK push to the app's STK callback, as Safaricom would
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function stk(Request $request): JsonResponse
    {
        //  Define the callback
        $callback = fn (array $attempt, $outcome) => $this->simulator->stk(
            attempt: $attempt,
            outcome: $outcome,
            url: route(config('daraja.stk.hooks.stk'))
        );

        //  Post the result of the attempt's STK push to the app's STK callback
        return $this->answer($request, StkPushOutcome::class, $callback);
    }

    /**
     * Post a C2B confirmation of the attempt to the app's confirmation hook, as Safaricom would
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function confirmation(Request $request): JsonResponse
    {
        //  Define the callback
        $callback = fn (array $attempt, $kind) => $this->simulator->confirmation(
            attempt: $attempt,
            kind: $kind,
            url: route(config('daraja.stk.hooks.confirmation'))
        );

        //  Post a C2B confirmation of the attempt to the app's confirmation hook
        return $this->answer($request, ConfirmationKind::class, $callback);
    }

    /**
     * Validate the attempt and the outcome, run the simulation and answer with its report, or the reason it was refused
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function answer(Request $request, string $enum, callable $simulate): JsonResponse
    {
        //  Answer only while pushes are simulated, and never in production
        abort_if(app()->isProduction() || ! config('daraja.stk.simulate'), 404);

        //  Validate what the page sent
        $data = $request->validate([
            'id' => ['required', 'uuid:4'],
            'outcome' => ['required', Rule::enum($enum)],
        ]);

        //  Refuse an attempt nobody knows
        if (! $attempt = $this->attempts->find($data['id'])) {
            return response()->json(['message' => __('This payment is not known.')], 422);
        }

        //  try to simulate
        try {
            return response()->json($simulate($attempt, $enum::from($data['outcome'])));

            //  catch any errors
        } catch (LogicException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
