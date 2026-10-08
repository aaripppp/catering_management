<?php

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Livewire\CateringBills\Index as CateringBillIndex;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCategory;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringMember;
use App\Models\CateringPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CateringBillingService;
use App\Services\CateringPaymentService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('protects the monthly billing page for administrators', function () {
    $this->get(route('catering-bills.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->waliKelas()->create())
        ->get(route('catering-bills.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-bills.index'))
        ->assertOk()
        ->assertSee('Tagihan Bulanan');
});

it('generates bills for the selected period and class', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 15000]);
    $member = CateringMember::factory()->create([
        'name' => 'Ahmad Zaki',
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    CateringAttendance::factory()->count(4)->sequence(
        fn ($sequence): array => [
            'attendance_date' => now()->startOfMonth()->addDays($sequence->index)->toDateString(),
            'status' => CateringAttendanceStatus::Ikut,
        ],
    )->for($member)->create();

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->set('participantGroup', CateringParticipantGroup::Student->value)
        ->set('schoolClassId', (string) $schoolClass->id)
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSee('Ahmad Zaki')
        ->assertDispatched('toast');

    $bill = CateringBill::query()->sole();

    expect($bill->active_days)->toBe(4)
        ->and($bill->gross_amount)->toBe(60000)
        ->and($bill->generated_by)->toBe($admin->id);
});

it('shows each category in its own column before the daily price without changing bill actions', function () {
    $admin = User::factory()->admin()->create();
    CateringBill::factory()->create([
        'member_name' => 'Ahmad Zaki',
        'category_name' => 'Siswa Umum',
        'period_month' => now()->month,
        'period_year' => now()->year,
        'price_per_day' => 15000,
        'active_days' => 4,
        'gross_amount' => 60000,
    ]);
    CateringBill::factory()->create([
        'member_name' => 'Budi Santoso',
        'category_name' => 'Anak Guru',
        'period_month' => now()->month,
        'period_year' => now()->year,
        'price_per_day' => 12000,
        'active_days' => 4,
        'gross_amount' => 48000,
    ]);

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->assertSeeInOrder(['Hari Aktif', 'Kategori', 'Harga/Hari'])
        ->assertSee('Siswa Umum')
        ->assertSee('Anak Guru')
        ->assertSee('Rp 15.000')
        ->assertSee('Rp 60.000')
        ->assertSee('Bayar')
        ->assertSee('Riwayat');
});

it('summarizes actual payments and outstanding balances for the selected scope', function () {
    $bills = collect([
        ['name' => 'A', 'gross' => 360000, 'paid' => 360000, 'status' => CateringBillStatus::Paid],
        ['name' => 'B', 'gross' => 247000, 'paid' => 200000, 'status' => CateringBillStatus::Partial],
        ['name' => 'C', 'gross' => 286000, 'paid' => 0, 'status' => CateringBillStatus::Unpaid],
        ['name' => 'D', 'gross' => 440000, 'paid' => 0, 'status' => CateringBillStatus::Unpaid],
    ])->map(function (array $data): CateringBill {
        $bill = CateringBill::factory()->create([
            'member_name' => $data['name'],
            'period_month' => 10,
            'period_year' => 2026,
            'gross_amount' => $data['gross'],
            'payment_status' => $data['status'],
        ]);

        if ($data['paid'] > 0) {
            CateringPayment::factory()->create([
                'catering_bill_id' => $bill->id,
                'catering_member_id' => $bill->catering_member_id,
                'amount' => $data['paid'],
            ]);
        }

        return $bill;
    });

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringBillIndex::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->bill_count === 4
            && (int) $summary->gross_amount === 1333000
            && (int) $summary->money_in === 560000
            && (int) $summary->credit_used === 0
            && (int) $summary->outstanding_amount === 773000)
        ->assertSeeInOrder([
            'Jumlah Tagihan',
            'Total Nilai Tagihan',
            'Jumlah Uang Masuk',
            'Kredit Digunakan',
            'Total Tunggakan',
            'Periode',
        ])
        ->assertSee('Rp 1.333.000')
        ->assertSee('Rp 560.000')
        ->assertSee('Rp 773.000')
        ->assertSee('Oktober 2026');

    expect($bills->sum(fn (CateringBill $bill): int => $bill->fresh()->remainingAmount()))->toBe(773000);
});

it('counts overpayments as money in without creating negative outstanding', function () {
    $bill = CateringBill::factory()->create([
        'period_month' => 10,
        'period_year' => 2026,
        'gross_amount' => 400000,
        'payment_status' => CateringBillStatus::Paid,
    ]);
    CateringPayment::factory()->create([
        'catering_bill_id' => $bill->id,
        'catering_member_id' => $bill->catering_member_id,
        'amount' => 300000,
    ]);
    CateringPayment::factory()->create([
        'catering_bill_id' => $bill->id,
        'catering_member_id' => $bill->catering_member_id,
        'amount' => 200000,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringBillIndex::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->money_in === 500000
            && (int) $summary->credit_used === 0
            && (int) $summary->outstanding_amount === 0);
});

it('displays generated overpayment separately from the remaining balance', function (int $paymentAmount, int $overpaymentAmount, int $remainingAmount) {
    $admin = User::factory()->admin()->create();
    $bill = CateringBill::factory()->create([
        'member_name' => 'Peserta Uji Lebih Bayar',
        'period_month' => now()->month,
        'period_year' => now()->year,
        'gross_amount' => 400000,
    ]);
    app(CateringPaymentService::class)->record($bill, $paymentAmount, $admin);

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->assertViewHas('bills', fn ($bills): bool => $bills->firstWhere('id', $bill->id)?->generatedCreditAmount() === $overpaymentAmount
            && $bills->firstWhere('id', $bill->id)?->remainingAmount() === $remainingAmount)
        ->assertSeeInOrder([
            'Terbayar',
            'Lebih Bayar',
            'Sisa',
        ])
        ->assertSee('Peserta Uji Lebih Bayar')
        ->assertSee('Rp '.number_format($overpaymentAmount, 0, ',', '.'));
})->with([
    'partially paid' => [300000, 0, 100000],
    'exactly paid' => [400000, 0, 0],
    'overpaid' => [500000, 100000, 0],
]);

it('reduces outstanding by applied credit without counting credit as new money in', function () {
    $member = CateringMember::factory()->create();
    $sourceBill = CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'period_month' => 9,
        'period_year' => 2026,
        'gross_amount' => 400000,
        'payment_status' => CateringBillStatus::Paid,
    ]);
    $targetBill = CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'period_month' => 10,
        'period_year' => 2026,
        'gross_amount' => 400000,
        'payment_status' => CateringBillStatus::Partial,
    ]);
    $sourcePayment = CateringPayment::factory()->create([
        'catering_bill_id' => $sourceBill->id,
        'catering_member_id' => $member->id,
        'amount' => 500000,
    ]);
    $credit = CateringCredit::factory()->create([
        'catering_member_id' => $member->id,
        'source_payment_id' => $sourcePayment->id,
        'source_bill_id' => $sourceBill->id,
        'original_amount' => 100000,
        'remaining_amount' => 0,
    ]);
    CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $credit->id,
        'catering_bill_id' => $targetBill->id,
        'amount' => 100000,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringBillIndex::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->money_in === 0
            && (int) $summary->credit_used === 100000
            && (int) $summary->outstanding_amount === 300000);
});

it('keeps carried credit separate from cash in the period where it is applied', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 20000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);

    foreach ([[9, '2026-09-01'], [10, '2026-10-01']] as [$month, $startDate]) {
        CateringAttendance::factory()->count(20)->sequence(
            fn ($sequence): array => [
                'attendance_date' => CarbonImmutable::parse($startDate)->addDays($sequence->index)->toDateString(),
                'status' => CateringAttendanceStatus::Ikut,
            ],
        )->for($member)->create();

        app(CateringBillingService::class)->generate(
            2026,
            $month,
            CateringParticipantGroup::Student,
            $schoolClass,
            actor: $admin,
        );

        if ($month === 9) {
            $september = CateringBill::query()->where('period_month', 9)->sole();
            app(CateringPaymentService::class)->record($september, 500000, $admin);
        }
    }

    $october = CateringBill::query()->where('period_month', 10)->sole();

    expect($october->creditAppliedAmount())->toBe(100000)
        ->and($october->generatedCreditAmount())->toBe(0)
        ->and($october->remainingAmount())->toBe(300000)
        ->and(CateringCredit::query()->sole()->original_amount)->toBe(100000);

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->set('month', 9)
        ->set('year', 2026)
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->money_in === 500000
            && (int) $summary->credit_used === 0
            && (int) $summary->outstanding_amount === 0);

    app(CateringPaymentService::class)->record($october, 300000, $admin);

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->assertViewHas('bills', fn ($bills): bool => $bills->firstWhere('id', $october->id)?->creditAppliedAmount() === 100000
            && $bills->firstWhere('id', $october->id)?->generatedCreditAmount() === 0)
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->gross_amount === 400000
            && (int) $summary->money_in === 300000
            && (int) $summary->credit_used === 100000
            && (int) $summary->outstanding_amount === 0)
        ->assertSeeInOrder([
            'Jumlah Uang Masuk',
            'Kredit Digunakan',
            'Total Tunggakan',
        ])
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 100.000');

    expect($october->fresh()->payment_status)->toBe(CateringBillStatus::Paid)
        ->and(CateringCreditAllocation::query()->count())->toBe(1)
        ->and(CateringCreditAllocation::query()->sum('amount'))->toBe(100000);
});

it('applies period group jenjang and class filters to every summary metric', function () {
    $smpClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $sdClass = SchoolClass::factory()->create(['name' => '5A', 'level' => '5']);
    $smpBill = CateringBill::factory()->create([
        'period_month' => 10,
        'period_year' => 2026,
        'school_class_id' => $smpClass->id,
        'school_level' => '7',
        'gross_amount' => 100000,
    ]);
    $sdBill = CateringBill::factory()->create([
        'period_month' => 10,
        'period_year' => 2026,
        'school_class_id' => $sdClass->id,
        'school_level' => '5',
        'gross_amount' => 200000,
    ]);
    $employeeBill = CateringBill::factory()->create([
        'period_month' => 10,
        'period_year' => 2026,
        'participant_group' => CateringParticipantGroup::Employee,
        'school_class_id' => null,
        'school_class_name' => null,
        'school_level' => null,
        'gross_amount' => 300000,
    ]);
    $otherPeriodBill = CateringBill::factory()->create([
        'period_month' => 9,
        'period_year' => 2026,
        'gross_amount' => 900000,
    ]);

    foreach ([[$smpBill, 40000], [$sdBill, 50000], [$employeeBill, 60000], [$otherPeriodBill, 900000]] as [$bill, $amount]) {
        CateringPayment::factory()->create([
            'catering_bill_id' => $bill->id,
            'catering_member_id' => $bill->catering_member_id,
            'amount' => $amount,
        ]);
    }

    foreach ([[$smpBill, 10000], [$sdBill, 20000], [$employeeBill, 30000]] as [$bill, $amount]) {
        $sourceBill = CateringBill::factory()->create([
            'catering_member_id' => $bill->catering_member_id,
            'period_month' => 9,
            'period_year' => 2026,
            'participant_group' => $bill->participant_group,
        ]);
        $sourcePayment = CateringPayment::factory()->create([
            'catering_bill_id' => $sourceBill->id,
            'catering_member_id' => $bill->catering_member_id,
            'amount' => $amount,
        ]);
        $credit = CateringCredit::factory()->create([
            'catering_member_id' => $bill->catering_member_id,
            'source_payment_id' => $sourcePayment->id,
            'source_bill_id' => $sourceBill->id,
            'original_amount' => $amount,
            'remaining_amount' => 0,
        ]);
        CateringCreditAllocation::factory()->create([
            'catering_credit_id' => $credit->id,
            'catering_bill_id' => $bill->id,
            'amount' => $amount,
        ]);
    }

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(CateringBillIndex::class)
        ->set('month', 10)
        ->set('year', 2026)
        ->set('jenjang', 'SMP')
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->gross_amount === 100000
            && (int) $summary->money_in === 40000
            && (int) $summary->credit_used === 10000
            && (int) $summary->outstanding_amount === 50000)
        ->set('schoolClassId', (string) $smpClass->id)
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->bill_count === 1
            && (int) $summary->money_in === 40000
            && (int) $summary->credit_used === 10000);

    $component
        ->set('participantGroup', CateringParticipantGroup::Employee->value)
        ->assertViewHas('summary', fn (object $summary): bool => (int) $summary->gross_amount === 300000
            && (int) $summary->money_in === 60000
            && (int) $summary->credit_used === 30000
            && (int) $summary->outstanding_amount === 210000);
});

it('records partial and excess payments from the payment modal', function () {
    $admin = User::factory()->admin()->create();
    $bill = CateringBill::factory()->create([
        'period_month' => now()->month,
        'period_year' => now()->year,
        'gross_amount' => 100000,
        'payment_status' => CateringBillStatus::Unpaid,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->call('openPayment', $bill->id)
        ->set('paymentAmount', '40000')
        ->set('paymentNote', 'Transfer pertama')
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect($bill->fresh()->payment_status)->toBe(CateringBillStatus::Partial)
        ->and(CateringPayment::query()->count())->toBe(1);

    $component
        ->set('paymentAmount', '70000')
        ->call('recordPayment')
        ->assertHasNoErrors()
        ->assertSee('Riwayat Pembayaran');

    expect($bill->fresh()->payment_status)->toBe(CateringBillStatus::Paid)
        ->and(CateringPayment::query()->count())->toBe(2)
        ->and(CateringCredit::query()->sole()->original_amount)->toBe(10000);
});

it('defaults a new payment to the latest remaining amount after resync', function () {
    $admin = User::factory()->admin()->create();
    $schoolClass = SchoolClass::factory()->create(['name' => '7A', 'level' => '7']);
    $category = CateringCategory::factory()->student()->create(['price_per_day' => 20000]);
    $member = CateringMember::factory()->create([
        'school_class_id' => $schoolClass->id,
        'catering_category_id' => $category->id,
    ]);
    CateringAttendance::factory()->count(20)->sequence(
        fn ($sequence): array => [
            'attendance_date' => now()->startOfMonth()->addDays($sequence->index)->toDateString(),
            'status' => CateringAttendanceStatus::Ikut,
        ],
    )->for($member)->create();
    app(CateringBillingService::class)->generate(
        now()->year,
        now()->month,
        CateringParticipantGroup::Student,
        $schoolClass,
        actor: $admin,
    );
    $bill = CateringBill::query()->sole();
    app(CateringPaymentService::class)->record($bill, 400000, $admin);
    CateringAttendance::factory()->for($member)->create([
        'attendance_date' => now()->startOfMonth()->addDays(20)->toDateString(),
        'status' => CateringAttendanceStatus::Ikut,
    ]);
    app(CateringBillingService::class)->generate(
        now()->year,
        now()->month,
        CateringParticipantGroup::Student,
        $schoolClass,
        actor: $admin,
    );

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->call('openPayment', $bill->id)
        ->assertSet('paymentAmount', '20000')
        ->assertSet('paymentDate', now()->toDateString());
});

it('edits payment amount date and note from payment history', function () {
    $admin = User::factory()->admin()->create();
    $bill = CateringBill::factory()->create([
        'period_month' => now()->month,
        'period_year' => now()->year,
        'gross_amount' => 100000,
    ]);
    $payment = app(CateringPaymentService::class)->record($bill, 150000, $admin, 'Awal');

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->call('openPayment', $bill->id)
        ->call('editPayment', $payment->id)
        ->assertSet('editingPaymentId', $payment->id)
        ->set('paymentAmount', '70000')
        ->set('paymentDate', '2026-10-09')
        ->set('paymentNote', 'Dikoreksi')
        ->call('recordPayment')
        ->assertHasNoErrors()
        ->assertSet('editingPaymentId', null);

    expect($payment->fresh()->amount)->toBe(70000)
        ->and($payment->fresh()->paid_at->toDateString())->toBe('2026-10-09')
        ->and($payment->fresh()->note)->toBe('Dikoreksi')
        ->and($bill->fresh()->payment_status)->toBe(CateringBillStatus::Partial)
        ->and($bill->fresh()->remainingAmount())->toBe(30000)
        ->and(CateringPayment::query()->count())->toBe(1)
        ->and(CateringCredit::query()->count())->toBe(0);
});

it('deletes a payment from history and refreshes the financial state', function () {
    $admin = User::factory()->admin()->create();
    $bill = CateringBill::factory()->create([
        'period_month' => now()->month,
        'period_year' => now()->year,
        'gross_amount' => 100000,
    ]);
    $payment = app(CateringPaymentService::class)->record($bill, 40000, $admin);

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->call('openPayment', $bill->id)
        ->call('confirmDeletePayment', $payment->id)
        ->assertSet('showDeletePaymentModal', true)
        ->call('deletePayment')
        ->assertSet('showDeletePaymentModal', false);

    expect($payment->fresh())->toBeNull()
        ->and($bill->fresh()->paidAmount())->toBe(0)
        ->and($bill->fresh()->generatedCreditAmount())->toBe(0)
        ->and($bill->fresh()->remainingAmount())->toBe(100000)
        ->and($bill->fresh()->payment_status)->toBe(CateringBillStatus::Unpaid);
});

it('filters bills by payment status', function () {
    $admin = User::factory()->admin()->create();
    CateringBill::factory()->create([
        'member_name' => 'Belum Lunas',
        'period_month' => now()->month,
        'period_year' => now()->year,
        'payment_status' => CateringBillStatus::Unpaid,
    ]);
    CateringBill::factory()->create([
        'member_name' => 'Sudah Lunas',
        'period_month' => now()->month,
        'period_year' => now()->year,
        'payment_status' => CateringBillStatus::Paid,
    ]);

    Livewire::actingAs($admin)
        ->test(CateringBillIndex::class)
        ->set('paymentStatus', CateringBillStatus::Paid->value)
        ->assertSee('Sudah Lunas')
        ->assertDontSee('Belum Lunas');
});
