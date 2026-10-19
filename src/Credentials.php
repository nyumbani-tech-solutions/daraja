<?php

namespace Nyumbani\Daraja;

/**
 * The credentials of one paybill or till, which an app builds from its env, config or database.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
final readonly class Credentials
{
    /**
     * Create the credentials, where a till number makes STK pushes pay a till (Buy Goods) instead of a paybill
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function __construct(
        public string $shortCode,
        public string $consumerKey,
        public string $consumerSecret,
        public ?string $passKey = null,
        public ?string $tillNumber = null,
        public Environment $environment = Environment::Production,
    ) {}

    /**
     * Get the transaction type an STK push is made with
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function transactionType(): string
    {
        return $this->tillNumber
            ? 'CustomerBuyGoodsOnline'
            : 'CustomerPayBillOnline';
    }

    /**
     * Get the number that receives an STK push payment
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function receiver(): string
    {
        return $this->tillNumber ?? $this->shortCode;
    }
}
