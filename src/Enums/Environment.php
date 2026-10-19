<?php

namespace Nyumbani\Daraja\Enums;

/**
 * The Daraja environment a set of credentials belongs to.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    /**
     * Get the base url of the Daraja API for this environment
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://sandbox.safaricom.co.ke',
            self::Production => 'https://api.safaricom.co.ke',
        };
    }
}
