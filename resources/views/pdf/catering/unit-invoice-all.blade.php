<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice Catering Semua Unit - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
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
        .summary { margin-top: 10px; width: 45%; margin-left: auto; page-break-inside: avoid; }
        .summary td { padding: 7px 9px; }
        .summary .label { width: 60%; color: #475569; text-align: right; }
        .summary .value { width: 40%; color: #0f172a; font-weight: bold; text-align: right; white-space: nowrap; }
        .summary .grand td { border-color: #1e40af; background: #1e40af; color: #fff; font-size: 12px; }
        .payment { margin-top: 12px; padding: 9px 11px; border: .5px solid #bfdbfe; background: #f8fafc; page-break-inside: avoid; }
        .payment-title { margin-bottom: 4px; color: #1e40af; font-size: 9px; font-weight: bold; }
        .payment-row { line-height: 1.5; }
        .payment-label { display: inline-block; width: 95px; color: #64748b; }
        .payment-value { color: #0f172a; font-weight: bold; }
        .due { margin-top: 5px; color: #0f172a; font-weight: bold; }
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
            <div class="title">INVOICE CATERING SEMUA UNIT</div>
            <div class="subtitle">{{ $periodLabel }}</div>
        </div>
    </div>

    <div class="meta">
        <div class="meta-box">
            <div class="meta-title">Informasi Tagihan</div>
            <div class="meta-row"><span class="meta-key">Periode</span><span class="meta-value">{{ $periodLabel }}</span></div>
            <div class="meta-row"><span class="meta-key">Jumlah Unit</span><span class="meta-value">{{ number_format($rowCount, 0, ',', '.') }}</span></div>
        </div>
        <div class="meta-box">
            <div class="meta-title">Informasi Invoice</div>
            <div class="meta-row"><span class="meta-key">No. Invoice</span><span class="meta-value">{{ $invoiceNumber }}</span></div>
            <div class="meta-row"><span class="meta-key">Tanggal</span><span class="meta-value">{{ $invoiceDate }}</span></div>
            <div class="meta-row"><span class="meta-key">Batas Bayar</span><span class="meta-value">{{ $dueDateFormatted }}</span></div>
        </div>
    </div>

    <div class="section-title">Rincian Tagihan Per Unit</div>
    <table>
        <thead>
            <tr>
                <th class="center" style="width: 6%;">No</th>
                <th style="width: 44%;">Unit</th>
                <th class="right" style="width: 14%;">Total Qty</th>
                <th class="right nowrap" style="width: 16%;">Harga/Porsi</th>
                <th class="right nowrap" style="width: 20%;">Total Tagihan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="center">{{ $row['no'] }}</td>
                    <td>{{ $row['unitName'] }}</td>
                    <td class="right">{{ $row['totalQuantityFormatted'] }}</td>
                    <td class="right nowrap">{{ $row['priceLabel'] }}</td>
                    <td class="right nowrap">{{ $row['grossAmountFormatted'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center">Tidak ada tagihan unit tersinkron untuk periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr><td class="label">Total Quantity</td><td class="value">{{ $grandTotalQuantityFormatted }}</td></tr>
        <tr class="grand"><td class="label">Total Tagihan</td><td class="value">{{ $grandTotalAmountFormatted }}</td></tr>
    </table>

    <div class="payment">
        <div class="payment-title">INFORMASI PEMBAYARAN</div>
        <div class="payment-row"><span class="payment-label">Bank</span><span class="payment-value">{{ $payment['bank'] }}</span></div>
        <div class="payment-row"><span class="payment-label">No. Rekening</span><span class="payment-value">{{ $payment['account_number'] }}</span></div>
        <div class="payment-row"><span class="payment-label">Atas Nama</span><span class="payment-value">{{ $payment['account_name'] }}</span></div>
        <div class="due">Batas Pembayaran: {{ $dueDateFormatted }}</div>
    </div>

    <div class="footer">
        {{ config('app.name') }} &middot; Invoice Catering Semua Unit &middot; {{ $periodLabel }} &middot; Dibuat dari snapshot tagihan yang telah disinkronkan.
    </div>
</body>
</html>
