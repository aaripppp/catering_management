<?php

namespace App\Policies;

use App\Models\CateringEmployeeUnit;
use App\Models\User;

class CateringEmployeeUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CateringEmployeeUnit $cateringEmployeeUnit): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CateringEmployeeUnit $cateringEmployeeUnit): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CateringEmployeeUnit $cateringEmployeeUnit): bool
    {
        return $user->isAdmin();
    }
}
