<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

function assertAnnurBranding(TestResponse $response): void
{
    $response
        ->assertOk()
        ->assertSee('rel="icon" type="image/png"', false)
        ->assertSee('rel="shortcut icon" type="image/png"', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('images/annur_logo2.png', false)
        ->assertDontSee('Laravel');
}

it('uses the canonical Annur asset without a legacy favicon file', function () {
    expect(public_path('images/annur_logo2.png'))->toBeFile()
        ->and(public_path('favicon.ico'))->not->toBeFile();
});

it('shows Annur branding on the public welcome page', function () {
    $response = $this->get('/');

    assertAnnurBranding($response);
    $response->assertSee('Logo Annur');
});

it('shows Annur branding on the login page', function () {
    $response = $this->get(route('login'));

    assertAnnurBranding($response);
    $response->assertSee('Logo Annur');
});

it('shows Annur branding on authenticated application pages', function (string $routeName) {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route($routeName));

    assertAnnurBranding($response);
    $response->assertSee('Logo Annur');
})->with([
    'dashboard' => ['dashboard'],
    'catering members' => ['catering-members.index'],
    'school classes' => ['school-classes.index'],
    'catering categories' => ['catering-categories.index'],
    'catering attendance' => ['catering-attendance.index'],
]);
