<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * Return the customer with this email, creating it when the email is new.
     *
     * An existing customer is returned as is: the stored name is never overwritten by
     * whatever name the current request happens to carry.
     *
     * @throws ValidationException when the email is new and no name was given
     */
    public function findOrCreate(string $email, ?string $name): Customer
    {
        $email = Customer::normalizeEmail($email);

        $customer = Customer::query()->where('email', $email)->first();

        if ($customer !== null) {
            return $customer;
        }

        $name = $name === null ? '' : trim($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'customer.name' => ['The customer name is required when the email address is new.'],
            ]);
        }

        try {
            return Customer::create(['name' => $name, 'email' => $email]);
        } catch (UniqueConstraintViolationException) {
            // Another request created the same customer between our lookup and our insert
            // (the unique index on email caught it). On MySQL the failed INSERT does not
            // abort the surrounding transaction, so we can fall back to the winner's row.
            //
            // This re-select MUST be a locking read. Under the default REPEATABLE READ
            // isolation our first plain SELECT froze this transaction's snapshot, so a plain
            // re-select would keep returning "no such customer" even though the row is
            // committed. A locking read always reads the latest committed row.
            //
            // A shared lock is enough (we only read the row) and avoids a deadlock: the failed
            // INSERT leaves every loser holding a shared lock on that row, so several losers
            // asking for an exclusive lock (FOR UPDATE) would block each other.
            return Customer::query()
                ->where('email', $email)
                ->sharedLock()
                ->firstOrFail();
        }
    }
}
