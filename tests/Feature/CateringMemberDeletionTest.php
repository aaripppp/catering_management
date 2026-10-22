<?php

use App\Exceptions\CateringMemberDeletionException;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCredit;
use App\Models\CateringCreditAllocation;
use App\Models\CateringEmployeeAssignment;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use App\Models\CateringPayment;
use App\Services\CateringMemberDeletionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function memberDeletionService(): CateringMemberDeletionService
{
    return app(CateringMemberDeletionService::class);
}

function deletionBill(CateringMember $member, int $month = 10): CateringBill
{
    return CateringBill::factory()->create([
        'catering_member_id' => $member->id,
        'member_name' => $member->name,
        'period_month' => $month,
    ]);
}

function deletionPayment(CateringMember $member, CateringBill $bill): CateringPayment
{
    return CateringPayment::factory()->create([
        'catering_member_id' => $member->id,
        'catering_bill_id' => $bill->id,
    ]);
}

function deletionCredit(CateringMember $member, CateringBill $bill, CateringPayment $payment): CateringCredit
{
    return CateringCredit::factory()->create([
        'catering_member_id' => $member->id,
        'source_payment_id' => $payment->id,
        'source_bill_id' => $bill->id,
    ]);
}

it('deletes attendance while preserving unrelated member data', function () {
    $deletedMember = CateringMember::factory()->create();
    $retainedMember = CateringMember::factory()->create();
    $deletedAttendance = CateringAttendance::factory()->for($deletedMember)->create();
    $retainedAttendance = CateringAttendance::factory()->for($retainedMember)->create();

    expect(memberDeletionService()->deleteMany([$deletedMember->id]))->toBe(1)
        ->and(CateringMember::query()->whereKey($deletedMember->id)->exists())->toBeFalse()
        ->and(CateringAttendance::query()->whereKey($deletedAttendance->id)->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($retainedMember->id)->exists())->toBeTrue()
        ->and(CateringAttendance::query()->whereKey($retainedAttendance->id)->exists())->toBeTrue();
});

it('deletes an employee assignment owned by the member', function () {
    $member = CateringMember::factory()->create();
    $assignment = CateringEmployeeAssignment::factory()->general()->create([
        'catering_member_id' => $member->id,
    ]);

    memberDeletionService()->deleteMany([$member->id]);

    expect(CateringMember::query()->whereKey($member->id)->exists())->toBeFalse()
        ->and(CateringEmployeeAssignment::query()->whereKey($assignment->id)->exists())->toBeFalse();
});

it('explicitly deletes every membership period owned by the member', function () {
    $member = CateringMember::factory()->create();
    $periods = CateringMemberPeriod::factory()->count(2)->create([
        'catering_member_id' => $member->id,
    ]);

    memberDeletionService()->deleteMany([$member->id]);

    expect(CateringMember::query()->whereKey($member->id)->exists())->toBeFalse()
        ->and(CateringMemberPeriod::query()->whereKey($periods->modelKeys())->exists())->toBeFalse();
});

it('deletes unpaid bills before deleting the member', function () {
    $member = CateringMember::factory()->create();
    $bills = collect([
        deletionBill($member, 9),
        deletionBill($member, 10),
    ]);

    memberDeletionService()->deleteMany([$member->id]);

    expect(CateringBill::query()->whereKey($bills->pluck('id')->all())->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($member->id)->exists())->toBeFalse();
});

it('deletes payments before their bills and member', function () {
    $member = CateringMember::factory()->create();
    $bill = deletionBill($member);
    $payment = deletionPayment($member, $bill);

    memberDeletionService()->deleteMany([$member->id]);

    expect(CateringPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(CateringBill::query()->whereKey($bill->id)->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($member->id)->exists())->toBeFalse();
});

it('deletes a generated credit before its payment and source bill', function () {
    $member = CateringMember::factory()->create();
    $bill = deletionBill($member);
    $payment = deletionPayment($member, $bill);
    $credit = deletionCredit($member, $bill, $payment);

    memberDeletionService()->deleteMany([$member->id]);

    expect(CateringCredit::query()->whereKey($credit->id)->exists())->toBeFalse()
        ->and(CateringPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(CateringBill::query()->whereKey($bill->id)->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($member->id)->exists())->toBeFalse();
});

it('deletes one credit and its allocations across multiple later bills', function () {
    $member = CateringMember::factory()->create();
    $sourceBill = deletionBill($member, 9);
    $payment = deletionPayment($member, $sourceBill);
    $credit = deletionCredit($member, $sourceBill, $payment);
    $targetBills = collect([
        deletionBill($member, 10),
        deletionBill($member, 11),
    ]);
    $allocations = $targetBills->map(fn (CateringBill $bill): CateringCreditAllocation => CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $credit->id,
        'catering_bill_id' => $bill->id,
    ]));

    memberDeletionService()->deleteMany([$member->id]);

    expect(CateringCreditAllocation::query()->whereKey($allocations->pluck('id')->all())->exists())->toBeFalse()
        ->and(CateringCredit::query()->whereKey($credit->id)->exists())->toBeFalse()
        ->and(CateringPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(CateringBill::query()->whereKey([$sourceBill->id, ...$targetBills->pluck('id')->all()])->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($member->id)->exists())->toBeFalse();
});

it('preserves the complete financial graph of an unrelated member', function () {
    $deletedMember = CateringMember::factory()->create();
    $deletedBill = deletionBill($deletedMember);
    $deletedPayment = deletionPayment($deletedMember, $deletedBill);
    $deletedCredit = deletionCredit($deletedMember, $deletedBill, $deletedPayment);
    $deletedTargetBill = deletionBill($deletedMember, 11);
    $deletedAllocation = CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $deletedCredit->id,
        'catering_bill_id' => $deletedTargetBill->id,
    ]);

    $retainedMember = CateringMember::factory()->create();
    $retainedBill = deletionBill($retainedMember);
    $retainedPayment = deletionPayment($retainedMember, $retainedBill);
    $retainedCredit = deletionCredit($retainedMember, $retainedBill, $retainedPayment);
    $retainedTargetBill = deletionBill($retainedMember, 11);
    $retainedAllocation = CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $retainedCredit->id,
        'catering_bill_id' => $retainedTargetBill->id,
    ]);

    memberDeletionService()->deleteMany([$deletedMember->id]);

    expect(CateringMember::query()->whereKey($deletedMember->id)->exists())->toBeFalse()
        ->and(CateringBill::query()->whereKey([$deletedBill->id, $deletedTargetBill->id])->exists())->toBeFalse()
        ->and(CateringPayment::query()->whereKey($deletedPayment->id)->exists())->toBeFalse()
        ->and(CateringCredit::query()->whereKey($deletedCredit->id)->exists())->toBeFalse()
        ->and(CateringCreditAllocation::query()->whereKey($deletedAllocation->id)->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($retainedMember->id)->exists())->toBeTrue()
        ->and(CateringBill::query()->whereKey([$retainedBill->id, $retainedTargetBill->id])->count())->toBe(2)
        ->and(CateringPayment::query()->whereKey($retainedPayment->id)->exists())->toBeTrue()
        ->and(CateringCredit::query()->whereKey($retainedCredit->id)->exists())->toBeTrue()
        ->and(CateringCreditAllocation::query()->whereKey($retainedAllocation->id)->exists())->toBeTrue();
});

it('rejects a payment linked to a bill owned by another member', function () {
    $paymentMember = CateringMember::factory()->create();
    $billMember = CateringMember::factory()->create();
    $bill = deletionBill($billMember);
    $payment = deletionPayment($paymentMember, $bill);

    expect(fn (): int => memberDeletionService()->deleteMany([$paymentMember->id]))
        ->toThrow(CateringMemberDeletionException::class, 'relasi data keuangan yang tidak konsisten');

    expect(CateringMember::query()->whereKey([$paymentMember->id, $billMember->id])->count())->toBe(2)
        ->and(CateringBill::query()->whereKey($bill->id)->exists())->toBeTrue()
        ->and(CateringPayment::query()->whereKey($payment->id)->exists())->toBeTrue();
});

it('rejects a credit whose source payment and bill belong to another member', function () {
    $creditMember = CateringMember::factory()->create();
    $sourceMember = CateringMember::factory()->create();
    $sourceBill = deletionBill($sourceMember);
    $sourcePayment = deletionPayment($sourceMember, $sourceBill);
    $credit = deletionCredit($creditMember, $sourceBill, $sourcePayment);

    expect(fn (): int => memberDeletionService()->deleteMany([$creditMember->id]))
        ->toThrow(CateringMemberDeletionException::class, 'relasi data keuangan yang tidak konsisten');

    expect(CateringMember::query()->whereKey([$creditMember->id, $sourceMember->id])->count())->toBe(2)
        ->and(CateringBill::query()->whereKey($sourceBill->id)->exists())->toBeTrue()
        ->and(CateringPayment::query()->whereKey($sourcePayment->id)->exists())->toBeTrue()
        ->and(CateringCredit::query()->whereKey($credit->id)->exists())->toBeTrue();
});

it('rejects a credit allocation targeting another member bill', function () {
    $creditMember = CateringMember::factory()->create();
    $sourceBill = deletionBill($creditMember);
    $sourcePayment = deletionPayment($creditMember, $sourceBill);
    $credit = deletionCredit($creditMember, $sourceBill, $sourcePayment);
    $targetMember = CateringMember::factory()->create();
    $targetBill = deletionBill($targetMember);
    $allocation = CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $credit->id,
        'catering_bill_id' => $targetBill->id,
    ]);

    expect(fn (): int => memberDeletionService()->deleteMany([$creditMember->id]))
        ->toThrow(CateringMemberDeletionException::class, 'relasi data keuangan yang tidak konsisten');

    expect(CateringMember::query()->whereKey([$creditMember->id, $targetMember->id])->count())->toBe(2)
        ->and(CateringCreditAllocation::query()->whereKey($allocation->id)->exists())->toBeTrue()
        ->and(CateringCredit::query()->whereKey($credit->id)->exists())->toBeTrue()
        ->and(CateringBill::query()->whereKey($targetBill->id)->exists())->toBeTrue();
});

it('conservatively rejects mismatched ownership even when both members are selected', function () {
    $paymentMember = CateringMember::factory()->create();
    $billMember = CateringMember::factory()->create();
    $bill = deletionBill($billMember);
    $payment = deletionPayment($paymentMember, $bill);

    expect(fn (): int => memberDeletionService()->deleteMany([$paymentMember->id, $billMember->id]))
        ->toThrow(CateringMemberDeletionException::class);

    expect(CateringMember::query()->whereKey([$paymentMember->id, $billMember->id])->count())->toBe(2)
        ->and(CateringBill::query()->whereKey($bill->id)->exists())->toBeTrue()
        ->and(CateringPayment::query()->whereKey($payment->id)->exists())->toBeTrue();
});

it('rolls back the complete graph when the final member delete fails', function () {
    $member = CateringMember::factory()->create();
    $attendance = CateringAttendance::factory()->for($member)->create();
    $assignment = CateringEmployeeAssignment::factory()->general()->create(['catering_member_id' => $member->id]);
    $period = CateringMemberPeriod::factory()->create(['catering_member_id' => $member->id]);
    $sourceBill = deletionBill($member);
    $payment = deletionPayment($member, $sourceBill);
    $credit = deletionCredit($member, $sourceBill, $payment);
    $targetBill = deletionBill($member, 11);
    $allocation = CateringCreditAllocation::factory()->create([
        'catering_credit_id' => $credit->id,
        'catering_bill_id' => $targetBill->id,
    ]);

    DB::unprepared("CREATE TRIGGER prevent_member_hard_delete BEFORE DELETE ON catering_members WHEN OLD.id = {$member->id} BEGIN SELECT RAISE(ABORT, 'blocked'); END;");

    try {
        expect(fn (): int => memberDeletionService()->deleteMany([$member->id]))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_member_hard_delete');
    }

    expect(CateringMember::query()->whereKey($member->id)->exists())->toBeTrue()
        ->and(CateringAttendance::query()->whereKey($attendance->id)->exists())->toBeTrue()
        ->and(CateringEmployeeAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue()
        ->and(CateringMemberPeriod::query()->whereKey($period->id)->exists())->toBeTrue()
        ->and(CateringBill::query()->whereKey([$sourceBill->id, $targetBill->id])->count())->toBe(2)
        ->and(CateringPayment::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and(CateringCredit::query()->whereKey($credit->id)->exists())->toBeTrue()
        ->and(CateringCreditAllocation::query()->whereKey($allocation->id)->exists())->toBeTrue();
});

it('normalizes ids and bulk deletes mixed selected graphs only', function () {
    $attendanceMember = CateringMember::factory()->create();
    $attendance = CateringAttendance::factory()->for($attendanceMember)->create();
    $financialMember = CateringMember::factory()->create();
    $bill = deletionBill($financialMember);
    $payment = deletionPayment($financialMember, $bill);
    $retainedMember = CateringMember::factory()->create();
    $retainedBill = deletionBill($retainedMember);

    $deletedCount = memberDeletionService()->deleteMany([
        (string) $financialMember->id,
        $attendanceMember->id,
        $financialMember->id,
        0,
        'invalid',
    ]);

    expect($deletedCount)->toBe(2)
        ->and(CateringMember::query()->whereKey([$attendanceMember->id, $financialMember->id])->exists())->toBeFalse()
        ->and(CateringAttendance::query()->whereKey($attendance->id)->exists())->toBeFalse()
        ->and(CateringBill::query()->whereKey($bill->id)->exists())->toBeFalse()
        ->and(CateringPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(CateringMember::query()->whereKey($retainedMember->id)->exists())->toBeTrue()
        ->and(CateringBill::query()->whereKey($retainedBill->id)->exists())->toBeTrue();
});
