<?php

namespace App\Policies;

use App\Models\CateringMember;
use App\Models\User;

class CateringMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CateringMember $cateringMember): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CateringMember $cateringMember): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CateringMember $cateringMember): bool
    {
        return $user->isAdmin();
    }
}
