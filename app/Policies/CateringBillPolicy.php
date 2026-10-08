<?php

namespace App\Policies;

use App\Models\CateringBill;
use App\Models\User;

class CateringBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CateringBill $cateringBill): bool
    {
        return $user->isAdmin();
    }

    public function generate(User $user): bool
    {
        return $user->isAdmin();
    }

    public function recordPayment(User $user, CateringBill $cateringBill): bool
    {
        return $user->isAdmin();
    }

    public function updatePayment(User $user, CateringBill $cateringBill): bool
    {
        return $user->isAdmin();
    }

    public function deletePayment(User $user, CateringBill $cateringBill): bool
    {
        return $user->isAdmin();
    }
}
