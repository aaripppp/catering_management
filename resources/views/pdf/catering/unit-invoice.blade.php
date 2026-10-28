<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice Catering Unit - {{ $unitName }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1e293b; font-family: Helvetica, Arial, sans-serif; font-size: 10px; line-height: 1.35; }
        .header { display: table; width: 100%; border-bottom: 1.5px solid #1e40af; padding-bottom: 9px; }
        .header-left, .header-right { display: table-cell; width: 50%; vertical-align: middle; }
        .header-right { text-align: right; }
        .logo { max-height: 48px; max-width: 120px; }
        .org-name { margin-top: 3px; color: #1e3a8a; font-size: 15px; font-weight: bold; }
        .org-sub { color: #64748b; font-size: 8.5px; }
        .title { color: #1e40af; font-size: 17px; font-weight: bold; letter-spacing: .5px; }
        .subtitle { color: #475569; font-size: 9.5px; }
        .meta { display: table; width: 100%; margin-top: 10px; page-break-inside: avoid; }
        .meta-box { display: table-cell; width: 50%; padding: 8px 10px; vertical-align: top; border: 1px solid #e2e8f0; }
        .meta-box + .meta-box { border-left: 0; }
        .meta-title { margin-bottom: 4px; color: #94a3b8; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }
        .meta-row { line-height: 1.55; }
        .meta-key { display: inline-block; width: 92px; color: #64748b; }
        .meta-value { color: #0f172a; font-weight: bold; }
        .section-title { margin: 12px 0 5px; color: #1e40af; font-size: 10.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th { padding: 6px; border: .5px solid #1e40af; background: #1e40af; color: #fff; font-size: 8.5px; text-align: left; text-transform: uppercase; }
        td { padding: 5px 6px; border: .5px solid #e2e8f0; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .summary { margin-top: 10px; page-break-inside: avoid; }
        .summary td { padding: 7px 9px; }
        .summary .label { width: 70%; color: #475569; text-align: right; }
        .summary .value { width: 30%; color: #0f172a; font-weight: bold; text-align: right; white-space: nowrap; }
        .summary .grand td { border-color: #1e40af; background: #1e40af; color: #fff; font-size: 12px; }
        .outstanding { page-break-inside: auto; }
        .outstanding tfoot td { background: #f8fafc; font-weight: bold; page-break-inside: avoid; }
        .payment { margin-top: 12px; padding: 9px 11px; border: .5px solid #bfdbfe; background: #f8fafc; page-break-inside: avoid; }
        .payment-title { margin-bottom: 4px; color: #1e40af; font-size: 9px; font-weight: bold; }
        .payment-row { line-height: 1.5; }
        .payment-label { display: inline-block; width: 95px; color: #64748b; }
        .payment-value { color: #0f172a; font-weight: bold; }
        .due { margin-top: 5px; color: #0f172a; font-weight: bold; }
        .note { margin-top: 8px; color: #475569; font-size: 8.5px; }
        .footer { margin-top: 12px; padding-top: 5px; border-top: .5px solid #e2e8f0; color: #94a3b8; font-size: 7.5px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="{{ config('app.name') }}" class="logo">
            @endif
            <div class="org-name">{{ config('app.name') }}</div>
            <div class="org-sub">Sistem Manajemen Catering Sekolah</div>
        </div>
        <div class="header-right">
            <div class="title">INVOICE CATERING UNIT</div>
            <div class="subtitle">{{ $unitName }} &middot; {{ $periodLabel }}</div>
        </div>
    </div>

    <div class="meta">
        <div class="meta-box">
            <div class="meta-title">Informasi Unit</div>
            <div class="meta-row"><span class="meta-key">Unit</span><span class="meta-value">{{ $unitName }}</span></div>
            <div class="meta-row"><span class="meta-key">Periode</span><span class="meta-value">{{ $periodLabel }}</span></div>
            <div class="meta-row"><span class="meta-key">Harga/Porsi</span><span class="meta-value">{{ $priceLabel }}</span></div>
        </div>
        <div class="meta-box">
            <div class="meta-title">Informasi Invoice</div>
            <div class="meta-row"><span class="meta-key">No. Invoice</span><span class="meta-value">{{ $invoiceNumber }}</span></div>
            <div class="meta-row"><span class="meta-key">Tanggal</span><span class="meta-value">{{ $invoiceDate }}</span></div>
            <div class="meta-row"><span class="meta-key">Batas Bayar</span><span class="meta-value">{{ $dueDateFormatted }}</span></div>
        </div>
    </div>

    <div class="section-title">Rincian Harian</div>
    <table>
        <thead>
            <tr>
                <th class="center" style="width: 7%;">No</th>
                <th style="width: 29%;">Tanggal</th>
                <th class="right" style="width: 16%;">Jumlah</th>
                <th class="right nowrap" style="width: 22%;">Harga/Porsi</th>
                <th class="right" style="width: 26%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($days as $day)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td class="nowrap">{{ $day['date'] }}</td>
                    <td class="right">{{ number_format($day['quantity'], 0, ',', '.') }}</td>
                    <td class="right nowrap">{{ $day['pricePerPersonFormatted'] }}</td>
                    <td class="right nowrap">{{ $day['subtotalFormatted'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center">Tidak ada rincian harian pada snapshot tagihan ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr><td class="label">Total Quantity</td><td class="value">{{ number_format($totalQuantity, 0, ',', '.') }}</td></tr>
        <tr><td class="label">Harga/Porsi</td><td class="value">{{ $priceLabel }}</td></tr>
        <tr><td class="label">Tagihan Kotor</td><td class="value">{{ $grossAmountFormatted }}</td></tr>
        @if (($totalSubsidyAmount ?? 0) > 0)
            @if (($employeeSubsidyAmount ?? 0) > 0)
                <tr><td class="label">Subsidi Pegawai</td><td class="value">- {{ $employeeSubsidyAmountFormatted ?? number_format($employeeSubsidyAmount, 0, ',', '.') }}</td></tr>
            @endif
            @if (($studentSubsidyAmount ?? 0) > 0)
                <tr><td class="label">Subsidi Siswa</td><td class="value">- {{ $studentSubsidyAmountFormatted ?? number_format($studentSubsidyAmount, 0, ',', '.') }}</td></tr>
            @endif
            <tr><td class="label">Total Tagihan</td><td class="value">{{ ($netAmountFormatted ?? $grossAmountFormatted) }}</td></tr>
        @else
            <tr class="grand"><td class="label">Tagihan Bulan Ini</td><td class="value">{{ ($netAmountFormatted ?? $grossAmountFormatted) }}</td></tr>
        @endif
    </table>

    @if ($priorOutstandingRows !== [])
        <div class="section-title">Tunggakan Sebelumnya</div>
        <table class="outstanding">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th class="right">Sisa Tunggakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($priorOutstandingRows as $row)
                    <tr>
                        <td>{{ $row['periodLabel'] }}</td>
                        <td class="right nowrap">{{ $row['remainingAmountFormatted'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total Outstanding</td>
                    <td class="right nowrap">{{ $totalOutstandingFormatted }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="summary">
            <tr><td class="label">Total Tagihan Periode Ini</td><td class="value">{{ ($netAmountFormatted ?? $grossAmountFormatted) }}</td></tr>
            <tr class="grand"><td class="label">Total Harus Dibayar</td><td class="value">{{ $totalDueFormatted }}</td></tr>
        </table>
    @endif

    <div class="payment">
        <div class="payment-title">INFORMASI PEMBAYARAN</div>
        <div class="payment-row"><span class="payment-label">Bank</span><span class="payment-value">{{ $payment['bank'] }}</span></div>
        <div class="payment-row"><span class="payment-label">No. Rekening</span><span class="payment-value">{{ $payment['account_number'] }}</span></div>
        <div class="payment-row"><span class="payment-label">Atas Nama</span><span class="payment-value">{{ $payment['account_name'] }}</span></div>
        <div class="due">Batas Pembayaran: {{ $dueDateFormatted }}</div>
        <div class="note">Setelah transfer, konfirmasikan kepada Admin Catering dengan format: {{ $confirmationReference }}</div>
    </div>

    <div class="footer">
        {{ config('app.name') }} &middot; Invoice Catering Unit &middot; {{ $periodLabel }} &middot; Dibuat dari snapshot tagihan yang telah disinkronkan.
    </div>
</body>
</html>
