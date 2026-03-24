<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Loan;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Loan $loan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Loan $loan): bool
    {
        return true;
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $loan->status === 'pending';
    }

    public function approve(User $user, Loan $loan): bool
    {
        return $loan->status === 'pending';
    }
}
