<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Reconciliation;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReconciliationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Reconciliation $reconciliation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, Reconciliation $reconciliation): bool
    {
        return $reconciliation->status !== 'complete';
    }
}
