<?php

use App\Exceptions\CateringUnitCombinedInvoiceNotReadyException;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\User;
use App\Services\CateringUnitBillingService;
use App\Services\CateringUnitInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function combinedUnitAttendance(
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

/**
 * @param  array<int, array{date: string, quantity: int, price: int}>  $days
 */
function combinedSyncedBill(
    CateringEmployeeUnit $unit,
    int $month = 10,
    int $year = 2026,
    array $days = [],
): CateringUnitBill {
    $days = $days ?: [
        ['date' => sprintf('%d-%02d-01', $year, $month), 'quantity' => 10, 'price' => 13000],
    ];

    foreach ($days as $day) {
        combinedUnitAttendance($unit, $day['date'], $day['quantity'], $day['price']);
    }

    return app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, $month, $year);
}

function combinedInvoicePdfText(string $bytes): string
{
    $text = '';

    if (preg_match_all('/stream\r?\n(.*?)endstream/s', $bytes, $matches) === false) {
        return $text;
    }

    foreach ($matches[1] as $stream) {
        $decoded = @gzuncompress($stream);

        if ($decoded === false || ! str_contains($decoded, 'Tf')) {
            continue;
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ|\((.*?)\)\s*Tj/s', $decoded, $runs, PREG_SET_ORDER) === false) {
            continue;
        }

        foreach ($runs as $run) {
            $fragment = $run[2] ?? '';

            if (isset($run[1]) && $run[1] !== '') {
                preg_match_all('/\((.*?)\)/s', $run[1], $fragments);
                $fragment .= implode('', $fragments[1]);
            }

            $text .= $fragment.' ';
        }
    }

    return trim((string) preg_replace('/\s+/', ' ', $text));
}

/** @return array{0: CateringEmployeeUnit, 1: CateringEmployeeUnit, 2: CateringEmployeeUnit} */
function combinedUnitTrio(int $month = 10, int $year = 2026): array
{
    $daycare = CateringEmployeeUnit::factory()->create(['name' => 'Daycare', 'sort_order' => 1, 'price_per_person' => 13000]);
    $tu = CateringEmployeeUnit::factory()->create(['name' => 'TU', 'sort_order' => 2, 'price_per_person' => 14000]);
    $sd = CateringEmployeeUnit::factory()->create(['name' => 'SD', 'sort_order' => 3, 'price_per_person' => 15000]);

    combinedSyncedBill($daycare, $month, $year, [
        ['date' => sprintf('%d-%02d-01', $year, $month), 'quantity' => 10, 'price' => 13000],
    ]);
    combinedSyncedBill($tu, $month, $year, [
        ['date' => sprintf('%d-%02d-01', $year, $month), 'quantity' => 8, 'price' => 14000],
    ]);
    combinedSyncedBill($sd, $month, $year, [
        ['date' => sprintf('%d-%02d-01', $year, $month), 'quantity' => 12, 'price' => 15000],
    ]);

    return [$daycare, $tu, $sd];
}

function combinedInvoiceRoute(array $parameters = []): string
{
    return route('catering-unit-bills.invoice.all', array_merge([
        'month' => 10,
        'year' => 2026,
    ], $parameters));
}

it('builds a combined invoice from synchronized unit bill snapshots', function () {
    combinedUnitTrio();

    $invoice = app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026);

    expect($invoice)->toMatchArray([
        'invoiceNumber' => 'INV/CAF/10/2026/UNIT-ALL',
        'invoiceDate' => '01/11/2026',
        'dueDate' => '28/11/2026',
        'dueDateFormatted' => '28 November 2026',
        'periodLabel' => 'Oktober 2026',
        'month' => 10,
        'year' => 2026,
        'rowCount' => 3,
        'grandTotalQuantity' => 30,
        'grandTotalQuantityFormatted' => '30',
        'grandTotalAmount' => 422000,
        'grandTotalAmountFormatted' => 'Rp 422.000',
    ]);
    expect($invoice['rows'])->toBe([
        [
            'no' => 1,
            'unitName' => 'Daycare',
            'totalQuantity' => 10,
            'totalQuantityFormatted' => '10',
            'pricePerPerson' => 13000,
            'priceLabel' => 'Rp 13.000',
            'grossAmount' => 130000,
            'grossAmountFormatted' => 'Rp 130.000',
        ],
        [
            'no' => 2,
            'unitName' => 'TU',
            'totalQuantity' => 8,
            'totalQuantityFormatted' => '8',
            'pricePerPerson' => 14000,
            'priceLabel' => 'Rp 14.000',
            'grossAmount' => 112000,
            'grossAmountFormatted' => 'Rp 112.000',
        ],
        [
            'no' => 3,
            'unitName' => 'SD',
            'totalQuantity' => 12,
            'totalQuantityFormatted' => '12',
            'pricePerPerson' => 15000,
            'priceLabel' => 'Rp 15.000',
            'grossAmount' => 180000,
            'grossAmountFormatted' => 'Rp 180.000',
        ],
    ])->and($invoice['logoDataUri'])->toStartWith('data:image/png;base64,');
});

it('orders combined rows by unit sort_order with a stable fallback', function () {
    $sd = CateringEmployeeUnit::factory()->create(['name' => 'SD', 'sort_order' => 3]);
    $daycare = CateringEmployeeUnit::factory()->create(['name' => 'Daycare', 'sort_order' => 1]);
    $tu = CateringEmployeeUnit::factory()->create(['name' => 'TU', 'sort_order' => 2]);

    foreach ([$sd, $daycare, $tu] as $unit) {
        combinedSyncedBill($unit);
    }

    expect(array_column(app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026)['rows'], 'unitName'))
        ->toBe(['Daycare', 'TU', 'SD']);
});

it('renders a real landscape combined pdf with unit rows and grand total', function () {
    combinedUnitTrio();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(combinedInvoiceRoute());

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Invoice-All-Unit-Oktober-2026.pdf"');

    $pdf = $response->streamedContent();
    $text = combinedInvoicePdfText($pdf);

    expect($pdf)->toStartWith('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(1000)
        ->and(preg_match('/\/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+841(\.\d+)?\s+595(\.\d+)?\s*\]/', $pdf))->toBe(1)
        ->and($text)->toContain('INVOICE CATERING SEMUA UNIT')
        ->and($text)->toContain('Oktober 2026')
        ->and($text)->toContain('INV/CAF/10/2026/UNIT-ALL')
        ->and($text)->toContain('Daycare')
        ->and($text)->toContain('TU')
        ->and($text)->toContain('SD')
        ->and($text)->toContain('Rp 422.000')
        ->and($text)->toContain('28 November 2026');
});

it('shows Tarif Campuran for a mixed-rate bill without inventing a rate', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    combinedSyncedBill($unit, days: [
        ['date' => '2026-10-01', 'quantity' => 10, 'price' => 13000],
        ['date' => '2026-10-02', 'quantity' => 5, 'price' => 14000],
    ]);

    $invoice = app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026);
    $html = view('pdf.catering.unit-invoice-all', $invoice)->render();

    expect($invoice['rows'])->toHaveCount(1)
        ->and($invoice['rows'][0])->toMatchArray([
            'unitName' => 'SD',
            'totalQuantity' => 15,
            'pricePerPerson' => null,
            'priceLabel' => 'Tarif Campuran',
            'grossAmount' => 200000,
        ])
        ->and($html)->toContain('Tarif Campuran')
        ->and($html)->toContain('Rp 200.000');
});

it('includes a synchronized zero-value bill without treating it as no-data', function () {
    $zero = CateringEmployeeUnit::factory()->create(['name' => 'Daycare', 'sort_order' => 1]);
    $sd = CateringEmployeeUnit::factory()->create(['name' => 'SD', 'sort_order' => 2]);
    combinedSyncedBill($zero, days: [
        ['date' => '2026-10-01', 'quantity' => 0, 'price' => 13000],
    ]);
    combinedSyncedBill($sd, days: [
        ['date' => '2026-10-01', 'quantity' => 4, 'price' => 13000],
    ]);

    $invoice = app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026);
    $html = view('pdf.catering.unit-invoice-all', $invoice)->render();

    expect($invoice['rowCount'])->toBe(2)
        ->and($invoice['rows'][0])->toMatchArray([
            'unitName' => 'Daycare',
            'totalQuantity' => 0,
            'totalQuantityFormatted' => '0',
            'grossAmount' => 0,
            'grossAmountFormatted' => 'Rp 0',
        ])
        ->and($invoice['grandTotalQuantity'])->toBe(4)
        ->and($invoice['grandTotalAmount'])->toBe(52000)
        ->and($html)->toContain('Rp 0');
});

it('blocks the combined invoice when any relevant unit is stale', function () {
    $daycare = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    $sd = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    combinedSyncedBill($daycare);
    $bill = combinedSyncedBill($sd);
    CateringUnitAttendance::query()
        ->where('catering_employee_unit_id', $sd->id)
        ->update(['quantity' => 3]);

    expect(fn () => app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026))
        ->toThrow(
            CateringUnitCombinedInvoiceNotReadyException::class,
            CateringUnitCombinedInvoiceNotReadyException::MESSAGE,
        );

    $response = $this->actingAs(User::factory()->admin()->create())->get(combinedInvoiceRoute());

    $response
        ->assertStatus(409)
        ->assertSeeText(CateringUnitCombinedInvoiceNotReadyException::MESSAGE)
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

    expect($bill->fresh()->gross_amount)->toBe(130000);
});

it('blocks the combined invoice when a relevant unit has no bill yet', function () {
    $daycare = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    $tu = CateringEmployeeUnit::factory()->create(['name' => 'TU']);
    combinedSyncedBill($daycare);
    combinedUnitAttendance($tu, '2026-10-01', 9, 14000);

    expect(fn () => app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026))
        ->toThrow(CateringUnitCombinedInvoiceNotReadyException::class);

    $this->actingAs(User::factory()->admin()->create())
        ->get(combinedInvoiceRoute())
        ->assertStatus(409)
        ->assertSeeText(CateringUnitCombinedInvoiceNotReadyException::MESSAGE);
});

it('does not let an active no-data unit block or appear in the combined invoice', function () {
    $daycare = CateringEmployeeUnit::factory()->create(['name' => 'Daycare']);
    $tu = CateringEmployeeUnit::factory()->create(['name' => 'TU']);
    combinedSyncedBill($daycare);

    $invoice = app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026);
    $html = view('pdf.catering.unit-invoice-all', $invoice)->render();

    expect($invoice['rowCount'])->toBe(1)
        ->and(array_column($invoice['rows'], 'unitName'))->toBe(['Daycare'])
        ->and($invoice['grandTotalQuantity'])->toBe(10)
        ->and($invoice['grandTotalAmount'])->toBe(130000);
});

it('includes an inactive unit that still has a synchronized historical bill', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    combinedSyncedBill($unit);
    $unit->update(['is_active' => false]);

    $invoice = app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026);

    expect($invoice['rowCount'])->toBe(1)
        ->and($invoice['rows'][0])->toMatchArray([
            'unitName' => 'SD',
            'totalQuantity' => 10,
            'grossAmount' => 130000,
        ]);
});

it('uses historical bill snapshots instead of the current unit master price', function () {
    $unit = CateringEmployeeUnit::factory()->create([
        'name' => 'SD',
        'price_per_person' => 13000,
    ]);
    combinedSyncedBill($unit);
    $unit->update(['price_per_person' => 15000]);

    $invoice = app(CateringUnitInvoiceService::class)->buildCombinedInvoice(10, 2026);

    expect($invoice['rows'][0])->toMatchArray([
        'unitName' => 'SD',
        'pricePerPerson' => 13000,
        'priceLabel' => 'Rp 13.000',
        'grossAmount' => 130000,
        'grossAmountFormatted' => 'Rp 130.000',
    ])->and($invoice['grandTotalAmount'])->toBe(130000);
});

it('keeps combined invoice dates deterministic across month and year boundaries', function (
    int $year,
    int $month,
    string $sourceDate,
    string $invoiceDate,
    string $dueDate,
    string $dueDateFormatted,
) {
    $unit = CateringEmployeeUnit::factory()->create();
    combinedSyncedBill($unit, $month, $year, [
        ['date' => $sourceDate, 'quantity' => 1, 'price' => 13000],
    ]);

    $invoice = app(CateringUnitInvoiceService::class)->buildCombinedInvoice($month, $year);

    expect($invoice['invoiceDate'])->toBe($invoiceDate)
        ->and($invoice['dueDate'])->toBe($dueDate)
        ->and($invoice['dueDateFormatted'])->toBe($dueDateFormatted);
})->with([
    'October 2026' => [2026, 10, '2026-10-01', '01/11/2026', '28/11/2026', '28 November 2026'],
    'December 2026' => [2026, 12, '2026-12-01', '01/01/2027', '28/01/2027', '28 Januari 2027'],
]);

it('derives a deterministic combined invoice number that is stable per period', function () {
    combinedUnitTrio();
    $service = app(CateringUnitInvoiceService::class);

    $first = $service->buildCombinedInvoice(10, 2026)['invoiceNumber'];
    $second = $service->buildCombinedInvoice(10, 2026)['invoiceNumber'];

    expect($first)->toBe('INV/CAF/10/2026/UNIT-ALL')
        ->and($second)->toBe($first)
        ->and($service->buildCombinedInvoice(11, 2026)['invoiceNumber'])->toBe('INV/CAF/11/2026/UNIT-ALL');
});

it('rejects an invalid combined invoicing period', function () {
    combinedUnitTrio();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(combinedInvoiceRoute(['month' => 13]))->assertSessionHasErrors('month');
    $this->actingAs($admin)->get(combinedInvoiceRoute(['year' => 2101]))->assertSessionHasErrors('year');
    $this->actingAs($admin)->get(route('catering-unit-bills.invoice.all'))->assertSessionHasErrors(['month', 'year']);
});

it('protects the combined invoice route for administrators', function () {
    combinedUnitTrio();

    $this->get(combinedInvoiceRoute())->assertRedirect(route('login'));

    $waliKelas = User::factory()->waliKelas()->create();
    $this->actingAs($waliKelas)
        ->get(combinedInvoiceRoute())
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('viewCombinedInvoice', CateringUnitBill::class))->toBeTrue();
});

it('keeps combined invoice queries bounded without per-unit lookups', function () {
    $admin = User::factory()->admin()->create();
    $daycare = CateringEmployeeUnit::factory()->create(['name' => 'Daycare', 'sort_order' => 1]);
    $tu = CateringEmployeeUnit::factory()->create(['name' => 'TU', 'sort_order' => 2]);
    combinedSyncedBill($daycare);
    combinedSyncedBill($tu);

    $this->actingAs($admin)->get(combinedInvoiceRoute())->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(combinedInvoiceRoute())->assertOk();
    $smallCount = count(DB::getQueryLog());

    foreach (range(3, 6) as $sortOrder) {
        $unit = CateringEmployeeUnit::factory()->create([
            'name' => 'Unit '.$sortOrder,
            'sort_order' => $sortOrder,
        ]);
        combinedSyncedBill($unit);
    }

    DB::flushQueryLog();
    $this->get(combinedInvoiceRoute())->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($smallCount)->toBeGreaterThan(0)
        ->and($largeCount)->toBe($smallCount);
});
