@use(App\Enums\CateringAttendanceStatus)

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $titleLabel }} - {{ $memberName }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 9mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #1e293b;
            margin: 0;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 1.5px solid #1e40af;
            padding-bottom: 7px;
            page-break-inside: avoid;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 40%;
        }

        .logo {
            max-height: 44px;
            max-width: 112px;
        }

        .org-name {
            font-size: 15px;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 3px;
        }

        .org-sub {
            font-size: 8.5px;
            color: #64748b;
        }

        .doc-title {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #1e40af;
        }

        .doc-subtitle {
            font-size: 9.5px;
            color: #475569;
        }

        .meta {
            display: table;
            width: 100%;
            margin-top: 8px;
            page-break-inside: avoid;
        }

        .meta-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            border: 1px solid #e2e8f0;
            padding: 7px 9px;
        }

        .meta-box + .meta-box {
            border-left: none;
        }

        .meta-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 3px;
        }

        .meta-row {
            line-height: 1.4;
        }

        .meta-key {
            display: inline-block;
            width: 88px;
            color: #64748b;
        }

        .meta-value {
            font-weight: bold;
            color: #0f172a;
        }

        .section-title {
            font-size: 10.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1e40af;
            margin: 9px 0 4px;
            page-break-inside: avoid;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }

        table.detail thead {
            display: table-header-group;
        }

        table.detail th {
            background: #1e40af;
            color: #ffffff;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 4px 5px;
            text-align: left;
            border: 0.5px solid #1e40af;
        }

        table.detail td {
            border: 0.5px solid #e2e8f0;
            padding: 3.2px 5px;
            line-height: 1.2;
        }

        table.detail tbody tr.alt td {
            background: #f8fafc;
        }

        .num {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .nowrap {
            white-space: nowrap;
        }

        .status-pill {
            display: inline-block;
            padding: 0.5px 5px;
            border-radius: 7px;
            font-size: 8.5px;
            font-weight: bold;
        }

        .status-billed {
            background: #dcfce7;
            color: #166534;
        }

        .status-free {
            background: #f1f5f9;
            color: #64748b;
        }

        .billable {
            color: #166534;
            font-weight: bold;
        }

        .not-billable {
            color: #64748b;
        }

        table.summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 7px;
            page-break-inside: avoid;
        }

        table.summary-grid th {
            background: #f1f5f9;
            color: #475569;
            font-size: 8px;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 3.5px 4px;
            border: 0.5px solid #e2e8f0;
        }

        table.summary-grid td {
            padding: 4px;
            border: 0.5px solid #e2e8f0;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }

        table.summary-grid td.price {
            font-size: 10px;
        }

        table.summary-grid th.total-head,
        table.summary-grid td.total-cell {
            background: #1e40af;
            color: #ffffff;
            border-color: #1e40af;
        }

        table.summary-grid td.total-cell {
            font-size: 13px;
            white-space: nowrap;
        }

        .notes {
            margin-top: 7px;
            border-left: 2px solid #1e40af;
            background: #f8fafc;
            padding: 6px 9px;
            color: #475569;
            font-size: 8.5px;
            line-height: 1.45;
            page-break-inside: avoid;
        }

        .notes ul {
            margin: 0;
            padding-left: 12px;
        }

        .footer {
            margin-top: 7px;
            text-align: center;
            font-size: 7.5px;
            color: #94a3b8;
            border-top: 0.5px solid #e2e8f0;
            padding-top: 4px;
            page-break-inside: avoid;
        }
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
        <div class="doc-title">{{ $titleLabel }}</div>
        <div class="doc-subtitle">{{ $contextLabel }}</div>
    </div>
</div>

<div class="meta">
    <div class="meta-box">
        <div class="meta-label">Data Peserta</div>
        <div class="meta-row">
            <span class="meta-key">Nama</span>
            <span class="meta-value">{{ $memberName }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-key">{{ $identityLabel }}</span>
            <span class="meta-value">{{ $identityValue }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-key">Kelompok</span>
            <span class="meta-value">{{ $participantGroupLabel }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-key">Wali</span>
            <span class="meta-value">{{ $guardianName ?: '-' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-key">Telp. Wali</span>
            <span class="meta-value">{{ $guardianPhone ?: '-' }}</span>
        </div>
    </div>
    <div class="meta-box">
        <div class="meta-label">Informasi Invoice</div>
        <div class="meta-row">
            <span class="meta-key">No. Invoice</span>
            <span class="meta-value">{{ $invoiceNumber }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-key">Tanggal</span>
            <span class="meta-value">{{ $invoiceDate }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-key">Periode</span>
            <span class="meta-value">{{ $periodLabel }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-key">Harga / Porsi</span>
            <span class="meta-value">{{ $pricePerDayFormatted }}</span>
        </div>
    </div>
</div>

<div class="section-title">Rincian Absensi {{ $periodDescription }}</div>

<table class="detail">
    <thead>
        <tr>
            <th class="num" style="width: 5%;">No</th>
            <th class="nowrap" style="width: 13%;">Tanggal</th>
            <th style="width: 12%;">Status</th>
            <th style="width: 20%;">Keterangan</th>
            <th class="right nowrap" style="width: 25%;">Harga / Porsi</th>
            <th class="right nowrap" style="width: 25%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($attendanceRows as $row)
            <tr class="{{ $loop->odd ? 'alt' : '' }}">
                <td class="num">{{ $row['number'] }}</td>
                <td class="nowrap">{{ $row['date'] }}</td>
                <td>
                    <span class="status-pill {{ $row['isBillable'] ? 'status-billed' : 'status-free' }}">{{ $row['status'] }}</span>
                </td>
                <td class="{{ $row['isBillable'] ? 'billable' : 'not-billable' }}">{{ $row['note'] }}</td>
                <td class="right nowrap">{{ $row['pricePerDayFormatted'] }}</td>
                <td class="right nowrap">{{ $row['subtotalFormatted'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="num not-billable">Belum ada absensi tersimpan untuk periode ini.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Rekap Absensi &amp; Tagihan</div>

<table class="summary-grid">
    <tr>
        <th style="width: 8%;">{{ CateringAttendanceStatus::Ikut->label() }}</th>
        <th style="width: 8%;">{{ CateringAttendanceStatus::Sakit->label() }}</th>
        <th style="width: 8%;">{{ CateringAttendanceStatus::Izin->label() }}</th>
        <th style="width: 8%;">{{ CateringAttendanceStatus::Alfa->label() }}</th>
        <th style="width: 8%;">{{ CateringAttendanceStatus::TidakIkut->label() }}</th>
        <th style="width: 8%;">{{ CateringAttendanceStatus::Libur->label() }}</th>
        <th style="width: 18%;">Harga / Porsi</th>
        <th class="total-head" style="width: 34%;">Total Tagihan</th>
    </tr>
    <tr>
        <td>{{ $countIkut }}</td>
        <td>{{ $countSakit }}</td>
        <td>{{ $countIzin }}</td>
        <td>{{ $countAlfa }}</td>
        <td>{{ $countTidakIkut }}</td>
        <td>{{ $countLibur }}</td>
        <td class="price">{{ $pricePerDayFormatted }}</td>
        <td class="total-cell">{{ $quantity }} hari &middot; {{ $totalFormatted }}</td>
    </tr>
</table>

<div class="notes">
    <ul>
        <li>Hanya status <strong>{{ CateringAttendanceStatus::Ikut->label() }}</strong> yang dihitung.</li>
        <li>{{ CateringAttendanceStatus::Sakit->label() }}, {{ CateringAttendanceStatus::Izin->label() }}, {{ CateringAttendanceStatus::Alfa->label() }}, {{ CateringAttendanceStatus::TidakIkut->label() }}, dan {{ CateringAttendanceStatus::Libur->label() }} tidak ditagihkan.</li>
        <li>Dihitung dari data absensi catering yang telah disimpan untuk periode {{ $periodLabel }}.</li>
        <li>Rincian hanya menampilkan hari kerja Senin sampai Jumat; hari Sabtu dan Minggu tidak ditampilkan.</li>
    </ul>
</div>

<div class="footer">
    {{ config('app.name') }} &middot; {{ $contextLabel }} &middot; {{ $periodLabel }} &middot;
    Invoice ini dihasilkan otomatis dari data absensi yang telah disimpan.
</div>

</body>
</html>
