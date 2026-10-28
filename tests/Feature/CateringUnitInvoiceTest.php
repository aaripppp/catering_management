<?php

use App\Exceptions\CateringUnitInvoiceNotReadyException;
use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitAttendance;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use App\Models\User;
use App\Services\CateringUnitBillingService;
use App\Services\CateringUnitInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function createUnitInvoiceAttendance(
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
function createSynchronizedUnitInvoiceBill(
    CateringEmployeeUnit $unit,
    int $month = 10,
    int $year = 2026,
    array $days = [],
): CateringUnitBill {
    $days = $days ?: [
        ['date' => '2026-10-01', 'quantity' => 10, 'price' => 14000],
        ['date' => '2026-10-02', 'quantity' => 12, 'price' => 14000],
    ];

    foreach ($days as $day) {
        createUnitInvoiceAttendance($unit, $day['date'], $day['quantity'], $day['price']);
    }

    return app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, $month, $year);
}

function unitInvoicePdfText(string $bytes): string
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

            $text .= (str_contains($fragment, "\0") ? mb_convert_encoding($fragment, 'UTF-8', 'UTF-16BE') : $fragment).' ';
        }
    }

    return trim((string) preg_replace('/\s+/', ' ', $text));
}

function createPriorUnitInvoiceBill(
    CateringEmployeeUnit $unit,
    int $month,
    int $year,
    int $grossAmount,
): CateringUnitBill {
    return CateringUnitBill::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'period_month' => $month,
        'period_year' => $year,
        'unit_name' => $unit->name,
        'gross_amount' => $grossAmount,
        'net_amount' => $grossAmount,
        'total_subsidy_amount' => 0,
    ]);
}

function payPriorUnitInvoiceBill(CateringUnitBill $bill, int $amount): void
{
    CateringUnitPayment::factory()->create([
        'catering_unit_bill_id' => $bill->id,
        'amount' => $amount,
    ]);
}

it('builds a per-unit invoice from synchronized bill snapshots', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    $bill = createSynchronizedUnitInvoiceBill($unit);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);

    expect($invoice)->toMatchArray([
        'unitName' => 'SD',
        'invoiceNumber' => 'INV/CAF/10/2026/UNIT-'.$bill->id,
        'invoiceDate' => '01/11/2026',
        'dueDate' => '28/11/2026',
        'dueDateFormatted' => '28 November 2026',
        'periodLabel' => 'Oktober 2026',
        'pricePerPerson' => 14000,
        'priceLabel' => 'Rp 14.000',
        'totalQuantity' => 22,
        'grossAmount' => 308000,
        'grossAmountFormatted' => 'Rp 308.000',
    ]);
    expect($invoice['days'])->toBe([
        [
            'date' => '1 Oktober 2026',
            'dateIso' => '2026-10-01',
            'quantity' => 10,
            'pricePerPerson' => 14000,
            'pricePerPersonFormatted' => 'Rp 14.000',
            'subtotal' => 140000,
            'subtotalFormatted' => 'Rp 140.000',
        ],
        [
            'date' => '2 Oktober 2026',
            'dateIso' => '2026-10-02',
            'quantity' => 12,
            'pricePerPerson' => 14000,
            'pricePerPersonFormatted' => 'Rp 14.000',
            'subtotal' => 168000,
            'subtotalFormatted' => 'Rp 168.000',
        ],
    ])->and($invoice['logoDataUri'])->toStartWith('data:image/png;base64,');
});

it('renders a real inline pdf with branding and snapshot totals', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    $bill = createSynchronizedUnitInvoiceBill($unit);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-unit-bills.invoice', $bill));
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Invoice-Unit-SD-Oktober-2026.pdf"');

    $pdf = $response->streamedContent();
    $text = unitInvoicePdfText($pdf);

    expect($pdf)->toStartWith('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(1000)
        ->and($text)->toContain('INVOICE CATERING UNIT')
        ->and($text)->toContain('SD')
        ->and($text)->toContain('Oktober 2026')
        ->and($text)->toContain('INV/CAF/10/2026/UNIT-'.$bill->id)
        ->and($text)->toContain('Rp 308.000')
        ->and($text)->toContain('28 November 2026')
        ->and($queryCount)->toBeLessThanOrEqual(10);
});

it('shows prior unit balances oldest first using each bills remaining amount', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SD']);
    $augustBill = createPriorUnitInvoiceBill($unit, 8, 2026, 600000);
    $septemberBill = createPriorUnitInvoiceBill($unit, 9, 2026, 500000);
    payPriorUnitInvoiceBill($augustBill, 100000);
    payPriorUnitInvoiceBill($septemberBill, 200000);
    $currentBill = createSynchronizedUnitInvoiceBill($unit);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($currentBill);
    $text = unitInvoicePdfText(app(CateringUnitInvoiceService::class)->renderPdf($invoice));

    expect($invoice['priorOutstandingRows'])->toBe([
        [
            'month' => 8,
            'year' => 2026,
            'periodLabel' => 'Agustus 2026',
            'remainingAmount' => 500000,
            'remainingAmountFormatted' => 'Rp 500.000',
        ],
        [
            'month' => 9,
            'year' => 2026,
            'periodLabel' => 'September 2026',
            'remainingAmount' => 300000,
            'remainingAmountFormatted' => 'Rp 300.000',
        ],
    ])->and($invoice['totalOutstanding'])->toBe(800000)
        ->and($invoice['totalDue'])->toBe(1108000)
        ->and($text)->toContain('TUNGGAKAN SEBELUMNYA')
        ->and($text)->toContain('Agustus 2026')
        ->and($text)->toContain('September 2026')
        ->and($text)->toContain('Total Outstanding')
        ->and($text)->toContain('Rp 800.000')
        ->and($text)->toContain('Rp 1.108.000');
});

it('excludes paid overpaid zero and current unit bills from prior outstanding', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $zeroBill = createPriorUnitInvoiceBill($unit, 5, 2026, 0);
    $paidBill = createPriorUnitInvoiceBill($unit, 6, 2026, 100000);
    $overpaidBill = createPriorUnitInvoiceBill($unit, 7, 2026, 100000);
    $partialBill = createPriorUnitInvoiceBill($unit, 9, 2026, 200000);
    payPriorUnitInvoiceBill($paidBill, 100000);
    payPriorUnitInvoiceBill($overpaidBill, 125000);
    payPriorUnitInvoiceBill($partialBill, 50000);
    $currentBill = createSynchronizedUnitInvoiceBill($unit);
    payPriorUnitInvoiceBill($currentBill, 100000);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($currentBill);

    expect($zeroBill->gross_amount)->toBe(0)
        ->and(array_column($invoice['priorOutstandingRows'], 'periodLabel'))->toBe(['September 2026'])
        ->and(array_column($invoice['priorOutstandingRows'], 'remainingAmount'))->toBe([150000])
        ->and($invoice['totalOutstanding'])->toBe(150000)
        ->and($invoice['totalDue'])->toBe(458000);
});

it('orders prior unit outstanding across a year boundary', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    createPriorUnitInvoiceBill($unit, 11, 2026, 100000);
    createPriorUnitInvoiceBill($unit, 12, 2026, 200000);
    $currentBill = createSynchronizedUnitInvoiceBill($unit, month: 1, year: 2027, days: [
        ['date' => '2027-01-05', 'quantity' => 1, 'price' => 14000],
    ]);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($currentBill);

    expect(array_column($invoice['priorOutstandingRows'], 'periodLabel'))->toBe([
        'November 2026',
        'Desember 2026',
    ])->and($invoice['totalOutstanding'])->toBe(300000);
});

it('hides the unit outstanding section when no prior balance exists', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $bill = createSynchronizedUnitInvoiceBill($unit);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);
    $html = view('pdf.catering.unit-invoice', $invoice)->render();

    expect($invoice['priorOutstandingRows'])->toBe([])
        ->and($invoice['totalOutstanding'])->toBe(0)
        ->and($html)->not->toContain('Tunggakan Sebelumnya')
        ->and($html)->not->toContain('Total Outstanding');
});

it('loads unit outstanding in one query regardless of prior period count', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $currentBill = CateringUnitBill::factory()->create([
        'catering_employee_unit_id' => $unit->id,
        'period_month' => 1,
        'period_year' => 2027,
        'gross_amount' => 1000,
        'net_amount' => 1000,
    ]);

    foreach (range(1, 12) as $month) {
        createPriorUnitInvoiceBill($unit, $month, 2026, 100000);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $breakdown = app(CateringUnitInvoiceService::class)->priorOutstandingBreakdownForBill($currentBill);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($breakdown['rows'])->toHaveCount(12)
        ->and($breakdown['total'])->toBe(1200000)
        ->and($queryCount)->toBe(1);
});

it('preserves zero-quantity daily snapshots and allows a zero-value invoice', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'SDM']);
    $bill = createSynchronizedUnitInvoiceBill($unit, days: [
        ['date' => '2026-10-03', 'quantity' => 0, 'price' => 13000],
    ]);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);
    $html = view('pdf.catering.unit-invoice', $invoice)->render();

    expect($invoice['totalQuantity'])->toBe(0)
        ->and($invoice['grossAmount'])->toBe(0)
        ->and($invoice['days'])->toHaveCount(1)
        ->and($invoice['days'][0])->toMatchArray([
            'quantity' => 0,
            'pricePerPerson' => 13000,
            'subtotal' => 0,
        ])
        ->and($html)->toContain('Rp 0');
});

it('renders mixed daily rates without inventing a single header price', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $bill = createSynchronizedUnitInvoiceBill($unit, days: [
        ['date' => '2026-10-01', 'quantity' => 10, 'price' => 13000],
        ['date' => '2026-10-02', 'quantity' => 10, 'price' => 14000],
    ]);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);
    $html = view('pdf.catering.unit-invoice', $invoice)->render();

    expect($invoice['pricePerPerson'])->toBeNull()
        ->and($invoice['priceLabel'])->toBe('Tarif Campuran')
        ->and($invoice['grossAmount'])->toBe(270000)
        ->and(array_column($invoice['days'], 'pricePerPerson'))->toBe([13000, 14000])
        ->and($html)->toContain('Tarif Campuran')
        ->and($html)->toContain('Rp 13.000')
        ->and($html)->toContain('Rp 14.000')
        ->and($html)->toContain('Rp 270.000');
});

it('uses historical bill pricing rather than the current master price', function () {
    $unit = CateringEmployeeUnit::factory()->create([
        'name' => 'SMP',
        'price_per_person' => 13000,
    ]);
    $bill = createSynchronizedUnitInvoiceBill($unit, days: [
        ['date' => '2026-10-01', 'quantity' => 10, 'price' => 13000],
    ]);
    $unit->update(['price_per_person' => 15000]);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);

    expect($invoice['pricePerPerson'])->toBe(13000)
        ->and($invoice['grossAmount'])->toBe(130000)
        ->and($invoice['priceLabel'])->toBe('Rp 13.000');
});

it('blocks stale bills until they are explicitly resynchronized', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $bill = createSynchronizedUnitInvoiceBill($unit);
    CateringUnitAttendance::query()
        ->where('catering_employee_unit_id', $unit->id)
        ->whereDate('attendance_date', '2026-10-01')
        ->update(['quantity' => 8]);

    expect(fn () => app(CateringUnitInvoiceService::class)->buildInvoice($bill))
        ->toThrow(CateringUnitInvoiceNotReadyException::class, CateringUnitInvoiceNotReadyException::MESSAGE);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-unit-bills.invoice', $bill))
        ->assertStatus(409)
        ->assertSeeText(CateringUnitInvoiceNotReadyException::MESSAGE);
});

it('blocks missing and unsynchronized bills with a controlled response', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    createUnitInvoiceAttendance($unit, '2026-10-01', 10, 13000);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-unit-bills.invoice', 999999))
        ->assertStatus(409)
        ->assertSeeText(CateringUnitInvoiceNotReadyException::MESSAGE);

    expect(CateringUnitBill::query()->exists())->toBeFalse();
});

it('blocks a renamed unit snapshot until resync and then uses the refreshed bill name', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'TU']);
    $bill = createSynchronizedUnitInvoiceBill($unit);
    $unit->update(['name' => 'Tata Usaha']);
    $service = app(CateringUnitInvoiceService::class);

    expect($bill->fresh()->unit_name)->toBe('TU')
        ->and(fn () => $service->buildInvoice($bill))->toThrow(CateringUnitInvoiceNotReadyException::class);

    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 10, 2026);

    expect($service->buildInvoice($bill)['unitName'])->toBe('Tata Usaha');
});

it('orders bill-day rows by attendance_date regardless of source insertion order', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    createUnitInvoiceAttendance($unit, '2026-10-05', 4, 13000);
    createUnitInvoiceAttendance($unit, '2026-10-01', 9, 13000);
    createUnitInvoiceAttendance($unit, '2026-10-03', 0, 13000);
    $bill = app(CateringUnitBillingService::class)->syncUnitPeriod($unit->id, 10, 2026);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);

    expect(array_column($invoice['days'], 'dateIso'))->toBe([
        '2026-10-01',
        '2026-10-03',
        '2026-10-05',
    ])->and(array_column($invoice['days'], 'quantity'))->toBe([9, 0, 4])
        ->and($invoice['totalQuantity'])->toBe(13)
        ->and($invoice['grossAmount'])->toBe(169000);
});

it('zero-pads a single-digit month in the deterministic invoice number', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $bill = createSynchronizedUnitInvoiceBill($unit, month: 9, days: [
        ['date' => '2026-09-01', 'quantity' => 2, 'price' => 13000],
    ]);

    expect(app(CateringUnitInvoiceService::class)->buildInvoice($bill)['invoiceNumber'])
        ->toBe('INV/CAF/09/2026/UNIT-'.$bill->id)
        ->and($bill->id)->toBeGreaterThan(0);
});

it('sanitizes and truncates the unit name in the pdf filename', function () {
    $unit = CateringEmployeeUnit::factory()->create(['name' => 'Kantin / SD & SMP "Pusat"']);
    $bill = createSynchronizedUnitInvoiceBill($unit, days: [
        ['date' => '2026-10-01', 'quantity' => 1, 'price' => 13000],
    ]);
    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);

    $filename = app(CateringUnitInvoiceService::class)->filename($invoice);

    expect($filename)->toBe('Invoice-Unit-Kantin-SD-SMP-Pusat-Oktober-2026.pdf')
        ->and($filename)->not->toContain('/')
        ->and($filename)->not->toContain('"');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('catering-unit-bills.invoice', $bill));

    $response->assertOk()->assertHeader(
        'Content-Disposition',
        'inline; filename="'.$filename.'"',
    );
});

it('keeps invoice dates deterministic across month and year boundaries', function (
    int $year,
    int $month,
    string $sourceDate,
    string $invoiceDate,
    string $dueDate,
    string $dueDateFormatted,
) {
    $unit = CateringEmployeeUnit::factory()->create();
    $bill = createSynchronizedUnitInvoiceBill($unit, $month, $year, [
        ['date' => $sourceDate, 'quantity' => 1, 'price' => 13000],
    ]);

    $invoice = app(CateringUnitInvoiceService::class)->buildInvoice($bill);

    expect($invoice['invoiceDate'])->toBe($invoiceDate)
        ->and($invoice['dueDate'])->toBe($dueDate)
        ->and($invoice['dueDateFormatted'])->toBe($dueDateFormatted);
})->with([
    'October 2026' => [2026, 10, '2026-10-01', '01/11/2026', '28/11/2026', '28 November 2026'],
    'December 2026' => [2026, 12, '2026-12-01', '01/01/2027', '28/01/2027', '28 Januari 2027'],
]);

it('derives a stable invoice number from the synchronized bill', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $bill = createSynchronizedUnitInvoiceBill($unit);
    $service = app(CateringUnitInvoiceService::class);

    $first = $service->buildInvoice($bill)['invoiceNumber'];
    $second = $service->buildInvoice($bill->fresh())['invoiceNumber'];

    expect($first)->toBe('INV/CAF/10/2026/UNIT-'.$bill->id)
        ->and($second)->toBe($first);
});

it('protects the direct unit invoice route for administrators', function () {
    $unit = CateringEmployeeUnit::factory()->create();
    $bill = createSynchronizedUnitInvoiceBill($unit);

    $this->get(route('catering-unit-bills.invoice', $bill))->assertRedirect(route('login'));

    $waliKelas = User::factory()->waliKelas()->create();
    $this->actingAs($waliKelas)
        ->get(route('catering-unit-bills.invoice', $bill))
        ->assertForbidden();

    $this->actingAs($waliKelas);
    expect(Gate::denies('viewInvoice', $bill))->toBeTrue();
});
