@use(App\Enums\CateringAttendanceStatus)

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice Catering Kelas {{ $className }}</title>
    <style>
        @page {
            margin: 22px 26px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #1e293b;
            margin: 0;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 10px;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 58%;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 42%;
        }

        .logo {
            max-height: 52px;
            max-width: 150px;
        }

        .org-name {
            font-size: 15px;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 5px;
        }

        .org-sub {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        .doc-title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1e40af;
        }

        .doc-subtitle {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .meta td {
            border: 1px solid #e2e8f0;
            padding: 4px 8px;
        }

        .meta-label {
            display: inline-block;
            width: 78px;
            color: #64748b;
        }

        .meta-value {
            font-weight: bold;
            color: #0f172a;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #1e40af;
            margin: 16px 0 6px;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
        }

        table.detail th {
            background: #1e40af;
            color: #ffffff;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 6px 7px;
            text-align: left;
            border: 1px solid #1e40af;
        }

        table.detail td {
            border: 1px solid #e2e8f0;
            padding: 5px 7px;
            vertical-align: top;
        }

        table.detail tbody tr.alt td {
            background: #f8fafc;
        }

        table.detail tfoot td {
            background: #eff6ff;
            font-weight: bold;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            padding: 6px 7px;
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

        .grand-total {
            font-size: 11px;
        }

        .notes {
            margin-top: 16px;
            border-left: 3px solid #1e40af;
            background: #f8fafc;
            padding: 8px 11px;
            color: #475569;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 7px;
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
        <div class="doc-title">INVOICE CATERING KELAS</div>
        <div class="doc-subtitle">{{ $periodDescription }}</div>
    </div>
</div>

<table class="meta">
    <tr>
        <td style="width: 25%;">
            <span class="meta-label">Kelas</span>
            <span class="meta-value">{{ $className }}</span>
        </td>
        <td style="width: 25%;">
            <span class="meta-label">Jenjang</span>
            <span class="meta-value">{{ $level === '' ? '-' : 'Kelas '.$level }}</span>
        </td>
        <td style="width: 25%;">
            <span class="meta-label">Periode</span>
            <span class="meta-value">{{ $periodLabel }}</span>
        </td>
        <td style="width: 25%;">
            <span class="meta-label">No. Invoice</span>
            <span class="meta-value">{{ $invoiceNumber }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="meta-label">Tanggal</span>
            <span class="meta-value">{{ $invoiceDate }}</span>
        </td>
        <td>
            <span class="meta-label">Jumlah Peserta</span>
            <span class="meta-value">{{ $participants }}</span>
        </td>
        <td colspan="2">
            <span class="meta-label">Total Tagihan</span>
            <span class="meta-value grand-total">{{ $grandTotalFormatted }}</span>
        </td>
    </tr>
</table>

<div class="section-title">Rekap Absensi per Peserta</div>

<table class="detail">
    <thead>
        <tr>
            <th class="num" style="width: 4%;">No</th>
            <th style="width: 24%;">Nama Siswa</th>
            <th class="num" style="width: 7%;">Ikut</th>
            <th class="num" style="width: 7%;">Sakit</th>
            <th class="num" style="width: 7%;">Izin</th>
            <th class="num" style="width: 7%;">Alfa</th>
            <th class="num" style="width: 7%;">Tdk Ikut</th>
            <th class="num" style="width: 7%;">Libur</th>
            <th class="right nowrap" style="width: 14%;">Harga / Ikut</th>
            <th class="right nowrap" style="width: 16%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr class="{{ $loop->odd ? 'alt' : '' }}">
                <td class="num">{{ $loop->iteration }}</td>
                <td>{{ $row['memberName'] }}</td>
                <td class="num">{{ $row['ikut'] }}</td>
                <td class="num">{{ $row['sakit'] }}</td>
                <td class="num">{{ $row['izin'] }}</td>
                <td class="num">{{ $row['alfa'] }}</td>
                <td class="num">{{ $row['tidakIkut'] }}</td>
                <td class="num">{{ $row['libur'] }}</td>
                <td class="right nowrap">{{ $row['pricePerDayFormatted'] }}</td>
                <td class="right nowrap">{{ $row['totalFormatted'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="num">Belum ada peserta aktif pada kelas ini.</td>
            </tr>
        @endforelse
    </tbody>
    @if ($rows !== [])
        <tfoot>
            <tr>
                <td class="num" colspan="2">TOTAL</td>
                <td class="num">{{ $totalIkut }}</td>
                <td class="num">{{ $totalSakit }}</td>
                <td class="num">{{ $totalIzin }}</td>
                <td class="num">{{ $totalAlfa }}</td>
                <td class="num">{{ $totalTidakIkut }}</td>
                <td class="num">{{ $totalLibur }}</td>
                <td class="right nowrap">-</td>
                <td class="right nowrap">{{ $grandTotalFormatted }}</td>
            </tr>
        </tfoot>
    @endif
</table>

<div class="notes">
    <strong>Catatan:</strong> Rekap ini disusun dari data absensi catering kelas {{ $className }} yang telah disimpan
    untuk periode {{ $periodLabel }}. Hanya status <strong>{{ CateringAttendanceStatus::Ikut->label() }}</strong> yang
    ditagihkan; status {{ CateringAttendanceStatus::Sakit->label() }},
    {{ CateringAttendanceStatus::Izin->label() }},
    {{ CateringAttendanceStatus::Alfa->label() }},
    {{ CateringAttendanceStatus::TidakIkut->label() }}, dan
    {{ CateringAttendanceStatus::Libur->label() }} tidak menambah tagihan.
    Harga per porsi mengikuti harga kategori masing-masing peserta, sehingga total bersifat jumlah per peserta
    dan bukan hasil rata-rata harga.
</div>

<div class="footer">
    {{ config('app.name') }} &middot; Invoice Catering Kelas {{ $className }} &middot; {{ $periodLabel }}
    <br>
    Invoice ini dihasilkan otomatis dari data absensi yang telah disimpan.
</div>

</body>
</html>
