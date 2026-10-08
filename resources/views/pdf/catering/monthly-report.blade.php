<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Bulanan Catering</title>
    <style>
        @page { size: A4 landscape; margin: 20px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 8px; }
        h1 { margin: 0; font-size: 16px; color: #0f172a; }
        h2 { margin: 16px 0 6px; font-size: 11px; color: #0f172a; }
        p { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; }
        th { background: #f1f5f9; color: #475569; text-align: left; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #64748b; }
        .summary { width: 48%; }
        .empty { padding: 16px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <h1>Laporan Bulanan Catering</h1>
    <p><strong>Periode:</strong> {{ $periodLabel }}</p>
    <p class="muted"><strong>Filter:</strong> {{ $filterLabel }}</p>

    <h2>Ringkasan</h2>
    <table class="summary">
        <thead><tr><th>Keterangan</th><th class="right">Jumlah</th></tr></thead>
        <tbody>
            <tr><td>Total Tagihan</td><td class="right">Rp {{ number_format($summary['gross_amount'], 0, ',', '.') }}</td></tr>
            <tr><td>Jumlah Uang Masuk</td><td class="right">Rp {{ number_format($summary['money_in'], 0, ',', '.') }}</td></tr>
            <tr><td>Kredit Digunakan</td><td class="right">Rp {{ number_format($summary['credit_used'], 0, ',', '.') }}</td></tr>
            <tr><td>Total Tunggakan</td><td class="right">Rp {{ number_format($summary['outstanding_amount'], 0, ',', '.') }}</td></tr>
            <tr><td>Total Lebih Bayar</td><td class="right">Rp {{ number_format($summary['overpayment_amount'], 0, ',', '.') }}</td></tr>
            <tr><td>Jumlah Lunas</td><td class="right">{{ $summary['paid_count'] }} peserta</td></tr>
            <tr><td>Jumlah Sebagian</td><td class="right">{{ $summary['partial_count'] }} peserta</td></tr>
            <tr><td>Jumlah Belum Bayar</td><td class="right">{{ $summary['unpaid_count'] }} peserta</td></tr>
        </tbody>
    </table>

    <h2>Rekap Per Kelas / Kelompok</h2>
    <table>
        <thead><tr><th class="center">No</th><th>Kelas / Kelompok</th><th class="center">Peserta</th><th class="right">Total Tagihan</th><th class="right">Uang Masuk</th><th class="right">Kredit Digunakan</th><th class="right">Tunggakan</th><th class="right">Lebih Bayar</th><th class="center">Lunas</th><th class="center">Sebagian</th><th class="center">Belum Bayar</th></tr></thead>
        <tbody>
            @forelse ($groups as $index => $group)
                <tr><td class="center">{{ $index + 1 }}</td><td>{{ $group['label'] }}</td><td class="center">{{ $group['participant_count'] }}</td><td class="right">Rp {{ number_format($group['gross_amount'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($group['money_in'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($group['credit_used'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($group['outstanding_amount'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($group['overpayment_amount'], 0, ',', '.') }}</td><td class="center">{{ $group['paid_count'] }}</td><td class="center">{{ $group['partial_count'] }}</td><td class="center">{{ $group['unpaid_count'] }}</td></tr>
            @empty
                <tr><td colspan="11" class="empty">Belum ada data laporan untuk periode/filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detail Pembayaran</h2>
    <table>
        <thead><tr><th class="center">No</th><th>Peserta</th><th>Kelas / Kelompok</th><th>Kategori</th><th class="right">Tagihan</th><th class="right">Terbayar</th><th class="right">Kredit Dipakai</th><th class="right">Lebih Bayar</th><th class="right">Sisa</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($details as $index => $detail)
                <tr><td class="center">{{ $index + 1 }}</td><td>{{ $detail['member_name'] }}</td><td>{{ $detail['group_label'] }}</td><td>{{ $detail['category_name'] }}</td><td class="right">Rp {{ number_format($detail['gross_amount'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($detail['paid_amount'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($detail['credit_applied_amount'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($detail['generated_credit_amount'], 0, ',', '.') }}</td><td class="right">Rp {{ number_format($detail['outstanding_amount'], 0, ',', '.') }}</td><td>{{ $detail['payment_status_label'] }}</td></tr>
            @empty
                <tr><td colspan="10" class="empty">Belum ada data laporan untuk periode/filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
