{{--  Buttons that answer for Safaricom while pushes are simulated: each makes the server post to the app's own hooks  --}}
<div x-data="darajaStkSimulator(@js($attempt['id'] ?? null), @js(__('The simulation failed.')), @js($recentUrl))" {{ $attributes->class('space-y-3 text-sm') }}>
    <p class="text-xs text-gray-500">{{ __('Each button makes the server post to the hooks of this app, as Safaricom would.') }}</p>

    {{--  Without an attempt the panel lists the payments the app started lately, to answer for one of them  --}}
    @if (! $attempt && $recentUrl)
        <div class="flex items-center gap-2" x-init="load()">
            <select x-model="attemptId" class="min-w-0 flex-1 rounded border border-gray-300 px-2 py-1 text-xs">
                <option value="">{{ __('Pick a payment to answer for') }}</option>
                <template x-for="payment in payments" :key="payment.id">
                    <option :value="payment.id" x-text="payment.label"></option>
                </template>
            </select>
            <button type="button" class="rounded border border-gray-300 px-2.5 py-1 text-xs" x-on:click="load()">{{ __('Refresh') }}</button>
        </div>
        <p x-show="loaded && ! payments.length" x-cloak class="text-xs text-gray-500">{{ __('No payments yet. Start one in your app, fake or real, then press Refresh.') }}</p>
    @endif

    <div>
        <p class="mb-1 text-xs font-medium">{{ __('STK result') }}</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach (['paid' => __('Paid'), 'cancelled' => __('Cancelled'), 'insufficient-funds' => __('No funds'), 'wrong-pin' => __('Wrong PIN'), 'timeout' => __('Timeout')] as $outcome => $label)
                <button type="button" class="rounded bg-primary px-2.5 py-1 text-xs font-medium text-white disabled:opacity-50" :disabled="busy || ! attemptId" x-on:click="simulate(@js($stkUrl), @js($outcome))">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div>
        <p class="mb-1 text-xs font-medium">{{ __('C2B confirmation, the real proof of payment') }}</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach (['stk' => __('For the STK payment'), 'manual' => __('Paid by hand'), 'short' => __('Paid by hand, KES 1 short')] as $outcome => $label)
                <button type="button" class="rounded bg-primary px-2.5 py-1 text-xs font-medium text-white disabled:opacity-50" :disabled="busy || ! attemptId" x-on:click="simulate(@js($confirmationUrl), @js($outcome))">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></p>

    <template x-if="result">
        <div class="space-y-2 text-xs">
            <p>{{ __('Posted to') }} <code x-text="result.url"></code>, {{ __('answered') }} <b x-text="result.status"></b></p>
            <p class="font-medium">{{ __('Attempt now') }}</p>
            <pre class="overflow-x-auto rounded bg-gray-100 p-2" x-text="JSON.stringify(result.attempt, null, 2)"></pre>
            <p class="font-medium">{{ __('Payload sent') }}</p>
            <pre class="max-h-64 overflow-auto rounded bg-gray-100 p-2" x-text="JSON.stringify(result.sent, null, 2)"></pre>
        </div>
    </template>
</div>

{{--  Define the component's state once per page, so the package needs no script of its own to be bundled  --}}
@once
    <script>
        /**
         * Get the state of the simulator panel, which answers for the attempt with the given id
         */
        window.darajaStkSimulator = (attemptId, failure, recentUrl) => ({
            attemptId,
            payments: [],
            loaded: false,
            busy: false,
            result: null,
            error: null,

            /**
             * Load the payments the app started lately, newest first, keeping the one picked while it is still listed
             */
            async load() {
                try {
                    //  Ask the app for its latest payments
                    const response = await fetch(recentUrl, { headers: { 'Accept': 'application/json' } });

                    //  Keep them, or none when the app refused
                    this.payments = response.ok ? await response.json() : [];
                } catch {
                    //  The server could not be reached
                    this.payments = [];
                }

                //  Forget a pick that is no longer listed, and say the list was loaded
                if (!this.payments.some((payment) => payment.id === this.attemptId)) this.attemptId = '';
                this.loaded = true;
            },

            /**
             * Post an outcome to a simulation route and keep the report
             */
            async simulate(url, outcome) {
                //  Lock the buttons and forget any error
                this.busy = true;
                this.error = null;

                //  Get the csrf token
                const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

                try {
                    //  Ask the app to post the simulated callback to its own hook
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ id: this.attemptId, outcome }),
                    });

                    //  Read the answer, which is an empty object when it was not JSON
                    const body = await response.json().catch(() => ({}));

                    //  Keep the report when it worked, and the reason it was refused when it did not
                    this.result = response.ok ? body : null;
                    this.error = response.ok ? null : (body.message ?? failure);
                } catch {
                    //  The server could not be reached
                    this.error = failure;
                } finally {
                    //  Unlock the buttons
                    this.busy = false;
                }
            },
        });
    </script>
@endonce
