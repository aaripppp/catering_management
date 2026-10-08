<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Absensi Catering - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1e293b; font-family: Helvetica, Arial, sans-serif; font-size: 8px; }
        h1 { margin: 0; color: #1e3a8a; font-size: 18px; }
        h2 { margin: 14px 0 5px; color: #1e40af; font-size: 11px; text-transform: uppercase; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        .meta { margin-top: 3px; color: #64748b; font-size: 9px; }
        .summary { width: 100%; margin-top: 10px; border-collapse: collapse; page-break-inside: avoid; }
        .summary td { border: 1px solid #cbd5e1; padding: 6px; text-align: center; }
        .summary .label { color: #64748b; font-size: 7px; text-transform: uppercase; }
        .summary .value { margin-top: 2px; color: #0f172a; font-size: 13px; font-weight: bold; }
        table.detail { width: 100%; border-collapse: collapse; }
        table.detail thead { display: table-header-group; }
        table.detail th { border: 1px solid #1e40af; background: #1e40af; color: white; padding: 4px 3px; font-size: 7px; text-align: center; text-transform: uppercase; }
        table.detail td { border: 1px solid #cbd5e1; padding: 3px; }
        table.detail tbody tr:nth-child(even) td { background: #f8fafc; }
        .center { text-align: center; }
        .muted { color: #64748b; }
        .inactive { color: #b45309; font-size: 7px; }
        .empty { padding: 12px !important; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rekap Absensi Catering</h1>
        <div class="meta">Periode: <strong>{{ $periodLabel }}</strong> &nbsp; | &nbsp; Filter: {{ $filterLabel }}</div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Peserta</div><div class="value">{{ $summary['participants'] }}</div></td>
            <td><div class="label">Catatan</div><div class="value">{{ $summary['total_records'] }}</div></td>
            @foreach ($statuses as $status)
                <td><div class="label">{{ $status->label() }}</div><div class="value">{{ $summary[$status->value] }}</div></td>
            @endforeach
        </tr>
    </table>

    <h2>Rekap Per Peserta</h2>
    <table class="detail">
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th style="text-align: left;">Nama</th>
                <th style="text-align: left;">Kelas / Kelompok</th>
                @foreach ($statuses as $status)
                    <th title="{{ $status->label() }}">{{ $status->shorthand() }}</th>
                @endforeach
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($participants as $participant)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>
                        {{ $participant['member_name'] }}
                        @unless ($participant['is_active']) <span class="inactive">(tidak aktif)</span> @endunless
                    </td>
                    <td>{{ $participant['group_label'] }}</td>
                    @foreach ($statuses as $status)
                        <td class="center">{{ $participant[$status->value] }}</td>
                    @endforeach
                    <td class="center"><strong>{{ $participant['total_days'] }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="13" class="empty">Tidak ada data peserta pada periode dan filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Rekap Per Kelas / Kelompok</h2>
    <table class="detail">
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th style="text-align: left;">Kelas / Kelompok</th>
                <th>Peserta</th>
                @foreach ($statuses as $status)
                    <th title="{{ $status->label() }}">{{ $status->shorthand() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $group['label'] }}</td>
                    <td class="center">{{ $group['participant_count'] }}</td>
                    @foreach ($statuses as $status)
                        <td class="center">{{ $group[$status->value] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="12" class="empty">Tidak ada data kelas atau kelompok pada periode dan filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
