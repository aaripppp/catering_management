<?php

use App\Models\User;

it('renders the login page for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Catering Management')
        ->assertSee('Sistem Manajemen Catering Sekolah');
});

it('authenticates a user with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('does not authenticate a user with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders the dashboard for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Catering Management')
        ->assertSee('Sistem Manajemen Catering Sekolah');
});

it('redirects an authenticated user away from the login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

it('logs an authenticated user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});

it('does not expose a registration route', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});
