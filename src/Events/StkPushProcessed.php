<?php

namespace Nyumbani\Daraja\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Nyumbani\Daraja\StkCallback;

/**
 * Broadcast when Safaricom reports the result of an STK push, on the channel named by the key the push was made with.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class StkPushProcessed implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * Create the event
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public string $key,
        public StkCallback $callback,
    ) {}

    /**
     * Get the channels the event broadcasts on
     *
     * @return array<int, Channel>
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function broadcastOn(): array
    {
        return [new Channel(config('daraja.broadcast_prefix').".{$this->key}")];
    }

    /**
     * Get the data the event broadcasts
     *
     * @return array<string, mixed>
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->callback->successful() ? 'success' : 'error',
            'merchantRequestId' => $this->callback->merchantRequestId,
            'checkoutRequestId' => $this->callback->checkoutRequestId,
            'description' => $this->callback->resultDescription,
        ];
    }
}
