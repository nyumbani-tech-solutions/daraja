<?php

namespace Nyumbani\Daraja\Responses;

/**
 * The outcome of a Daraja call that only reports success or failure.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
final readonly class ApiResponse
{
    /**
     * Create the response from Daraja's reply
     *
     * @param  array<string, mixed>  $data
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public bool $successful,
        public string $message,
        public array $data = [],
    ) {}

    /**
     * Get whether Daraja refused the request
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function failed(): bool
    {
        return ! $this->successful;
    }
}
