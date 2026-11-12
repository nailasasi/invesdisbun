<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label Inventaris - {{ $inventaris->kode_inventaris }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", Roboto, Arial, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
        }

        .toolbar {
            max-width: 420px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .toolbar button {
            border: 1px solid #0f766e;
            background: #0f766e;
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
        }

        .toolbar button.secondary {
            background: #fff;
            color: #334155;
            border-color: #cbd5e1;
        }

        .label {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #0f172a;
            border-radius: 12px;
            padding: 20px 22px;
        }

        .label__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .label__title {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #0f766e;
        }

        .label__kode {
            font-family: "Consolas", "Courier New", monospace;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .label__row {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 7px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .label__row:last-child { border-bottom: none; }

        .label__key {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .label__value {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .label { border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="secondary" onclick="window.close()">Tutup</button>
        <button type="button" onclick="window.print()">Cetak Label</button>
    </div>

    <div class="label">
        <div class="label__head">
            <span class="label__title">Inventaris Lokal Ruangan</span>
            <span class="label__kode">{{ $inventaris->kode_inventaris }}</span>
        </div>

        <div class="label__row">
            <span class="label__key">Nama Barang</span>
            <span class="label__value">{{ $inventaris->nama_barang }}</span>
        </div>
        <div class="label__row">
            <span class="label__key">Merk</span>
            <span class="label__value">{{ $inventaris->merk ?: '-' }}</span>
        </div>
        <div class="label__row">
            <span class="label__key">Ruangan</span>
            <span class="label__value">{{ $ruangan->nama_ruangan }}</span>
        </div>
        @if ($ruangan->lokasi)
        <div class="label__row">
            <span class="label__key">Lokasi</span>
            <span class="label__value">{{ $ruangan->lokasi->nama_lokasi }}</span>
        </div>
        @endif
    </div>
</body>
</html>
