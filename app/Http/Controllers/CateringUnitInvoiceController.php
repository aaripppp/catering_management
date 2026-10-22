<?php

namespace App\Http\Controllers;

use App\Exceptions\CateringUnitInvoiceNotReadyException;
use App\Models\CateringUnitBill;
use App\Services\CateringUnitInvoiceService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CateringUnitInvoiceController extends Controller
{
    public function __construct(private readonly CateringUnitInvoiceService $invoices) {}

    public function __invoke(CateringUnitBill $bill): StreamedResponse
    {
        Gate::authorize('viewInvoice', $bill);

        try {
            return $this->invoices->previewResponse($bill);
        } catch (CateringUnitInvoiceNotReadyException $exception) {
            abort(409, $exception->getMessage());
        }
    }
}
