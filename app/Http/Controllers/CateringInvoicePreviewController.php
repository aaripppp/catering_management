<?php

namespace App\Http\Controllers;

use App\Enums\CateringParticipantGroup;
use App\Models\CateringMember;
use App\Models\SchoolClass;
use App\Services\CateringInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves inline catering invoice previews.
 *
 * Individual and class invoices are read-only GET endpoints so the browser can
 * open them in a new tab and render its native PDF viewer.
 */
class CateringInvoicePreviewController extends Controller
{
    public function __construct(private readonly CateringInvoiceService $invoices) {}

    /**
     * Preview the monthly invoice of a single participant.
     */
    public function member(Request $request, CateringMember $member): StreamedResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'participantGroup' => ['required', Rule::enum(CateringParticipantGroup::class)],
            'schoolClassId' => ['nullable', 'integer'],
        ]);

        $group = CateringParticipantGroup::from($validated['participantGroup']);
        $schoolClassId = isset($validated['schoolClassId']) ? (int) $validated['schoolClassId'] : null;

        abort_unless($member->is_active, 404);

        $category = $member->cateringCategory;

        abort_if($category === null || $category->participant_group !== $group, 404);

        if ($group->requiresClassSelection()) {
            abort_if($schoolClassId === null || $member->school_class_id !== $schoolClassId, 404);

            abort_unless(
                SchoolClass::query()->active()->whereKey($schoolClassId)->exists(),
                404,
            );
        }

        [$start, $end] = $this->monthRange((int) $validated['month'], (int) $validated['year']);

        $invoice = $this->invoices->buildMemberInvoice($member, $start, $end);

        abort_if($invoice['savedDays'] === 0, 404, 'Absensi peserta untuk periode ini belum disimpan.');

        return $this->invoices->individualPdfPreviewResponse($invoice);
    }

    /**
     * Preview the monthly summary invoice of a class.
     */
    public function classSummary(Request $request, SchoolClass $schoolClass): StreamedResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        abort_unless($schoolClass->is_active, 404);

        [$start, $end] = $this->monthRange((int) $validated['month'], (int) $validated['year']);

        $invoice = $this->invoices->buildClassInvoice($schoolClass, $start, $end);

        abort_if($invoice['savedDays'] === 0, 404, 'Absensi kelas untuk periode ini belum disimpan.');

        return $this->invoices->classPdfPreviewResponse($invoice);
    }

    /**
     * Resolve the inclusive month range covered by the selected period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function monthRange(int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return [$start, $start->endOfMonth()];
    }
}
