<?php

use App\Enums\CateringUnitBillSyncStatus;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitBillDay;
use App\Models\User;
use App\Services\CateringUnitBillingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function createUnitBillingAttendance(
    CateringEmployeeUnit $unit,
    string $date,
    int $quantity,
    int $price,
): CateringUnitAttendance {
    return CateringUnitAttendance::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'attendance_date' => $date,
        'quantity' => $quantity,
        'price_per_person_snapshot' => $price,
    ]);
}

it('synchronizes a monthly bill and daily snapshots from attendance', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    $actor = User::factory()->admin()->create();
    createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    createUnitBillingAttendance($unit, '2026-10-02', 12, 13000);
    $service = app(CateringUnitBillingService::class);

    expect($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Unsynced);

    $bills = $service->syncPeriod(10, 2026, $actor->id);

    expect($bills)->toHaveCount(1);
    expect($bills->first())
        ->unit_name->toBe('Daycare')
        ->total_quantity->toBe(22)
        ->price_per_person_snapshot->toBe(13000)
        ->gross_amount->toBe(286000)
        ->generated_by->toBe($actor->id);
    expect($bills->first()->days)->toHaveCount(2)
        ->and($bills->first()->days->pluck('subtotal')->all())->toBe([130000, 156000]);
});

it('synchronizes idempotently without duplicate bills or days', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    createUnitBillingAttendance($unit, '2026-10-02', 12, 13000);
    $service = app(CateringUnitBillingService::class);

    $firstBill = $service->syncPeriod(10, 2026)->first();
    $secondBill = $service->syncPeriod(10, 2026)->first();

    expect($secondBill->id)->toBe($firstBill->id)
        ->and(CateringUnitBill::query()->count())->toBe(1)
        ->and(CateringUnitBillDay::query()->count())->toBe(2)
        ->and($secondBill->gross_amount)->toBe(286000);
});

it('marks quantity changes stale and updates the same bill on resync', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $attendance = createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    $service = app(CateringUnitBillingService::class);
    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    $attendance->update(['quantity' => 8]);

    expect($bill->fresh()->total_quantity)->toBe(10)
        ->and($bill->fresh()->gross_amount)->toBe(130000)
        ->and($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Stale);

    $resynced = $service->syncUnitPeriod($unit->id, 10, 2026);

    expect($resynced->id)->toBe($bill->id)
        ->and($resynced->total_quantity)->toBe(8)
        ->and($resynced->gross_amount)->toBe(104000)
        ->and($resynced->days->first()->quantity)->toBe(8)
        ->and($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Synced);
});

it('detects added and deleted source rows and exactly replaces bill days', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $first = createUnitBillingAttendance($unit, '2026-10-01', 5, 13000);
    $service = app(CateringUnitBillingService::class);
    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    $second = createUnitBillingAttendance($unit, '2026-10-02', 7, 13000);

    expect($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Stale);

    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    expect($bill->days)->toHaveCount(2)
        ->and($bill->total_quantity)->toBe(12);

    $first->delete();

    expect($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Stale);

    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    expect($bill->days)->toHaveCount(1)
        ->and($bill->days->first()->attendance_date->toDateString())->toBe('2026-10-02')
        ->and($bill->total_quantity)->toBe(7)
        ->and($bill->gross_amount)->toBe(91000);
    expect($second->fresh())->not->toBeNull();
});

it('preserves confirmed zero rows but creates no bill when source is missing', function () {
    $zeroUnit = CateringEmployeeUnit::factory()->create();
    $missingUnit = CateringEmployeeUnit::factory()->create();
    createUnitBillingAttendance($zeroUnit, '2026-10-01', 0, 13000);
    $service = app(CateringUnitBillingService::class);

    $zeroBill = $service->syncUnitPeriod($zeroUnit->id, 10, 2026);
    $missingBill = $service->syncUnitPeriod($missingUnit->id, 10, 2026);

    expect($zeroBill)->not->toBeNull()
        ->and($zeroBill->total_quantity)->toBe(0)
        ->and($zeroBill->gross_amount)->toBe(0)
        ->and($zeroBill->days)->toHaveCount(1)
        ->and($zeroBill->days->first()->quantity)->toBe(0)
        ->and($zeroBill->days->first()->subtotal)->toBe(0)
        ->and($missingBill)->toBeNull()
        ->and(CateringUnitBill::query()->count())->toBe(1);
});

it('uses historical attendance prices after the master price changes', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 13000]);
    $attendance = createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);

    $unit->update(['price_per_person' => 15000]);
    $attendance->update(['quantity' => 11]);
    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 10, 2026);

    expect($attendance->fresh()->price_per_person_snapshot)->toBe(13000)
        ->and($bill->price_per_person_snapshot)->toBe(13000)
        ->and($bill->gross_amount)->toBe(143000)
        ->and($bill->days->first()->price_per_person_snapshot)->toBe(13000);
});

it('supports mixed historical rates without storing a false header rate', function () {
    $unit = CateringEmployeeUnit::factory()->create(['price_per_person' => 15000]);
    createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    $second = createUnitBillingAttendance($unit, '2026-10-02', 10, 14000);
    $service = app(CateringUnitBillingService::class);

    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    expect($bill->total_quantity)->toBe(20)
        ->and($bill->gross_amount)->toBe(270000)
        ->and($bill->price_per_person_snapshot)->toBeNull()
        ->and($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Synced);

    $second->update(['price_per_person_snapshot' => 15000]);

    expect($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Stale);
});

it('treats a unit name change as stale until explicit resynchronization', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'TU']);
    createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    $service = app(CateringUnitBillingService::class);
    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    $unit->update(['name' => 'Tata Usaha']);

    expect($bill->fresh()->unit_name)->toBe('TU')
        ->and($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Stale);

    $resynced = $service->syncUnitPeriod($unit->id, 10, 2026);

    expect($resynced->unit_name)->toBe('Tata Usaha')
        ->and($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Synced);
});

it('retains inactive unit history and allows it to be resynchronized', function () {
    $unit = CateringEmployeeUnit::factory()->create(['is_active' => true]);
    $attendance = createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    $service = app(CateringUnitBillingService::class);
    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    $unit->update(['is_active' => false]);
    $attendance->update(['quantity' => 9]);
    $resynced = $service->syncPeriod(10, 2026)->first();

    expect($resynced->id)->toBe($bill->id)
        ->and($resynced->total_quantity)->toBe(9)
        ->and($unit->fresh()->unitAttendances()->count())->toBe(1)
        ->and($unit->fresh()->unitBills()->count())->toBe(1);
});

it('keeps an existing bill as an empty snapshot when all source rows are removed', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $attendance = createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    $service = app(CateringUnitBillingService::class);
    $bill = $service->syncUnitPeriod($unit->id, 10, 2026);

    $attendance->delete();

    expect($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Stale);

    $resynced = $service->syncPeriod(10, 2026)->first();

    expect($resynced->id)->toBe($bill->id)
        ->and($resynced->total_quantity)->toBe(0)
        ->and($resynced->gross_amount)->toBe(0)
        ->and($resynced->price_per_person_snapshot)->toBeNull()
        ->and($resynced->days)->toHaveCount(0)
        ->and($service->periodStatus(10, 2026)->first()['status'])->toBe(CateringUnitBillSyncStatus::Synced);
});

it('rolls back the bill header and days when daily replacement fails', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $attendance = createUnitBillingAttendance($unit, '2026-10-01', 10, 13000);
    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 10, 2026);
    $attendance->update(['quantity' => 8]);
    $failingService = new class extends CateringUnitBillingService
    {
        protected function replaceBillDays(array $billIds, array $daySnapshots): void
        {
            parent::replaceBillDays($billIds, $daySnapshots);

            throw new RuntimeException('Forced bill-day replacement failure.');
        }
    };

    expect(fn () => $failingService->syncUnitPeriod($unit->id, 10, 2026))
        ->toThrow(RuntimeException::class, 'Forced bill-day replacement failure.');

    expect($bill->fresh()->total_quantity)->toBe(10)
        ->and($bill->fresh()->gross_amount)->toBe(130000)
        ->and($bill->fresh()->days)->toHaveCount(1)
        ->and($bill->fresh()->days->first()->quantity)->toBe(10);
});

it('enforces one bill per unit and period at the database level', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    CateringUnitBill::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'period_month' => 10,
        'period_year' => 2026,
    ]);

    expect(fn () => CateringUnitBill::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'period_month' => 10,
        'period_year' => 2026,
    ]))->toThrow(QueryException::class);
});

it('enforces one daily snapshot per bill and date at the database level', function () {
    $bill = CateringUnitBill::factory()->create();
    CateringUnitBillDay::factory()->create([
        'catering_unit_bill_id' => $bill->id,
        'attendance_date' => '2026-10-01',
    ]);

    expect(fn () => CateringUnitBillDay::factory()->create([
        'catering_unit_bill_id' => $bill->id,
        'attendance_date' => '2026-10-01',
    ]))->toThrow(QueryException::class);
});

it('synchronizes and evaluates multiple units with bounded queries', function () {
    $units = CateringEmployeeUnit::factory()->count(8)->create();

    foreach ($units as $unit) {
        createUnitBillingAttendance($unit, '2026-10-01', 5, 13000);
        createUnitBillingAttendance($unit, '2026-10-02', 7, 13000);
    }

    $service = app(CateringUnitBillingService::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $bills = $service->syncPeriod(10, 2026);
    $syncQueryCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $statuses = $service->periodStatus(10, 2026);
    $statusQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($bills)->toHaveCount(8)
        ->and($bills->every(fn (CateringUnitBill $bill): bool => $bill->total_quantity === 12))->toBeTrue()
        ->and($statuses)->toHaveCount(8)
        ->and($statuses->every(fn (array $status): bool => $status['status'] === CateringUnitBillSyncStatus::Synced))->toBeTrue()
        ->and($syncQueryCount)->toBeLessThanOrEqual(12)
        ->and($statusQueryCount)->toBeLessThanOrEqual(4);
});
