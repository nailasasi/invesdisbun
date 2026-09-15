@php
    \Carbon\Carbon::setLocale('id');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SPPBI - {{ $pegawai->nama_pegawai }}</title>
    <style>
        body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.5; margin: 30px; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
        .table th, .table td { border: 1px solid #000; padding: 6px 8px; font-size: 11pt; }
        .table th { background: #f2f2f2; text-align: left; }
        .header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 20px; }
        .sign { margin-top: 40px; display: flex; justify-content: space-between; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer;">Cetak Dokumen</button>
    </div>

    <div class="header text-center">
        <h3 style="margin: 0; text-transform: uppercase;">Pemerintah Provinsi Jawa Timur</h3>
        <h2 style="margin: 0; text-transform: uppercase;">Dinas Perkebunan</h2>
        <p style="margin: 0; font-size: 10pt;">Surat Penunjukan Pemegang Barang Inventaris (SPPBI)</p>
        <p style="margin: 0; font-size: 10pt;">Nomor: {{ $sppbi->nomor_surat ?? '......./SPPBI/' . date('Y') }}</p>
    </div>

    <p>Yang bertanda tangan di bawah ini menerangkan bahwa barang inventaris dinas berikut:</p>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>Nama Barang</th>
                <th>No. Register / Kartu</th>
                <th>Merk / Tipe</th>
                <th>Kondisi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($asetList as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $item->aset->barang->nama_barang ?? '-' }}</td>
                    <td>{{ $item->aset->nomor_kartu_barang ?? '-' }}</td>
                    <td>{{ $item->aset->merk ?? '-' }}</td>
                    <td>{{ $item->aset->kondisi ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada aset terdata.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p>Diserahkan sebagai penanggung jawab pemegang barang dinas kepada:</p>
    <table style="width: 100%; margin-bottom: 20px;">
        <tr><td style="width: 150px;">Nama Pegawai</td><td>: <strong>{{ $pegawai->nama_pegawai }}</strong></td></tr>
        <tr><td>NIP</td><td>: {{ $pegawai->nip ?? '-' }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $pegawai->jabatan ?? '-' }}</td></tr>
        <tr><td>Unit Kerja / Ruangan</td><td>: {{ $pegawai->ruangan?->nama_ruangan ?? '-' }}</td></tr>
    </table>

    <div class="sign">
        <div style="text-align: center; width: 40%;">
            <p>Penerima / Pemegang,</p>
            <br><br><br>
            <p><strong>{{ $pegawai->nama_pegawai }}</strong><br>NIP. {{ $pegawai->nip ?? '........................' }}</p>
        </div>
        <div style="text-align: center; width: 40%;">
            <p>{{ $sppbi?->tanggal_surat?->translatedFormat('d F Y') ?? date('d F Y') }}<br>Pengurus Barang / Admin Aset,</p>
            <br><br><br>
            <p><strong>Achmar Adrian Ramadhan, A.Md.</strong><br>NIP. 19991223 202504 1 006</p>
        </div>
    </div>
</body>
</html>