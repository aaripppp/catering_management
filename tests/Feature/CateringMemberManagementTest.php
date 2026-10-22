<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringBillStatus;
use App\Enums\Gender;
use App\Livewire\CateringMembers\Index as CateringMemberIndex;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCredit;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringMembershipService;
use App\Services\CateringPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function storeMemberManagementAttendance(CateringMember $member, array $days, int $month = 10): void
{
    CateringAttendance::query()->insert(collect($days)->map(fn (int $day): array => [
        'catering_member_id' => $member->id,
        'attendance_date' => CarbonImmutable::create(2026, $month, $day)->toDateString(),
        'status' => CateringAttendanceStatus::Ikut->value,
        'created_at' => now(),
        'updated_at' => now(),
    ])->all());
}

it('redirects guests away from the catering members page', function () {
    $this->get(route('catering-members.index'))->assertRedirect(route('login'));
});

it('forbids wali kelas from the catering members page', function () {
    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-members.index'))
        ->assertForbidden();
});

it('allows an admin to open the catering members page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-members.index'))
        ->assertOk();
});

it('creates a catering member linked to a class and a category', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create();
    $category = CateringCategory::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Ahmad Fauzi')
        ->set('school_class_id', (string) $schoolClass->id)
        ->set('catering_category_id', (string) $category->id)
        ->set('gender', Gender::Male->value)
        ->set('phone', '081234567890')
        ->set('mulai_catering', '2026-10-20')
        ->call('save')
        ->assertHasNoErrors();

    $member = CateringMember::query()->where('name', 'Ahmad Fauzi')->firstOrFail();
    $period = $member->periods()->sole();

    expect($member->school_class_id)->toBe($schoolClass->id)
        ->and($member->catering_category_id)->toBe($category->id)
        ->and($member->gender)->toBe(Gender::Male)
        ->and($member->phone)->toBe('081234567890')
        ->and($period->start_date->toDateString())->toBe('2026-10-20')
        ->and($period->end_date)->toBeNull();
});

it('rolls back a new member when its initial membership period cannot be created', function () {
    $category = CateringCategory::factory()->create();
    $originalDispatcher = clone CateringMemberPeriod::getEventDispatcher();

    CateringMemberPeriod::creating(function (): never {
        throw new RuntimeException('Simulated period failure.');
    });

    try {
        expect(fn () => Livewire::actingAs(User::factory()->admin()->create())
            ->test(CateringMemberIndex::class)
            ->call('create')
            ->set('name', 'Rollback Member')
            ->set('catering_category_id', (string) $category->id)
            ->set('mulai_catering', '2026-10-20')
            ->call('save'))
            ->toThrow(RuntimeException::class, 'Simulated period failure.');
    } finally {
        CateringMemberPeriod::setEventDispatcher($originalDispatcher);
    }

    expect(CateringMember::query()->where('name', 'Rollback Member')->exists())->toBeFalse()
        ->and(CateringMemberPeriod::query()->count())->toBe(0);
});

it('accepts past current and future initial catering dates', function (string $startDate) {
    $category = CateringCategory::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Date '.$startDate)
        ->set('catering_category_id', (string) $category->id)
        ->set('mulai_catering', $startDate)
        ->call('save')
        ->assertHasNoErrors();

    $member = CateringMember::query()->where('name', 'Date '.$startDate)->sole();

    expect($member->periods()->sole()->start_date->toDateString())->toBe($startDate);
})->with([
    'past date' => ['2026-10-01'],
    'current date' => ['2026-10-17'],
    'future date' => ['2026-11-17'],
]);

it('rejects an invalid initial catering date', function () {
    $category = CateringCategory::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Invalid Date Member')
        ->set('catering_category_id', (string) $category->id)
        ->set('mulai_catering', 'not-a-date')
        ->call('save')
        ->assertHasErrors(['mulai_catering' => 'date']);

    expect(CateringMember::query()->where('name', 'Invalid Date Member')->exists())->toBeFalse();
});

it('creates a catering member without a class', function () {
    $admin = User::factory()->admin()->create();
    $category = CateringCategory::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Tanpa Kelas')
        ->set('school_class_id', '')
        ->set('catering_category_id', (string) $category->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(CateringMember::where('name', 'Tanpa Kelas')->firstOrFail()->school_class_id)->toBeNull();
});

it('requires a name and a catering category when creating a member', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', '')
        ->set('catering_category_id', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'catering_category_id' => 'required']);
});

it('rejects an unknown gender value', function () {
    $category = CateringCategory::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('create')
        ->set('name', 'Salah Gender')
        ->set('catering_category_id', (string) $category->id)
        ->set('gender', 'X')
        ->call('save')
        ->assertHasErrors(['gender']);
});

it('updates a catering member', function () {
    $admin = User::factory()->admin()->create();
    $member = CateringMember::factory()->create(['name' => 'Ahmad Fauzi']);
    $category = CateringCategory::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('edit', ['id' => $member->id])
        ->set('name', 'Ahmad Fauzi Jr')
        ->set('catering_category_id', (string) $category->id)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('catering_members', [
        'id' => $member->id,
        'name' => 'Ahmad Fauzi Jr',
        'catering_category_id' => $category->id,
    ]);
});

it('does not change membership periods while editing participant details', function () {
    $member = CateringMember::factory()->create(['name' => 'Ahmad Fauzi', 'is_active' => true]);
    $period = CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-20',
        'end_date' => null,
    ]);
    $category = CateringCategory::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('edit', ['id' => $member->id])
        ->set('name', 'Ahmad Fauzi Jr')
        ->set('catering_category_id', (string) $category->id)
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    $period->refresh();

    expect($member->fresh()->is_active)->toBeFalse()
        ->and($member->fresh()->name)->toBe('Ahmad Fauzi Jr')
        ->and($member->periods()->count())->toBe(1)
        ->and($period->start_date->toDateString())->toBe('2026-10-20')
        ->and($period->end_date)->toBeNull();
});

it('stops catering on the inclusive last participation date', function () {
    $member = CateringMember::factory()->create(['name' => 'Stop Member']);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openStopModal', $member->id)
        ->assertSet('showStopModal', true)
        ->assertSee('Tanggal Terakhir Ikut Catering')
        ->set('tanggal_terakhir', '2026-10-17')
        ->call('stopCatering')
        ->assertHasNoErrors();

    $membership = app(CateringMembershipService::class);

    expect($member->periods()->sole()->end_date->toDateString())->toBe('2026-10-17')
        ->and($membership->isCovered($member->id, CarbonImmutable::parse('2026-10-17')))->toBeTrue()
        ->and($membership->isCovered($member->id, CarbonImmutable::parse('2026-10-18')))->toBeFalse();
});

it('automatically reduces an existing bill through the inclusive stop date without deleting attendance', function () {
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 20000]);
    $member = CateringMember::factory()->create(['catering_category_id' => $category->id]);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);
    storeMemberManagementAttendance($member, range(1, 20));
    $bill = CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'school_class_id' => $member->school_class_id,
        'catering_category_id' => $category->id,
        'member_name' => $member->name,
        'category_name' => $category->name,
        'price_per_day' => 20000,
        'saved_days' => 20,
        'active_days' => 20,
        'gross_amount' => 400000,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openStopModal', $member->id)
        ->set('tanggal_terakhir', '2026-10-15')
        ->call('stopCatering')
        ->assertHasNoErrors();

    $bill->refresh();

    expect($bill->saved_days)->toBe(15)
        ->and($bill->active_days)->toBe(15)
        ->and($bill->gross_amount)->toBe(300000);
    expect(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(20)
        ->and(CateringAttendance::query()->where('catering_member_id', $member->id)->whereDate('attendance_date', '2026-10-15')->exists())->toBeTrue()
        ->and(CateringAttendance::query()->where('catering_member_id', $member->id)->whereDate('attendance_date', '>', '2026-10-15')->count())->toBe(5);
});

it('reconciles credit after stopping reduces a paid bill without changing its payment', function () {
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 20000]);
    $member = CateringMember::factory()->create(['catering_category_id' => $category->id]);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);
    storeMemberManagementAttendance($member, range(1, 20));
    $bill = CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'school_class_id' => $member->school_class_id,
        'catering_category_id' => $category->id,
        'member_name' => $member->name,
        'category_name' => $category->name,
        'price_per_day' => 20000,
        'saved_days' => 20,
        'active_days' => 20,
        'gross_amount' => 400000,
    ]);
    $payment = app(CateringPaymentService::class)->record($bill, 400000);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openStopModal', $member->id)
        ->set('tanggal_terakhir', '2026-10-15')
        ->call('stopCatering')
        ->assertHasNoErrors();

    $bill->refresh();
    $credit = CateringCredit::query()->sole();

    expect($bill->gross_amount)->toBe(300000)
        ->and($bill->paidAmount())->toBe(400000)
        ->and($bill->payment_status)->toBe(CateringBillStatus::Paid);
    expect($payment->fresh()->amount)->toBe(400000)
        ->and(CateringPayment::query()->count())->toBe(1);
    expect($credit->source_payment_id)->toBe($payment->id)
        ->and($credit->source_bill_id)->toBe($bill->id)
        ->and($credit->original_amount)->toBe(100000)
        ->and($credit->remaining_amount)->toBe(100000);
});

it('does not create a future bill while automatically synchronizing a stop', function () {
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 20000]);
    $member = CateringMember::factory()->create(['catering_category_id' => $category->id]);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => null,
    ]);
    storeMemberManagementAttendance($member, range(1, 20));
    storeMemberManagementAttendance($member, range(1, 10), month: 11);
    CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'school_class_id' => $member->school_class_id,
        'catering_category_id' => $category->id,
        'period_month' => 10,
        'period_year' => 2026,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openStopModal', $member->id)
        ->set('tanggal_terakhir', '2026-10-15')
        ->call('stopCatering')
        ->assertHasNoErrors();

    expect(CateringBill::query()->where('catering_member_id', $member->id)->count())->toBe(1)
        ->and(CateringBill::query()->where('catering_member_id', $member->id)->where('period_year', 2026)->where('period_month', 11)->doesntExist())->toBeTrue();
});

it('rejects stopping catering before the open period starts', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-20',
        'end_date' => null,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openStopModal', $member->id)
        ->set('tanggal_terakhir', '2026-10-19')
        ->call('stopCatering')
        ->assertHasErrors(['membership']);

    expect($member->periods()->sole()->end_date)->toBeNull();
});

it('rejects stopping a participant without an open membership period', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-17',
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openStopModal', $member->id)
        ->assertHasErrors(['membership'])
        ->assertSet('showStopModal', false);

    expect($member->periods()->sole()->end_date->toDateString())->toBe('2026-10-17');
});

it('rejoins by creating a new open period while preserving history', function () {
    $member = CateringMember::factory()->create(['name' => 'Rejoin Member']);
    $oldPeriod = CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-17',
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openRejoinModal', $member->id)
        ->assertSet('showRejoinModal', true)
        ->assertSee('Mulai Catering')
        ->set('mulai_catering_lagi', '2026-11-17')
        ->call('rejoinCatering')
        ->assertHasNoErrors();

    $periods = $member->periods()->orderBy('start_date')->get();

    expect($periods)->toHaveCount(2)
        ->and($oldPeriod->fresh()->end_date->toDateString())->toBe('2026-10-17')
        ->and($periods->last()->start_date->toDateString())->toBe('2026-11-17')
        ->and($periods->last()->end_date)->toBeNull();
});

it('automatically resynchronizes an existing boundary month bill from the rejoin date', function () {
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 20000]);
    $member = CateringMember::factory()->create(['catering_category_id' => $category->id]);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-15',
    ]);
    storeMemberManagementAttendance($member, range(1, 20), month: 11);
    $bill = CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'school_class_id' => $member->school_class_id,
        'catering_category_id' => $category->id,
        'member_name' => $member->name,
        'category_name' => $category->name,
        'price_per_day' => 20000,
        'period_month' => 11,
        'period_year' => 2026,
        'saved_days' => 0,
        'active_days' => 0,
        'gross_amount' => 0,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openRejoinModal', $member->id)
        ->set('mulai_catering_lagi', '2026-11-17')
        ->call('rejoinCatering')
        ->assertHasNoErrors();

    $bill->refresh();

    expect($bill->saved_days)->toBe(4)
        ->and($bill->active_days)->toBe(4)
        ->and($bill->gross_amount)->toBe(80000)
        ->and(CateringAttendance::query()->where('catering_member_id', $member->id)->count())->toBe(20);
});

it('rejects a rejoin date that overlaps historical membership', function () {
    $member = CateringMember::factory()->create();
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-17',
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openRejoinModal', $member->id)
        ->set('mulai_catering_lagi', '2026-10-17')
        ->call('rejoinCatering')
        ->assertHasErrors(['membership']);

    expect($member->periods()->count())->toBe(1);
});

it('renders compact membership history newest first', function () {
    $member = CateringMember::factory()->create(['name' => 'History Member']);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-17',
    ]);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $member->id,
        'start_date' => '2026-11-17',
        'end_date' => null,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->call('openHistoryModal', $member->id)
        ->assertSet('showHistoryModal', true)
        ->assertSee('Periode Keikutsertaan')
        ->assertSee('17 November 2026')
        ->assertSee('1 September 2026')
        ->assertSee('17 Oktober 2026')
        ->assertSee('Sekarang');
});

it('renders active catering status separately from the inactive master status', function () {
    $category = CateringCategory::factory()->create(['name' => 'Siswa Umum']);
    $participating = CateringMember::factory()->inactive()->create([
        'name' => 'Archived But Participating',
        'catering_category_id' => $category->id,
    ]);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $participating->id,
        'start_date' => '2026-11-17',
        'end_date' => null,
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->assertSee('Kelas / Penempatan')
        ->assertSee('Archived But Participating')
        ->assertSee('Nonaktif Peserta')
        ->assertSee('Aktif')
        ->assertSee('Sejak 17 November 2026')
        ->assertSee('Edit Peserta')
        ->assertSee('Riwayat Catering')
        ->assertSee('Berhenti Catering')
        ->assertSee('Hapus Peserta');

    $html = $component->html();

    expect(preg_match_all('/<th(?:\s|>)/', $html))->toBe(8)
        ->and($html)->toContain("toggleActionMenu({$participating->id}, true")
        ->and($html)->not->toMatch('/<th[^>]*>\s*Status Peserta\s*<\/th>/');
});

it('renders stopped catering status with the latest end date and stopped actions', function () {
    $stopped = CateringMember::factory()->create(['name' => 'Active But Stopped']);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $stopped->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);
    CateringMemberPeriod::query()->create([
        'catering_member_id' => $stopped->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-17',
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->assertSee('Active But Stopped')
        ->assertSee('Tidak Aktif')
        ->assertSee('Terakhir 17 Oktober 2026')
        ->assertSee('Edit Peserta')
        ->assertSee('Riwayat Catering')
        ->assertSee('Mulai Catering Lagi')
        ->assertSee('Hapus Peserta');

    expect($component->html())->toContain("toggleActionMenu({$stopped->id}, false");
});

it('renders a simple catering fallback for a participant without periods', function () {
    CateringMember::factory()->create(['name' => 'Belum Ikut Member']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->assertSee('Belum Ikut Member')
        ->assertSee('Belum pernah ikut');
});

it('keeps every category badge on one line', function (string $categoryName) {
    $category = CateringCategory::factory()->create(['name' => $categoryName]);
    CateringMember::factory()->create(['catering_category_id' => $category->id]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->assertSeeHtml('class="badge-info whitespace-nowrap">'.$categoryName.'</span>');
})->with([
    'student category' => ['Siswa Umum'],
    'teacher child category' => ['Anak Guru'],
    'long category' => ['Ea molestiae dengan nama panjang'],
]);

it('deletes a catering member', function () {
    $admin = User::factory()->admin()->create();
    $member = CateringMember::factory()->create();

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->call('confirmDelete', $member->id)
        ->assertSet('showDeleteModal', true)
        ->assertSee('Hapus Peserta?')
        ->call('deleteConfirmed');

    $this->assertDatabaseMissing('catering_members', ['id' => $member->id]);
});

it('forbids wali kelas from creating a catering member', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $this->actingAs($waliKelas);
    expect(Gate::denies('create', CateringMember::class))->toBeTrue();
});

it('forbids wali kelas from deleting a catering member', function () {
    $waliKelas = User::factory()->waliKelas()->create();
    $member = CateringMember::factory()->create();

    Livewire::actingAs($waliKelas)
        ->test(CateringMemberIndex::class)
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('delete', $member))->toBeTrue();

    $this->assertDatabaseHas('catering_members', ['id' => $member->id]);
});

it('filters the catering member list by search term', function () {
    $admin = User::factory()->admin()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi']);
    CateringMember::factory()->create(['name' => 'Dewi Lestari']);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('search', 'Dewi')
        ->assertSee('Dewi Lestari')
        ->assertDontSee('Ahmad Fauzi');
});

it('filters the catering member list by status', function () {
    $admin = User::factory()->admin()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi', 'is_active' => true]);
    CateringMember::factory()->inactive()->create(['name' => 'Dewi Lestari']);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('status', 'inactive')
        ->assertSee('Dewi Lestari')
        ->assertDontSee('Ahmad Fauzi');
});

it('filters the catering member list by school class', function () {
    $admin = User::factory()->admin()->create();
    $classA = SchoolClass::factory()->create();
    $classB = SchoolClass::factory()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi', 'school_class_id' => $classA->id]);
    CateringMember::factory()->create(['name' => 'Dewi Lestari', 'school_class_id' => $classB->id]);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('schoolClassId', (string) $classA->id)
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Dewi Lestari');
});

it('orders active class options by canonical level and natural class name', function () {
    foreach ([
        ['name' => '12B-IPS', 'level' => '12'],
        ['name' => '10A', 'level' => '10'],
        ['name' => '1B', 'level' => '1'],
        ['name' => 'KB-2', 'level' => 'KB'],
        ['name' => 'A-1', 'level' => 'TK A'],
        ['name' => 'Daycare A', 'level' => 'Daycare'],
        ['name' => '12A-IPS', 'level' => '12'],
        ['name' => '2A', 'level' => '2'],
        ['name' => 'B-1', 'level' => 'TK B'],
        ['name' => '12B-IPA', 'level' => '12'],
        ['name' => '7A', 'level' => '7'],
        ['name' => 'KB-1', 'level' => 'KB'],
        ['name' => '1A', 'level' => '1'],
        ['name' => '11A', 'level' => '11'],
        ['name' => '12A-IPA', 'level' => '12'],
    ] as $class) {
        SchoolClass::factory()->create($class);
    }

    SchoolClass::factory()->inactive()->create(['name' => 'Inactive 1A', 'level' => '1']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringMemberIndex::class)
        ->assertViewHas('classes', fn ($classes): bool => $classes->pluck('name')->all() === [
            'Daycare A',
            'KB-1',
            'KB-2',
            'A-1',
            'B-1',
            '1A',
            '1B',
            '2A',
            '7A',
            '10A',
            '11A',
            '12A-IPA',
            '12B-IPA',
            '12A-IPS',
            '12B-IPS',
        ]);
});

it('filters catering members by every jenjang', function () {
    $admin = User::factory()->admin()->create();
    $members = [];

    foreach ([
        'Daycare' => ['level' => 'Daycare', 'class' => 'Daycare A'],
        'TK' => ['level' => 'TK A', 'class' => 'A-1'],
        'SD' => ['level' => '4', 'class' => '4A'],
        'SMP' => ['level' => '8', 'class' => '8A'],
        'SMA' => ['level' => '11', 'class' => '11A'],
    ] as $jenjang => $classData) {
        $schoolClass = SchoolClass::factory()->create([
            'name' => $classData['class'],
            'level' => $classData['level'],
        ]);
        $memberName = $jenjang.' Member';
        CateringMember::factory()->create([
            'name' => $memberName,
            'school_class_id' => $schoolClass->id,
        ]);
        $members[$jenjang] = $memberName;
    }

    foreach ($members as $jenjang => $memberName) {
        $component = Livewire::actingAs($admin)
            ->test(CateringMemberIndex::class)
            ->set('jenjang', $jenjang)
            ->assertSee($memberName);

        foreach ($members as $otherJenjang => $otherMemberName) {
            if ($otherJenjang !== $jenjang) {
                $component->assertDontSee($otherMemberName);
            }
        }
    }
});

it('restricts class options by jenjang and resets an incompatible class filter', function () {
    $admin = User::factory()->admin()->create();
    $class7 = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    SchoolClass::factory()->create(['name' => '8A', 'level' => '8']);
    SchoolClass::factory()->create(['name' => 'A-1', 'level' => 'TK A']);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('schoolClassId', (string) $class7->id)
        ->set('jenjang', 'SMP')
        ->assertSet('schoolClassId', (string) $class7->id)
        ->assertViewHas('classes', fn ($classes): bool => $classes->pluck('name')->all() === ['7A', '8A'])
        ->set('jenjang', 'TK')
        ->assertSet('schoolClassId', '')
        ->assertViewHas('classes', fn ($classes): bool => $classes->pluck('name')->all() === ['A-1']);
});

it('keeps member pagination server-side and eager loads displayed relations', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create(['level' => '7']);
    $category = CateringCategory::factory()->create();
    $members = CateringMember::factory()->count(11)->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    $assignmentClass = SchoolClass::factory()->create(['level' => '8']);
    CateringEmployeeAssignment::factory()->schoolClass($assignmentClass)->create([
        'catering_member_id' => $members->last()->id,
    ]);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->assertViewHas('members', function ($members): bool {
            return $members->perPage() === 10
                && $members->count() === 10
                && $members->getCollection()->every(fn (CateringMember $member): bool => $member->relationLoaded('schoolClass')
                    && $member->relationLoaded('cateringCategory')
                    && $member->relationLoaded('employeeAssignment')
                    && $member->relationLoaded('periods')
                    && ($member->employeeAssignment === null || $member->employeeAssignment->relationLoaded('schoolClass')));
        })
        ->call('setPage', 2)
        ->set('jenjang', 'SMP')
        ->assertSet('paginators.page', 1);
});

it('filters the catering member list by catering category', function () {
    $admin = User::factory()->admin()->create();
    $categoryA = CateringCategory::factory()->create();
    $categoryB = CateringCategory::factory()->create();
    CateringMember::factory()->create(['name' => 'Ahmad Fauzi', 'catering_category_id' => $categoryA->id]);
    CateringMember::factory()->create(['name' => 'Dewi Lestari', 'catering_category_id' => $categoryB->id]);

    Livewire::actingAs($admin)
        ->test(CateringMemberIndex::class)
        ->set('cateringCategoryId', (string) $categoryA->id)
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Dewi Lestari');
});

it('does not store a price on the member record', function () {
    $member = CateringMember::factory()->create();

    expect(Schema::hasColumn('catering_members', 'price_per_day'))->toBeFalse()
        ->and(Schema::hasColumn('catering_categories', 'price_per_day'))->toBeTrue()
        ->and($member->getAttributes())->not->toHaveKey('price_per_day');
});

it('detaches the class from a member when the class is deleted', function () {
    $member = CateringMember::factory()->create();

    expect($member->school_class_id)->not->toBeNull();

    $member->schoolClass->delete();

    expect($member->fresh()->school_class_id)->toBeNull();
});
