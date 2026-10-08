<?php

namespace App\Http\Controllers;

use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Services\CateringAttendanceRecapExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CateringAttendanceRecapPreviewController extends Controller
{
    public function __construct(private readonly CateringAttendanceRecapExportService $exportService) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'participantGroup' => ['nullable', Rule::enum(CateringParticipantGroup::class)],
            'jenjang' => ['nullable', Rule::in(SchoolLevel::educationLevels())],
            'schoolClassId' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $filters = [
            'month' => (int) $validated['month'],
            'year' => (int) $validated['year'],
            'participantGroup' => (string) ($validated['participantGroup'] ?? ''),
            'jenjang' => (string) ($validated['jenjang'] ?? ''),
            'schoolClassId' => isset($validated['schoolClassId']) ? (string) $validated['schoolClassId'] : '',
            'search' => trim((string) ($validated['search'] ?? '')),
        ];

        return $this->exportService->pdfPreviewResponse($filters);
    }
}
