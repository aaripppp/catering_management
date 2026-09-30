<?php

use App\Enums\UserRole;
use App\Models\User;

it('stores the given role', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->fresh()->role)->toBe($role);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => $role->value,
    ]);
})->with(UserRole::cases());

it('falls back to the homeroom teacher role when no role is given', function () {
    $user = User::query()->create([
        'name' => 'Wali Kelas',
        'email' => 'wali@example.com',
        'password' => 'password',
    ]);

    expect($user->fresh()->role)->toBe(UserRole::WaliKelas);
});

it('identifies an administrator', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue()
        ->and($user->hasRole(UserRole::Admin))->toBeTrue();
});

it('does not identify a homeroom teacher as an administrator', function () {
    $user = User::factory()->waliKelas()->create();

    expect($user->isAdmin())->toBeFalse()
        ->and($user->hasRole(UserRole::WaliKelas))->toBeTrue();
});

it('exposes a human readable label for each role', function (UserRole $role, string $label) {
    expect($role->label())->toBe($label);
})->with([
    'admin' => [UserRole::Admin, 'Administrator'],
    'wali_kelas' => [UserRole::WaliKelas, 'Wali Kelas'],
]);
