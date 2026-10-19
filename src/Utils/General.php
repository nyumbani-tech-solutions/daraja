<?php

//  General helper functions

if (! function_exists('daraja_phone')) {
    /**
     * Normalise a Kenyan phone number to the 254XXXXXXXXX format Daraja expects
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    function daraja_phone(string $phone): string
    {
        return '254'.substr(preg_replace('/\D/', '', $phone), -9);
    }
}
