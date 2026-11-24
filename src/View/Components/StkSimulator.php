<?php

namespace Nyumbani\Daraja\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class StkSimulator extends Component
{
    /**
     * Create the simulator, whose buttons answer for Safaricom: pass the contract of the attempt the page follows, or none to let the panel list the payments the app started lately. The urls default to the routes this package registers for its docs
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public ?array $attempt = null,
        public ?string $stkUrl = null,
        public ?string $confirmationUrl = null,
        public ?string $recentUrl = null,
    ) {
        //  Define the stk url
        $this->stkUrl ??= Route::has('daraja.simulate.stk')
            ? route('daraja.simulate.stk')
            : null;

        //  Define the recent url, which a panel without an attempt picks a payment from
        $this->recentUrl ??= Route::has('daraja.simulate.recent')
            ? route('daraja.simulate.recent')
            : null;

        //  Define the confirmation url
        $this->confirmationUrl ??= Route::has('daraja.simulate.confirmation')
            ? route('daraja.simulate.confirmation')
            : null;
    }

    /**
     * Render nothing in production or without routes to post to, which only the docs register, so the panel can stay in a view and vanish outside the docs
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function shouldRender(): bool
    {
        return ! app()->isProduction()
            && $this->stkUrl !== null
            && $this->confirmationUrl !== null;
    }

    /**
     * Get the view
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function render(): View
    {
        return view('daraja::components.stk-simulator');
    }
}
