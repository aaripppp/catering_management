<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

function sidebarNavigationMarkup(TestResponse $response): string
{
    preg_match('/<nav\b.*?<\/nav>/s', $response->getContent(), $matches);

    return html_entity_decode($matches[0] ?? '', ENT_QUOTES | ENT_HTML5);
}

function sidebarNavigationLink(string $markup, string $href): string
{
    preg_match('/<a\b[^>]*href="'.preg_quote($href, '/').'"[^>]*>.*?<\/a>/s', $markup, $matches);

    return $matches[0] ?? '';
}

it('renders the administrator catering navigation in the requested order with unchanged routes', function () {
    $response = $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'));

    $markup = sidebarNavigationMarkup($response);
    $items = [
        'Absensi Siswa' => route('catering-attendance.index'),
        'Absensi Unit' => route('catering-unit-attendance.index'),
        'Tagihan Siswa' => route('catering-bills.index'),
        'Tagihan Unit' => route('catering-unit-bills.index'),
        'Budaya Makan' => route('catering-culture-meal.index'),
        'Rekap Absensi' => route('catering-attendance-recap.index'),
        'Laporan' => route('catering-monthly-report.index'),
    ];
    $previousPosition = -1;

    foreach ($items as $label => $href) {
        $position = strpos($markup, $label);

        expect($position)->toBeInt()->toBeGreaterThan($previousPosition)
            ->and(sidebarNavigationLink($markup, $href))->toContain('>'.$label.'</span>');

        $previousPosition = $position;
    }

    expect($markup)->not->toContain('Absensi Catering', 'Tagihan Bulanan');
});

it('preserves the limited catering navigation for wali kelas users', function () {
    $response = $this->actingAs(User::factory()->waliKelas()->create())->get(route('dashboard'));

    $markup = sidebarNavigationMarkup($response);

    expect($markup)
        ->toContain('Absensi Siswa', 'Rekap Absensi')
        ->not->toContain('Absensi Unit', 'Tagihan Siswa', 'Tagihan Unit', 'Budaya Makan', 'Laporan');
});

it('keeps the catering navigation active state for :label', function (string $label, string $routeName) {
    $response = $this->actingAs(User::factory()->admin()->create())->get(route($routeName));

    $link = sidebarNavigationLink(sidebarNavigationMarkup($response), route($routeName));

    expect($link)
        ->toContain('aria-current="page"', 'sidebar-link-active', '>'.$label.'</span>');
})->with([
    'student attendance' => ['Absensi Siswa', 'catering-attendance.index'],
    'unit attendance' => ['Absensi Unit', 'catering-unit-attendance.index'],
    'student billing' => ['Tagihan Siswa', 'catering-bills.index'],
    'unit billing' => ['Tagihan Unit', 'catering-unit-bills.index'],
    'culture meal' => ['Budaya Makan', 'catering-culture-meal.index'],
    'attendance recap' => ['Rekap Absensi', 'catering-attendance-recap.index'],
    'monthly report' => ['Laporan', 'catering-monthly-report.index'],
]);
