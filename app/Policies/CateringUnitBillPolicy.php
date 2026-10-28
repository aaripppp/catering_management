<?php

namespace App\Policies;

use App\Models\CateringUnitBill;
use App\Models\User;

class CateringUnitBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CateringUnitBill $cateringUnitBill): bool
    {
        return $user->isAdmin();
    }

    public function sync(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewInvoice(User $user, CateringUnitBill $cateringUnitBill): bool
    {
        return $user->isAdmin();
    }

    public function viewCombinedInvoice(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
