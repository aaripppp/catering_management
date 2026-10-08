<?php

namespace App\Http\Controllers;

use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\CateringBill;
use App\Services\CateringMonthlyReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CateringMonthlyReportPreviewController extends Controller
{
    public function __construct(private readonly CateringMonthlyReportExportService $exportService) {}

    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', CateringBill::class);
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'participantGroup' => ['nullable', Rule::enum(CateringParticipantGroup::class)],
            'jenjang' => ['nullable', Rule::in(SchoolLevel::educationLevels())],
            'schoolClassId' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
        ]);

        $filters = [
            'month' => (int) $validated['month'],
            'year' => (int) $validated['year'],
            'participantGroup' => (string) ($validated['participantGroup'] ?? ''),
            'jenjang' => (string) ($validated['jenjang'] ?? ''),
            'schoolClassId' => isset($validated['schoolClassId']) ? (string) $validated['schoolClassId'] : '',
        ];

        return $this->exportService->pdfPreviewResponse($filters);
    }
}
