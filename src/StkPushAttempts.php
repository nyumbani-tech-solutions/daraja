<?php

namespace Nyumbani\Daraja;

use DateTimeInterface;

/**
 * Keeps the STK push attempts in the cache for a while, found by id, checkout request, receipt or reference.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class StkPushAttempts
{
    /**
     * The longest account reference Daraja accepts on an STK push
     */
    public const REFERENCE_LENGTH = 12;

    /**
     * The cache key of the ids of the latest attempts
     */
    public const RECENT_KEY = 'daraja.stk.recent';

    /**
     * Store a new attempt, unless its id is already used
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function add(array $attempt): bool
    {
        //  Refuse an id that is already taken
        if (! cache()->add($this->key($attempt['id']), $attempt, $this->expiry())) {
            return false;
        }

        //  Index the attempt
        $this->index($attempt);

        //  Remember it for the simulator of the docs
        $this->remember($attempt['id']);

        return true;
    }

    /**
     * Get the latest attempts that are still remembered, newest first, for the simulator of the docs to pick from. Nothing is kept in production
     *
     * @return array<int, array>
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function recent(): array
    {
        return collect(cache()->get(self::RECENT_KEY, []))
            ->map(fn (string $id) => $this->find($id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Remember the id of an attempt among the latest ten, outside production
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function remember(string $id): void
    {
        //  Nothing is kept in production
        if (app()->isProduction()) {
            return;
        }

        //  Put the id first and keep the latest ten
        $ids = collect(cache()->get(self::RECENT_KEY, []))
            ->prepend($id)
            ->unique()
            ->take(10)
            ->values()
            ->all();

        cache()->put(self::RECENT_KEY, $ids, $this->expiry());
    }

    /**
     * Store the attempt as it now stands
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function save(array $attempt): array
    {
        //  Store the attempt
        cache()->put($this->key($attempt['id']), $attempt, $this->expiry());

        //  Index the attempt
        $this->index($attempt);

        //  Return the attempt
        return $attempt;
    }

    /**
     * Find an attempt by its id
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function find(string $id): ?array
    {
        return cache()->get($this->key($id));
    }

    /**
     * Find the attempt Daraja is working on
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function findByCheckoutRequestId(string $checkoutRequestId): ?array
    {
        return $this->findIndexed("checkout.{$checkoutRequestId}");
    }

    /**
     * Find the attempt that was paid with an M-Pesa receipt number
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function findByReceipt(string $receipt): ?array
    {
        return $this->findIndexed("receipt.{$receipt}");
    }

    /**
     * Find the attempt a payment confirmed, so one payment never confirms two attempts
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function findByTransactionId(string $transactionId): ?array
    {
        return $this->findIndexed("transaction.{$transactionId}");
    }

    /**
     * Find the latest attempt for a reference on a short code
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public function findByReference(string $shortCode, string $reference): ?array
    {
        return $this->findIndexed("reference.{$shortCode}.{$reference}");
    }

    /**
     * Point the lookups at the attempt, including the reference as an STK push cuts it
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function index(array $attempt): void
    {
        //  Collect the lookups
        $lookups = collect([
            'reference.'.$attempt['shortCode'].'.'.$attempt['reference'],
            'reference.'.$attempt['shortCode'].'.'.str($attempt['reference'])->limit(self::REFERENCE_LENGTH, ''),
        ]);

        //  Add the checkout request and the receipt, once known
        if ($attempt['checkoutRequestId'] ?? null) {
            $lookups->push("checkout.{$attempt['checkoutRequestId']}");
        }

        //  Add the receipt
        if ($attempt['receipt'] ?? null) {
            $lookups->push("receipt.{$attempt['receipt']}");
        }

        //  Add the payment that confirmed the attempt
        if ($attempt['transactionId'] ?? null) {
            $lookups->push("transaction.{$attempt['transactionId']}");
        }

        //  Point each lookup at the id
        $lookups
            ->unique()
            ->each(fn (string $lookup) => cache()->put($this->key($lookup), $attempt['id'], $this->expiry()));
    }

    /**
     * Find an attempt through a lookup
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function findIndexed(string $lookup): ?array
    {
        $id = cache()->get($this->key($lookup));

        return $id ? $this->find($id) : null;
    }

    /**
     * Get the cache key
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function key(string $name): string
    {
        return "daraja.stk.{$name}";
    }

    /**
     * Get when an attempt is forgotten
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected function expiry(): DateTimeInterface
    {
        return now()->addMinutes(config('daraja.stk.ttl', 60));
    }
}
