<?php

namespace Nyumbani\Daraja\Enums;

enum StkPushOutcome: string
{
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case INSUFFICIENT_FUNDS = 'insufficient-funds';
    case WRONG_PIN = 'wrong-pin';
    case TIMEOUT = 'timeout';

    /**
     * Get the result code Safaricom sends for the outcome
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function resultCode(): int
    {
        return match ($this) {
            self::PAID => 0,
            self::CANCELLED => 1032,
            self::INSUFFICIENT_FUNDS => 1,
            self::WRONG_PIN => 2001,
            self::TIMEOUT => 1037,
        };
    }

    /**
     * Get the result description Safaricom sends for the outcome
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function resultDescription(): string
    {
        return match ($this) {
            self::PAID => 'The service request is processed successfully.',
            self::CANCELLED => 'Request cancelled by user.',
            self::INSUFFICIENT_FUNDS => 'The balance is insufficient for the transaction.',
            self::WRONG_PIN => 'The initiator information is invalid.',
            self::TIMEOUT => 'DS timeout user cannot be reached.',
        };
    }
}
