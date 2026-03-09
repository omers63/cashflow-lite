<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return true;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $transaction->status === 'pending';
    }

    public function reverse(User $user, Transaction $transaction): bool
    {
        return $transaction->status === 'complete';
    }
}
