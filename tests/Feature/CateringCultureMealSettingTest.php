<?php

use App\Enums\CateringAttendanceStatus;
use App\Livewire\CateringCultureMeal\Index as CultureMealIndex;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCredit;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use App\Models\CateringSetting;
use App\Models\CateringUnitBill;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringCultureMealIncomeService;
use App\Services\CateringCultureMealMonitoringService;
use App\Services\CateringCultureMealSettingService;
use Database\Seeders\CateringSettingSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function settingService(): CateringCultureMealSettingService
{
    return app(CateringCultureMealSettingService::class);
}

function settingCulturePage(User $user)
{
    return Livewire::actingAs($user)
        ->test(CultureMealIndex::class)
        ->set('month', 10)
        ->set('year', 2026);
}

function settingCultureAttendance(): void
{
    $member = CateringMember::factory()
        ->for(CateringCategory::factory()->student())
        ->create();
    CateringMemberPeriod::create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => '2026-10-10',
        'status' => CateringAttendanceStatus::Alfa,
    ]);
}

it('defaults the culture meal price to 16000', function () {
    expect(settingService()->getCultureMealPrice())->toBe(16000);

    $this->seed(CateringSettingSeeder::class);

    expect(CateringSetting::query()->where('key', 'culture_meal_price')->value('value'))->toBe('16000')
        ->and(settingService()->getCultureMealPrice())->toBe(16000);
});

it('displays the reference price on the Budaya Makan page as Rp 16.000', function () {
    $this->seed(CateringSettingSeeder::class);

    settingCulturePage(User::factory()->admin()->create())
        ->assertSee('Harga Budaya Makan')
        ->assertSee('Rp 16.000')
        ->assertSee('Edit Harga');
});

it('lets an admin edit the culture meal price', function () {
    $this->seed(CateringSettingSeeder::class);

    settingCulturePage(User::factory()->admin()->create())
        ->call('editCultureMealPrice')
        ->assertSet('showPriceModal', true)
        ->assertSet('culture_meal_price', '16000')
        ->set('culture_meal_price', '17000')
        ->call('saveCultureMealPrice')
        ->assertHasNoErrors()
        ->assertSet('showPriceModal', false)
        ->assertSee('Rp 17.000');
});

it('persists the edited price across reloads', function () {
    $admin = User::factory()->admin()->create();

    settingCulturePage($admin)
        ->call('editCultureMealPrice')
        ->set('culture_meal_price', '17500')
        ->call('saveCultureMealPrice')
        ->assertHasNoErrors();

    expect(settingService()->getCultureMealPrice())->toBe(17500)
        ->and(CateringSetting::query()->where('key', 'culture_meal_price')->value('value'))->toBe('17500');

    settingCulturePage($admin)->assertSee('Rp 17.500');
});

it('does not reset an edited price when the seeder runs again', function () {
    $this->seed(CateringSettingSeeder::class);

    settingService()->updateCultureMealPrice(19000);
    $this->seed(CateringSettingSeeder::class);

    expect(settingService()->getCultureMealPrice())->toBe(19000);
});

it('rejects an invalid price', function () {
    settingCulturePage(User::factory()->admin()->create())
        ->call('editCultureMealPrice')
        ->set('culture_meal_price', '0')
        ->call('saveCultureMealPrice')
        ->assertHasErrors(['culture_meal_price']);

    expect(settingService()->getCultureMealPrice())->toBe(16000);
});

it('blocks non-admins from updating the price', function () {
    $this->get(route('catering-culture-meal.index'))->assertRedirect(route('login'));

    $waliKelas = User::factory()->waliKelas()->create();

    $this->actingAs($waliKelas)
        ->get(route('catering-culture-meal.index'))
        ->assertForbidden();

    Livewire::actingAs($waliKelas)
        ->test(CultureMealIndex::class)
        ->assertForbidden();

    $this->actingAs($waliKelas);

    expect(Gate::denies('updateCateringCultureMeal'))->toBeTrue();

    $this->seed(CateringSettingSeeder::class);

    expect(settingService()->getCultureMealPrice())->toBe(16000);
});

it('does not change Data Tercatat when the price changes', function () {
    settingCultureAttendance();

    $monitoring = app(CateringCultureMealMonitoringService::class);
    $before = $monitoring->monitor(10, 2026)['total'];

    settingCulturePage(User::factory()->admin()->create())
        ->call('editCultureMealPrice')
        ->set('culture_meal_price', '17000')
        ->call('saveCultureMealPrice')
        ->assertHasNoErrors();

    expect($before)->toBe(1)
        ->and($monitoring->monitor(10, 2026)['total'])->toBe(1)
        ->and(CateringAttendance::count())->toBe(1);
});

it('does not change existing manual income when the price changes', function () {
    $admin = User::factory()->admin()->create();
    $class = SchoolClass::factory()->create();

    $income = app(CateringCultureMealIncomeService::class)->create([
        'income_date' => '2026-10-10',
        'school_class_id' => $class->id,
        'amount' => 100000,
        'note' => 'Pemasukan lama',
    ], $admin);

    settingCulturePage($admin)
        ->call('editCultureMealPrice')
        ->set('culture_meal_price', '17000')
        ->call('saveCultureMealPrice')
        ->assertHasNoErrors();

    $income->refresh();

    expect($income->amount)->toBe(100000)
        ->and(app(CateringCultureMealIncomeService::class)->monthlyTotal(10, 2026))->toBe(100000);
});

it('does not create billing, payment, or invoice side effects when the price changes', function () {
    CateringBill::factory()->create();
    CateringUnitBill::factory()->create();
    CateringPayment::factory()->create();
    CateringCredit::factory()->create();

    $before = [
        'bills' => CateringBill::count(),
        'unit_bills' => CateringUnitBill::count(),
        'payments' => CateringPayment::count(),
        'credits' => CateringCredit::count(),
    ];

    settingCulturePage(User::factory()->admin()->create())
        ->call('editCultureMealPrice')
        ->set('culture_meal_price', '18000')
        ->call('saveCultureMealPrice')
        ->assertHasNoErrors();

    expect([
        'bills' => CateringBill::count(),
        'unit_bills' => CateringUnitBill::count(),
        'payments' => CateringPayment::count(),
        'credits' => CateringCredit::count(),
    ])->toBe($before);
});

it('formats the price as Indonesian Rupiah while storing an integer', function () {
    settingService()->updateCultureMealPrice(17500);

    settingCulturePage(User::factory()->admin()->create())
        ->assertSee('Rp 17.500');

    expect(CateringSetting::query()->where('key', 'culture_meal_price')->value('value'))->toBe('17500')
        ->and(settingService()->getCultureMealPrice())->toBe(17500);
});

it('renders the price input with an unrestricted integer step', function () {
    settingCulturePage(User::factory()->admin()->create())
        ->call('editCultureMealPrice')
        ->assertSeeHtml('id="price-amount" type="number" inputmode="numeric" min="1" step="1"')
        ->assertDontSeeHtml('id="price-amount" type="number" inputmode="numeric" min="1" step="1000"');
});
