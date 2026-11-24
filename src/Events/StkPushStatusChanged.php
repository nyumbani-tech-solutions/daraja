<?php

namespace Nyumbani\Daraja\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Nyumbani\Daraja\Enums\StkPushStatus;

/**
 * Tells the payer's browser, on a public channel only they know, where the STK push stands. It carries no personal data.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class StkPushStatusChanged implements ShouldBroadcast
{
    use Dispatchable;

    /**
     * Create the event with the attempt as it stands
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(public array $attempt) {}

    /**
     * Get the channel the UI subscribed to before it started the push
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function broadcastOn(): array
    {
        return ["stk.{$this->attempt['id']}"];
    }

    /**
     * Get the name the UI listens for
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    /**
     * Get what the UI is told: the status, the reason for a failure and whether to show how to pay manually
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->attempt['status'],
            'reason' => $this->attempt['reason'] ?? null,
            'manual' => StkPushStatus::from($this->attempt['status'])->showsManualInstructions(),
        ];
    }
}
