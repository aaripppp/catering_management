<?php

namespace App\Http\Controllers;

use App\Exceptions\CateringUnitCombinedInvoiceNotReadyException;
use App\Models\CateringUnitBill;
use App\Services\CateringUnitInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the combined monthly invoice preview for all catering units.
 */
class CateringUnitCombinedInvoiceController extends Controller
{
    public function __construct(private readonly CateringUnitInvoiceService $invoices) {}

    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('viewCombinedInvoice', CateringUnitBill::class);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        try {
            return $this->invoices->combinedPreviewResponse(
                (int) $validated['month'],
                (int) $validated['year'],
            );
        } catch (CateringUnitCombinedInvoiceNotReadyException $exception) {
            abort(409, $exception->getMessage());
        }
    }
}
