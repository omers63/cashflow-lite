<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserBalanceController extends Controller
{
    public function balance(User $user)
    {
        return response()->json([
            'user_id' => $user->id,
            'user_code' => $user->user_code,
            'bank_account_balance' => $user->bank_account_balance,
            'fund_account_balance' => $user->fund_account_balance,
            'outstanding_loans' => $user->outstanding_loans,
        ]);
    }

    public function availableToBorrow(User $user)
    {
        return response()->json([
            'user_id' => $user->id,
            'user_code' => $user->user_code,
            'available_to_borrow' => $user->available_to_borrow,
        ]);
    }
}
