<?php

namespace App\Policies;

use App\Models\CateringCategory;
use App\Models\User;

class CateringCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CateringCategory $cateringCategory): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CateringCategory $cateringCategory): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CateringCategory $cateringCategory): bool
    {
        // Admin can delete categories even if members exist (cascade delete is intentional)
        return $user->isAdmin();
    }
}
