<?php

namespace Nyumbani\Daraja\Enums;

/**
 * Why an STK push attempt failed.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
enum StkPushFailureReason: string
{
    case CANCELLED = 'cancelled';
    case INSUFFICIENT_FUNDS = 'insufficient-funds';
    case WRONG_PIN = 'wrong-pin';
    case TIMEOUT = 'timeout';
    case REJECTED = 'rejected';
    case NO_RESPONSE = 'no-response';
    case OTHER = 'other';

    /**
     * Get the reason for the result code of an STK callback or query
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function fromResultCode(int $code): self
    {
        return match ($code) {
            1032 => self::CANCELLED,
            1 => self::INSUFFICIENT_FUNDS,
            2001 => self::WRONG_PIN,
            1037 => self::TIMEOUT,
            default => self::OTHER,
        };
    }
}
