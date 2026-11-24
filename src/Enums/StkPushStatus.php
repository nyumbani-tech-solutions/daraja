<?php

namespace Nyumbani\Daraja\Enums;

/**
 * Where an STK push attempt stands, which is also the status the UI is told about.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
enum StkPushStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case TIMED_OUT = 'timed-out';
    case CONFIRMED = 'confirmed';

    /**
     * Check whether the payer should be shown how to pay manually
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function showsManualInstructions(): bool
    {
        return match ($this) {
            self::FAILED, self::TIMED_OUT => true,
            default => false,
        };
    }

    /**
     * Check whether an STK result can still change the attempt
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function awaitsStkResult(): bool
    {
        return match ($this) {
            self::PENDING, self::TIMED_OUT => true,
            default => false,
        };
    }
}
