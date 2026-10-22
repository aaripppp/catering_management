<?php

namespace App\Policies;

use App\Models\CateringUnitAttendance;
use App\Models\User;

class CateringUnitAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CateringUnitAttendance $cateringUnitAttendance): bool
    {
        return $user->isAdmin();
    }

    public function updateAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
