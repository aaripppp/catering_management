<?php

use App\Enums\CateringAttendanceStatus;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function billableCultureMealMigration(string $filename): Migration
{
    return require database_path('migrations/'.$filename);
}

it('backfills the attendance billing flag as false for existing budaya makan rows', function () {
    $attendance = CateringAttendance::factory()->create([
        'status' => CateringAttendanceStatus::Alfa,
    ]);

    Schema::table('catering_attendances', function (Blueprint $table): void {
        $table->dropColumn('is_culture_meal_billable');
    });

    billableCultureMealMigration('2026_11_09_042228_add_is_culture_meal_billable_to_catering_attendances_table.php')->up();

    expect((bool) DB::table('catering_attendances')->where('id', $attendance->id)->value('is_culture_meal_billable'))->toBeFalse();
});

it('backfills billable days from active days for existing bills', function () {
    $bill = CateringBill::factory()->create([
        'active_days' => 12,
        'alfa_days' => 3,
        'billable_days' => 12,
        'gross_amount' => 240000,
    ]);

    Schema::table('catering_bills', function (Blueprint $table): void {
        $table->dropColumn('billable_days');
    });

    billableCultureMealMigration('2026_11_09_042230_add_billable_days_to_catering_bills_table.php')->up();

    $restored = DB::table('catering_bills')->where('id', $bill->id)->first();

    expect((int) $restored->billable_days)->toBe(12)
        ->and((int) $restored->active_days)->toBe(12)
        ->and((int) $restored->gross_amount)->toBe(240000);
});

it('adds zeroed billable culture meal snapshots without changing historical gross', function () {
    $bill = CateringBill::factory()->create([
        'active_days' => 12,
        'billable_days' => 12,
        'gross_amount' => 240000,
    ]);

    Schema::table('catering_bills', function (Blueprint $table): void {
        $table->dropColumn([
            'billable_culture_meal_days',
            'culture_meal_billable_price',
            'culture_meal_billable_amount',
        ]);
    });

    billableCultureMealMigration('2026_11_09_083736_add_bm_pricing_to_catering_bills_table.php')->up();

    $restored = DB::table('catering_bills')->where('id', $bill->id)->first();

    expect((int) $restored->billable_culture_meal_days)->toBe(0)
        ->and((int) $restored->culture_meal_billable_price)->toBe(0)
        ->and((int) $restored->culture_meal_billable_amount)->toBe(0)
        ->and((int) $restored->gross_amount)->toBe(240000);
});
