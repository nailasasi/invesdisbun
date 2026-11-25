<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Eksekutif Pengelolaan BMD - {{ $periode['judul_periode'] }}</title>
    <style>
        @page {
            margin: 12mm 14mm 14mm 14mm;
            size: a4 landscape;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.35;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* KOP RESMI PEMPROV JATIM */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .kop-logo-cell {
            width: 75px;
            vertical-align: middle;
            text-align: center;
        }
        .kop-logo {
            max-height: 60px;
            max-width: 65px;
        }
        .kop-text-cell {
            vertical-align: middle;
            text-align: center;
        }
        .kop-instansi {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.8px;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
        }
        .kop-dinas {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #065f46; /* Emerald 800 */
            margin: 2px 0 3px 0;
            text-transform: uppercase;
        }
        .kop-alamat {
            font-size: 8pt;
            color: #334155;
            margin: 0;
            line-height: 1.25;
        }
        .kop-divider {
            border-top: 2.5px solid #065f46;
            border-bottom: 1px solid #047857;
            height: 3px;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        /* DOKUMEN METADATA BANNER */
        .doc-banner {
            background-color: #ecfdf5; /* Emerald 50 */
            border: 1px solid #a7f3d0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            width: 100%;
            box-sizing: border-box;
        }
        .doc-banner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .doc-title {
            font-size: 12pt;
            font-weight: bold;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0 0 3px 0;
        }
        .doc-subtitle {
            font-size: 8.5pt;
            color: #047857;
            font-weight: 600;
            margin: 0;
        }
        .doc-meta-label {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .doc-meta-val {
            font-size: 8.5pt;
            color: #0f172a;
            font-weight: bold;
        }

        /* OPINI / MANAJERIAL SUMMARY BOX */
        .executive-box {
            background-color: #f8fafc;
            border-left: 4px solid #047857;
            border-top: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 8.5pt;
            line-height: 1.4;
            color: #334155;
        }
        .executive-box-title {
            font-size: 9pt;
            font-weight: bold;
            color: #065f46;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        /* SEKSI & JUDUL */
        .section-header {
            background-color: #065f46;
            color: #ffffff;
            font-size: 8.5pt;
            font-weight: bold;
            padding: 4px 8px;
            margin-top: 10px;
            margin-bottom: 5px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .section-subheader {
            font-size: 8pt;
            font-weight: bold;
            color: #047857;
            margin: 4px 0 2px 0;
            text-transform: uppercase;
        }

        /* TABEL RESMI */
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8pt;
        }
        .table-data th {
            background-color: #065f46;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 5px 4px;
            border: 1px solid #047857;
            font-size: 7.5pt;
        }
        .table-data th.sub-th {
            background-color: #047857;
            border: 1px solid #065f46;
        }
        .table-data td {
            padding: 4px 5px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .table-data tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .table-data tr.row-total td {
            background-color: #e2e8f0;
            font-weight: bold;
            border-top: 1.5px solid #64748b;
            border-bottom: 2px solid #0f172a;
        }
        .table-data tr.row-highlight td {
            background-color: #f0fdf4;
            font-weight: bold;
        }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .nowrap { white-space: nowrap; }

        .table-data th.text-right,
        .table-data td.text-right {
            text-align: right;
            padding-right: 8px;
        }
        .table-data th.text-left,
        .table-data td.text-left {
            text-align: left;
            padding-left: 8px;
        }

        /* GRID / 2 KOLOM */
        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 8px;
        }
        .layout-table td {
            border: none;
            vertical-align: top;
            padding: 0;
        }

        /* BADGES */
        .badge-green { color: #047857; font-weight: bold; }
        .badge-orange { color: #d97706; font-weight: bold; }
        .badge-red { color: #dc2626; font-weight: bold; }
        .badge-gray { color: #64748b; font-weight: bold; }

        /* CATATAN KAKI & DISCLAIMER */
        .notes-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.35;
            margin-top: 6px;
            margin-bottom: 8px;
        }
        .notes-box strong {
            color: #334155;
        }

        /* TANDA TANGAN */
        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-top: 14px;
            page-break-inside: avoid;
        }
        .ttd-table td {
            border: none;
            vertical-align: top;
            text-align: center;
            font-size: 8.5pt;
            padding: 0 10px;
        }

        .page-break {
            page-break-before: always;
        }
        .avoid-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

    {{-- CATATAN: Penomoran footer resmi (Halaman X dari Y) dan identitas dokumen
         dirender langsung via Canvas API pada LaporanEksekutifExporter::renderPdf
         agar nomor halaman terhitung akurat di seluruh halaman cetak (1 sampai n). --}}

    {{-- KOP SURAT RESMI PEMPROV JATIM --}}
    <table class="kop-table">
        <tr>
            <td class="kop-logo-cell">
                @if ($logoJatim)
                    <img src="{{ $logoJatim }}" class="kop-logo" alt="Logo Jawa Timur">
                @endif
            </td>
            <td class="kop-text-cell">
                <div class="kop-instansi">PEMERINTAH PROVINSI JAWA TIMUR</div>
                <div class="kop-dinas">DINAS PERKEBUNAN</div>
                <div class="kop-alamat">
                    Jalan Gayung Kebonsari No. 175, Gayungan, Surabaya, Jawa Timur 60235<br>
                    Telepon: (031) 8291032, 8291033 | Pos-el: perkebunan@jatimprov.go.id | Laman: disbun.jatimprov.go.id
                </div>
            </td>
            <td class="kop-logo-cell">
                @if ($logoInvensbun)
                    <img src="{{ $logoInvensbun }}" class="kop-logo" alt="Logo Invensbun">
                @endif
            </td>
        </tr>
    </table>
    <div class="kop-divider"></div>

    {{-- BANNER METADATA DOKUMEN LAPORAN --}}
    <div class="doc-banner">
        <table class="doc-banner-table">
            <tr>
                <td style="width: 58%; vertical-align: top;">
                    <div class="doc-title">LAPORAN EKSEKUTIF PENGELOLAAN BARANG MILIK DAERAH (BMD)</div>
                    <div class="doc-subtitle">Rekapitulasi Portofolio Neraca Saldo, Kondisi Fisik, Penempatan, dan Akuntabilitas Aset</div>
                </td>
                <td style="width: 22%; vertical-align: top;">
                    <div class="doc-meta-label">Periode Laporan</div>
                    <div class="doc-meta-val">{{ $periode['judul_periode'] }}</div>
                    <div class="doc-meta-label" style="margin-top: 3px;">Tanggal Acuan Neraca</div>
                    <div class="doc-meta-val" style="color: #065f46;">{{ $periode['acuan']->translatedFormat('d F Y') }}</div>
                </td>
                <td style="width: 20%; vertical-align: top;">
                    <div class="doc-meta-label">Cakupan Wilayah / Unit</div>
                    <div class="doc-meta-val">{{ $meta['nama_unit'] }}</div>
                    <div class="doc-meta-label" style="margin-top: 3px;">Waktu Ekspor Dokumen</div>
                    <div class="doc-meta-val">{{ $waktuCetak }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- SEKSI 1: RINGKASAN EKSEKUTIF & OPINI MANAJERIAL --}}
    <div class="executive-box">
        <div class="executive-box-title">I. RINGKASAN EKSEKUTIF & TINJAUAN MANAJERIAL PORTOFOLIO ASET</div>
        <p style="margin: 0 0 4px 0;">
            Berdasarkan rekonsiliasi data Barang Milik Daerah (BMD) Dinas Perkebunan Provinsi Jawa Timur per <strong>{{ $periode['acuan']->translatedFormat('d F Y') }} (Pukul 23:59:59 WIB)</strong>,
            pada Tahun Anggaran <strong>{{ $periode['tahun'] }}</strong> tercatat realisasi <strong>Pengadaan Aset sebanyak {{ number_format($k['jumlah_aset_pengadaan'], 0, ',', '.') }} unit</strong>
            ({{ number_format($k['pengadaan_barang_count'], 0, ',', '.') }} unit Barang Inventaris dan {{ number_format($k['pengadaan_kendaraan_count'], 0, ',', '.') }} unit Kendaraan Dinas)
            dengan total belanja pengadaan sebesar <strong>Rp {{ number_format($k['nilai_pengadaan_tahun'], 0, ',', '.') }}</strong> (hanya aset yang tanggal perolehannya berada dalam tahun {{ $periode['tahun'] }}).
            Secara kumulatif, cakupan unit <strong>{{ $meta['nama_unit'] }}</strong> mengelola total aset aktif sebanyak <strong>{{ number_format($k['total_aset_aktif'], 0, ',', '.') }} unit</strong>
            dengan total nilai perolehan historis <strong>Rp {{ number_format($k['total_nilai_perolehan_aset'], 0, ',', '.') }}</strong> dan akumulasi beban penyusutan kumulatif sebesar <strong>Rp {{ number_format($k['total_akumulasi_penyusutan_gabungan'], 0, ',', '.') }}</strong>,
            menghasilkan nilai buku bersih aset bergerak sebesar <strong>Rp {{ number_format($k['total_nilai_buku_aset'], 0, ',', '.') }}</strong>
            (rasio nilai buku terhadap perolehan: <strong>{{ $k['total_nilai_perolehan_aset'] > 0 ? number_format(($k['total_nilai_buku_aset'] / $k['total_nilai_perolehan_aset']) * 100, 1, ',', '.') : '0,0' }}%</strong>).
            @if ($k['bisa_akses_tanah'])
                Ditambah portofolio Register Tanah KIB A sebanyak <strong>{{ number_format($k['total_bidang_tanah'], 0, ',', '.') }} bidang</strong> (luas {{ number_format($k['total_luas_tanah'], 0, ',', '.') }} m&sup2;) senilai <strong>Rp {{ number_format($k['total_nilai_perolehan_tanah'], 0, ',', '.') }}</strong> yang tidak disusutkan,
                sehingga total valuasi neraca saldo gabungan mencapai <strong>Rp {{ number_format($k['total_nilai_buku_gabungan'], 0, ',', '.') }}</strong>.
            @endif
        </p>
        <p style="margin: 0;">
            Dari aspek fisik, <strong>Kondisi seluruh aset aktif berdasarkan data kondisi terakhir</strong> mencatat sebanyak <strong>{{ number_format($k['kondisi_baik'], 0, ',', '.') }} unit ({{ $k['total_aset_aktif'] > 0 ? number_format(($k['kondisi_baik'] / $k['total_aset_aktif']) * 100, 1, ',', '.') : '0,0' }}%)</strong> aset berada dalam kondisi <strong>Baik</strong>,
            sementara terdapat <strong>{{ number_format($k['kondisi_rusak_ringan'], 0, ',', '.') }} unit</strong> Rusak Ringan dan <strong>{{ number_format($k['kondisi_rusak_berat'], 0, ',', '.') }} unit</strong> Rusak Berat.
            Rekap kondisi mencakup seluruh aset yang masih aktif dalam cakupan unit laporan, tanpa membatasi tahun pengadaan.
            Tingkat kepatuhan penempatan ruangan/pemegang mencapai <strong>{{ number_format($k['persentase_penempatan_pemegang'], 1, ',', '.') }}%</strong>
            ({{ number_format($k['memiliki_penempatan_pemegang_count'], 0, ',', '.') }} dari {{ number_format($k['total_aset_aktif'], 0, ',', '.') }} aset telah tercatat ruangan atau penanggung jawab pemegangnya).
        </p>
    </div>

    {{-- SEKSI 2: TABEL NERACA SALDO ASET HISTORIS & VALUASI FINANSIAL --}}
    <div class="section-header">II. NERACA SALDO ASET HISTORIS & VALUASI KEUANGAN (SAP)</div>
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 38%;" class="text-left">Indikator Neraca / Portofolio Aset</th>
                <th style="width: 12%;">Jumlah Fisik</th>
                <th style="width: 8%;">Satuan</th>
                <th style="width: 18%;" class="text-right">Nilai Finansial (Rp)</th>
                <th style="width: 20%;" class="text-left">Keterangan Metodologi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td><strong>Total Aset Bergerak (Barang &amp; Kendaraan)</strong></td>
                <td class="text-right font-bold">{{ number_format($k['total_barang_dan_kendaraan'], 0, ',', '.') }}</td>
                <td class="text-center">Unit</td>
                <td class="text-right font-bold">Rp {{ number_format($k['total_nilai_perolehan_aset'], 0, ',', '.') }}</td>
                <td>Nilai perolehan seluruh aset bergerak aktif</td>
            </tr>
            <tr>
                <td class="text-center">2</td>
                <td style="padding-left: 18px;">&bull; Barang Inventaris (Peralatan &amp; Mesin)</td>
                <td class="text-right">{{ number_format($k['total_barang'], 0, ',', '.') }}</td>
                <td class="text-center">Unit</td>
                <td class="text-right">Rp {{ number_format($grafik['nilai']['nilai_perolehan'][0] ?? 0, 0, ',', '.') }}</td>
                <td>Barang inventaris non-kendaraan</td>
            </tr>
            <tr>
                <td class="text-center">3</td>
                <td style="padding-left: 18px;">&bull; Kendaraan Dinas Bermotor Operasional</td>
                <td class="text-right">{{ number_format($k['total_kendaraan'], 0, ',', '.') }}</td>
                <td class="text-center">Unit</td>
                <td class="text-right">Rp {{ number_format($grafik['nilai']['nilai_perolehan'][1] ?? 0, 0, ',', '.') }}</td>
                <td>Armada kendaraan dinas roda 2, 3, dan 4</td>
            </tr>
            <tr>
                <td class="text-center">4</td>
                <td><strong>Akumulasi Beban Penyusutan Aset Bergerak</strong></td>
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td class="text-right" style="color: #b91c1c;">(Rp {{ number_format($k['total_akumulasi_penyusutan_gabungan'], 0, ',', '.') }})</td>
                <td>Beban penyusutan kumulatif metode garis lurus SAP</td>
            </tr>
            <tr class="row-highlight">
                <td class="text-center">5</td>
                <td><strong>NILAI BUKU BERSIH ASET BERGERAK</strong></td>
                <td class="text-right font-bold">{{ number_format($k['total_barang_dan_kendaraan'], 0, ',', '.') }}</td>
                <td class="text-center">Unit</td>
                <td class="text-right font-bold" style="color: #065f46;">Rp {{ number_format($k['total_nilai_buku_aset'], 0, ',', '.') }}</td>
                <td>Valuasi riil aset bergerak setelah dikurangi penyusutan</td>
            </tr>
            @if ($k['bisa_akses_tanah'])
                <tr>
                    <td class="text-center">6</td>
                    <td><strong>Register Tanah Daerah (KIB A)*</strong></td>
                    <td class="text-right">{{ number_format($k['total_bidang_tanah'], 0, ',', '.') }}</td>
                    <td class="text-center">Bidang</td>
                    <td class="text-right">Rp {{ number_format($k['total_nilai_perolehan_tanah'], 0, ',', '.') }}</td>
                    <td>Luas: {{ number_format($k['total_luas_tanah'], 0, ',', '.') }} m&sup2;. Tanah tidak disusutkan (Nilai Buku = Perolehan)</td>
                </tr>
                <tr class="row-total">
                    <td class="text-center">7</td>
                    <td>TOTAL VALUASI SALDO GABUNGAN NERACA</td>
                    <td class="text-center">-</td>
                    <td class="text-center">-</td>
                    <td class="text-right" style="color: #065f46;">Rp {{ number_format($k['total_nilai_buku_gabungan'], 0, ',', '.') }}</td>
                    <td>Total Nilai Buku Bersih Bergerak + Register Tanah KIB A</td>
                </tr>
            @else
                <tr class="row-total">
                    <td class="text-center">6</td>
                    <td>TOTAL VALUASI SALDO GABUNGAN NERACA</td>
                    <td class="text-center">-</td>
                    <td class="text-center">-</td>
                    <td class="text-right" style="color: #065f46;">Rp {{ number_format($k['total_nilai_buku_aset'], 0, ',', '.') }}</td>
                    <td>Total Nilai Buku Bersih (Akses register tanah dibatasi)</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- SEKSI 3: DISTRIBUSI KATEGORI ASET & TREN PENGADAAN (2 KOLOM) --}}
    <table class="layout-table">
        <tr>
            {{-- Kolom Kiri: Kategori Barang Terbesar --}}
            <td style="width: 48%; padding-right: 8px;">
                <div class="section-header">III. DISTRIBUSI KATEGORI ASET UTAMA</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 8%;">No</th>
                            <th style="width: 54%;" class="text-left">Kategori Barang Inventaris</th>
                            <th style="width: 18%;">Jumlah Fisik</th>
                            <th style="width: 20%;">Proporsi (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $katLabels = $grafik['kategori']['labels'] ?? [];
                            $katData = $grafik['kategori']['data'] ?? [];
                            $katTotal = $grafik['kategori']['total'] ?? 0;
                        @endphp
                        @forelse ($katLabels as $idx => $label)
                            @php
                                $cnt = $katData[$idx] ?? 0;
                                $pct = $katTotal > 0 ? ($cnt / $katTotal) * 100 : 0;
                            @endphp
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $label }}</td>
                                <td class="text-right">{{ number_format($cnt, 0, ',', '.') }} unit</td>
                                <td class="text-right">{{ number_format($pct, 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Tidak ada data kategori aset</td></tr>
                        @endforelse
                        <tr class="row-total">
                            <td colspan="2" class="text-center">TOTAL KATEGORI TERDATA</td>
                            <td class="text-right">{{ number_format($katTotal, 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ $katTotal > 0 ? '100,0%' : '0,0%' }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            {{-- Kolom Kanan: Tren Pengadaan 5 Tahun Terakhir --}}
            <td style="width: 52%; padding-left: 8px;">
                <div class="section-header">IV. TREN PENGADAAN 5 TAHUN TERAKHIR</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 18%;">Tahun</th>
                            <th style="width: 22%;" class="text-right">Jumlah Unit</th>
                            <th style="width: 32%;" class="text-right">Belanja Modal (Rp)</th>
                            <th style="width: 28%;" class="text-right">Rata-rata / Unit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $trenLabels = $grafik['tren_pengadaan']['labels'] ?? [];
                            $trenCounts = $grafik['tren_pengadaan']['data_count'] ?? [];
                            $trenNilai = $grafik['tren_pengadaan']['data_nilai'] ?? [];
                            $sumTrenC = 0; $sumTrenN = 0;
                        @endphp
                        @forelse ($trenLabels as $idx => $thn)
                            @php
                                $uCount = (int) ($trenCounts[$idx] ?? 0);
                                $nil = (float) ($trenNilai[$idx] ?? 0.0);
                                $avg = $uCount > 0 ? ($nil / $uCount) : 0.0;
                                $sumTrenC += $uCount;
                                $sumTrenN += $nil;
                            @endphp
                            <tr>
                                <td class="text-center font-bold">{{ $thn }}</td>
                                <td class="text-right">{{ number_format($uCount, 0, ',', '.') }} unit</td>
                                <td class="text-right">Rp {{ number_format($nil, 0, ',', '.') }}</td>
                                <td class="text-right">Rp {{ number_format($avg, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Tidak ada riwayat pengadaan terdata</td></tr>
                        @endforelse
                        <tr class="row-total">
                            <td class="text-center">TOTAL</td>
                            <td class="text-right">{{ number_format($sumTrenC, 0, ',', '.') }} unit</td>
                            <td class="text-right">Rp {{ number_format($sumTrenN, 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($sumTrenC > 0 ? ($sumTrenN / $sumTrenC) : 0, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- PAGE BREAK UNTUK TAMPILAN RESMI LANDSCAPE HALAMAN 2 --}}
    <div class="page-break"></div>

    {{-- KOP KECIL DI HALAMAN LANJUTAN --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px; border-bottom: 1.5px solid #065f46; padding-bottom: 4px;">
        <tr>
            <td style="font-size: 8pt; font-weight: bold; color: #065f46; text-transform: uppercase;">
                DINAS PERKEBUNAN PROVINSI JAWA TIMUR &mdash; LAPORAN EKSEKUTIF PENGELOLAAN BMD
            </td>
            <td style="font-size: 8pt; text-align: right; color: #64748b;">
                Periode: <strong>{{ $periode['judul_periode'] }}</strong> | Cakupan: <strong>{{ $meta['nama_unit'] }}</strong>
            </td>
        </tr>
    </table>

    {{-- SEKSI 4: ANALISIS NILAI FINANSIAL & MASA MANFAAT SAP (2 KOLOM) --}}
    <table class="layout-table">
        <tr>
            {{-- Kolom Kiri: Rekapitulasi Finansial Portofolio --}}
            <td style="width: 52%; padding-right: 8px;">
                <div class="section-header">V. REKAPITULASI VALUASI FINANSIAL &amp; AKUMULASI PENYUSUTAN</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 26%;" class="text-left">Kelompok Portofolio</th>
                            <th style="width: 25%;" class="text-right">Nilai Perolehan</th>
                            <th style="width: 25%;" class="text-right">Akumulasi Depresiasi</th>
                            <th style="width: 24%;" class="text-right">Nilai Buku Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $npBarang = $grafik['nilai']['nilai_perolehan'][0] ?? 0;
                            $nbBarang = $grafik['nilai']['nilai_buku'][0] ?? 0;
                            $depBarang = max(0, $npBarang - $nbBarang);

                            $npKend = $grafik['nilai']['nilai_perolehan'][1] ?? 0;
                            $nbKend = $grafik['nilai']['nilai_buku'][1] ?? 0;
                            $depKend = max(0, $npKend - $nbKend);

                            $totNpAset = $k['total_nilai_perolehan_aset'];
                            $totDepAset = $k['total_akumulasi_penyusutan_gabungan'];
                            $totNbAset = $k['total_nilai_buku_aset'];
                        @endphp
                        <tr>
                            <td>Barang Inventaris</td>
                            <td class="text-right">Rp {{ number_format($npBarang, 0, ',', '.') }}</td>
                            <td class="text-right" style="color: #b91c1c;">Rp {{ number_format($depBarang, 0, ',', '.') }}</td>
                            <td class="text-right font-bold">Rp {{ number_format($nbBarang, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Kendaraan Dinas</td>
                            <td class="text-right">Rp {{ number_format($npKend, 0, ',', '.') }}</td>
                            <td class="text-right" style="color: #b91c1c;">Rp {{ number_format($depKend, 0, ',', '.') }}</td>
                            <td class="text-right font-bold">Rp {{ number_format($nbKend, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="row-highlight">
                            <td><strong>Total Aset Bergerak</strong></td>
                            <td class="text-right font-bold">Rp {{ number_format($totNpAset, 0, ',', '.') }}</td>
                            <td class="text-right font-bold" style="color: #b91c1c;">Rp {{ number_format($totDepAset, 0, ',', '.') }}</td>
                            <td class="text-right font-bold" style="color: #065f46;">Rp {{ number_format($totNbAset, 0, ',', '.') }}</td>
                        </tr>
                        @if ($k['bisa_akses_tanah'])
                            <tr>
                                <td>Register KIB A Tanah*</td>
                                <td class="text-right">Rp {{ number_format($k['total_nilai_perolehan_tanah'], 0, ',', '.') }}</td>
                                <td class="text-right">-</td>
                                <td class="text-right font-bold">Rp {{ number_format($k['total_nilai_perolehan_tanah'], 0, ',', '.') }}</td>
                            </tr>
                            <tr class="row-total">
                                <td>TOTAL PORTOFOLIO</td>
                                <td class="text-right">Rp {{ number_format($k['total_nilai_perolehan_gabungan'], 0, ',', '.') }}</td>
                                <td class="text-right" style="color: #b91c1c;">Rp {{ number_format($totDepAset, 0, ',', '.') }}</td>
                                <td class="text-right" style="color: #065f46;">Rp {{ number_format($k['total_nilai_buku_gabungan'], 0, ',', '.') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </td>

            {{-- Kolom Kanan: Analisis Masa Manfaat SAP & Depresiasi --}}
            <td style="width: 48%; padding-left: 8px;">
                <div class="section-header">VI. ANALISIS STATUS MASA MANFAAT (SAP GARIS LURUS)</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 38%;" class="text-left">Kategori Masa Manfaat</th>
                            <th style="width: 16%;" class="text-right">Jumlah Fisik</th>
                            <th style="width: 14%;" class="text-right">Proporsi</th>
                            <th style="width: 32%;" class="text-left">Keterangan Finansial</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totMasa = $k['total_barang_dan_kendaraan'];
                            $pctHabis = $totMasa > 0 ? ($k['masa_habis'] / $totMasa) * 100 : 0;
                            $pctKritis = $totMasa > 0 ? ($k['masa_kritis'] / $totMasa) * 100 : 0;
                            $pctNormal = $totMasa > 0 ? ($k['masa_normal'] / $totMasa) * 100 : 0;
                            $pctNan = $totMasa > 0 ? ($k['masa_nan'] / $totMasa) * 100 : 0;
                        @endphp
                        <tr>
                            <td class="text-left"><strong style="color: #b91c1c;">Habis Masa Manfaat</strong> (Telah Susut Penuh)</td>
                            <td class="text-right font-bold nowrap">{{ number_format($k['masa_habis'], 0, ',', '.') }} unit</td>
                            <td class="text-right nowrap">{{ number_format($pctHabis, 1, ',', '.') }}%</td>
                            <td class="text-left">Nilai buku residu / nol</td>
                        </tr>
                        <tr>
                            <td class="text-left"><strong style="color: #d97706;">Masa Kritis</strong> (Sisa <= 1 Tahun)</td>
                            <td class="text-right font-bold nowrap">{{ number_format($k['masa_kritis'], 0, ',', '.') }} unit</td>
                            <td class="text-right nowrap">{{ number_format($pctKritis, 1, ',', '.') }}%</td>
                            <td class="text-left">Mendekati habis depresiasi</td>
                        </tr>
                        <tr>
                            <td class="text-left"><strong style="color: #047857;">Masa Normal</strong> (Sisa > 1 Tahun)</td>
                            <td class="text-right font-bold nowrap">{{ number_format($k['masa_normal'], 0, ',', '.') }} unit</td>
                            <td class="text-right nowrap">{{ number_format($pctNormal, 1, ',', '.') }}%</td>
                            <td class="text-left">Penyusutan rutin berjalan</td>
                        </tr>
                        <tr>
                            <td class="text-left"><span style="color: #64748b;">Tanpa Ketetapan Masa Manfaat</span></td>
                            <td class="text-right nowrap">{{ number_format($k['masa_nan'], 0, ',', '.') }} unit</td>
                            <td class="text-right nowrap">{{ number_format($pctNan, 1, ',', '.') }}%</td>
                            <td class="text-left">Aset tanpa batas susut</td>
                        </tr>
                        <tr class="row-total">
                            <td class="text-left font-bold">TOTAL ASET BERGERAK</td>
                            <td class="text-right font-bold nowrap">{{ number_format($totMasa, 0, ',', '.') }} unit</td>
                            <td class="text-right font-bold nowrap">{{ $totMasa > 0 ? '100,0%' : '0,0%' }}</td>
                            <td class="text-left">Populasi aset bergerak</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- SEKSI 5: KONDISI FISIK ASET & PROGRES SENSUS 3-TAHUNAN (2 KOLOM) --}}
    <table class="layout-table">
        <tr>
            {{-- Kolom Kiri: Kondisi Fisik Master Aset --}}
            <td style="width: 50%; padding-right: 8px;">
                <div class="section-header">VII. RINGKASAN KONDISI FISIK SELURUH ASET AKTIF</div>
                <div style="font-size: 7.2pt; color: #065f46; font-style: italic; margin: 2px 0 4px 0;">Kondisi seluruh aset aktif berdasarkan data kondisi terakhir.</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 36%;" class="text-left">Kategori Kondisi Fisik</th>
                            <th style="width: 18%;">Jumlah Fisik</th>
                            <th style="width: 16%;">Proporsi</th>
                            <th style="width: 30%;" class="text-left">Implikasi Pengelolaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totKond = $k['total_aset_aktif'];
                            $pctB = $totKond > 0 ? ($k['kondisi_baik'] / $totKond) * 100 : 0;
                            $pctRR = $totKond > 0 ? ($k['kondisi_rusak_ringan'] / $totKond) * 100 : 0;
                            $pctRB = $totKond > 0 ? ($k['kondisi_rusak_berat'] / $totKond) * 100 : 0;
                            $pctL = $totKond > 0 ? ($k['kondisi_lainnya'] / $totKond) * 100 : 0;
                        @endphp
                        <tr>
                            <td><span class="badge-green">&bull; BAIK (B)</span></td>
                            <td class="text-right font-bold">{{ number_format($k['kondisi_baik'], 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ number_format($pctB, 1, ',', '.') }}%</td>
                            <td>Siap operasional optimal</td>
                        </tr>
                        <tr>
                            <td><span class="badge-orange">&bull; RUSAK RINGAN (RR)</span></td>
                            <td class="text-right font-bold">{{ number_format($k['kondisi_rusak_ringan'], 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ number_format($pctRR, 1, ',', '.') }}%</td>
                            <td>Perlu pemeliharaan berkala</td>
                        </tr>
                        <tr>
                            <td><span class="badge-red">&bull; RUSAK BERAT (RB)</span></td>
                            <td class="text-right font-bold">{{ number_format($k['kondisi_rusak_berat'], 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ number_format($pctRB, 1, ',', '.') }}%</td>
                            <td>Potensi usulan penghapusan</td>
                        </tr>
                        <tr>
                            <td><span class="badge-gray">&bull; BELUM DIISI / LAINNYA</span></td>
                            <td class="text-right">{{ number_format($k['kondisi_lainnya'], 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ number_format($pctL, 1, ',', '.') }}%</td>
                            <td>Perlu pembaruan master data</td>
                        </tr>
                        <tr class="row-total">
                            <td class="text-center">TOTAL KONSOLIDASI FISIK</td>
                            <td class="text-right">{{ number_format($totKond, 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ $totKond > 0 ? '100,0%' : '0,0%' }}</td>
                            <td>Kondisi seluruh aset aktif</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            {{-- Kolom Kanan: Sensus Fisik Siklus 3-Tahunan --}}
            <td style="width: 50%; padding-left: 8px;">
                <div class="section-header">VIII. PROGRES SENSUS FISIK SIKLUS 3-TAHUNAN</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 44%;" class="text-left">Indikator Sensus Fisik</th>
                            <th style="width: 18%;">Jumlah Fisik</th>
                            <th style="width: 16%;">Capaian</th>
                            <th style="width: 22%;" class="text-left">Status Siklus Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Target Wajib Sensus</td>
                            <td class="text-right">{{ number_format($k['sensus_wajib_count'], 0, ',', '.') }} unit</td>
                            <td class="text-right">100,0%</td>
                            <td>Siklus Aktif: <strong>{{ $k['siklus_sensus_aktif'] ?? 'Berjalan' }}</strong></td>
                        </tr>
                        <tr class="row-highlight">
                            <td><strong>Realisasi Telah Disensus</strong></td>
                            <td class="text-right font-bold" style="color: #047857;">{{ number_format($k['sensus_sudah_count'], 0, ',', '.') }} unit</td>
                            <td class="text-right font-bold" style="color: #047857;">{{ number_format($k['sensus_persentase'], 1, ',', '.') }}%</td>
                            <td>Fisik terverifikasi lapangan</td>
                        </tr>
                        <tr>
                            <td>Belum Diverifikasi Sensus</td>
                            <td class="text-right" style="color: #b91c1c;">{{ number_format($k['sensus_belum_count'], 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ $k['sensus_wajib_count'] > 0 ? number_format(max(0, 100 - $k['sensus_persentase']), 1, ',', '.') . '%' : '0,0%' }}</td>
                            <td>Menunggu jadwal verifikasi</td>
                        </tr>
                        <tr>
                            <td colspan="4" style="font-size: 7.5pt; color: #475569; background-color: #f1f5f9; padding: 4px 6px;">
                                <strong>Hasil Sensus Terverifikasi:</strong>
                                Baik: <strong>{{ number_format($k['sensus_baik'], 0, ',', '.') }}</strong> |
                                Rusak Ringan: <strong>{{ number_format($k['sensus_rusak_ringan'], 0, ',', '.') }}</strong> |
                                Rusak Berat: <strong>{{ number_format($k['sensus_rusak_berat'], 0, ',', '.') }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- PAGE BREAK UNTUK REKAPITULASI LOKASI HALAMAN 3 --}}
    <div class="page-break"></div>

    {{-- KOP KECIL DI HALAMAN LANJUTAN --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px; border-bottom: 1.5px solid #065f46; padding-bottom: 4px;">
        <tr>
            <td style="font-size: 8pt; font-weight: bold; color: #065f46; text-transform: uppercase;">
                DINAS PERKEBUNAN PROVINSI JAWA TIMUR &mdash; LAPORAN EKSEKUTIF PENGELOLAAN BMD
            </td>
            <td style="font-size: 8pt; text-align: right; color: #64748b;">
                Periode: <strong>{{ $periode['judul_periode'] }}</strong> | Cakupan: <strong>{{ $meta['nama_unit'] }}</strong>
            </td>
        </tr>
    </table>

    {{-- SEKSI 6: REKAPITULASI PENEMPATAN LOKASI FISIK & STATUS RUANGAN/PEMEGANG --}}
    <div class="section-header">IX. DISTRIBUSI LOKASI FISIK ASET &amp; STATUS KONTROL PENEMPATAN (GROUPED BARANG VS KENDARAAN)</div>
    <table class="table-data">
        <thead>
            <tr>
                <th rowspan="2" style="width: 3%;">No</th>
                <th rowspan="2" style="width: 17%;" class="text-left">Lokasi Fisik Aset</th>
                <th colspan="5" class="sub-th">BARANG INVENTARIS (KONDISI FISIK)</th>
                <th colspan="5" class="sub-th" style="background-color: #065f46;">KENDARAAN BERMOTOR (KONDISI FISIK)</th>
                <th rowspan="2" style="width: 6%;">Total Aset</th>
                <th colspan="2" class="sub-th" style="background-color: #0f766e;">STATUS RUANGAN</th>
                <th colspan="2" class="sub-th" style="background-color: #1e3a8a;">STATUS PEMEGANG</th>
            </tr>
            <tr>
                {{-- Barang C-G --}}
                <th style="width: 4%;">Baik</th>
                <th style="width: 4%;">RR</th>
                <th style="width: 4%;">RB</th>
                <th style="width: 4%;">Blm</th>
                <th style="width: 5%;">Subtot</th>
                {{-- Kendaraan H-L --}}
                <th style="width: 4%;">Baik</th>
                <th style="width: 4%;">RR</th>
                <th style="width: 4%;">RB</th>
                <th style="width: 4%;">Blm</th>
                <th style="width: 5%;">Subtot</th>
                {{-- Ruangan N-O --}}
                <th style="width: 6%;">Ada</th>
                <th style="width: 6%;">Tanpa</th>
                {{-- Pemegang P-Q --}}
                <th style="width: 6%;">Ada</th>
                <th style="width: 6%;">Tanpa</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lokasiData = $grafik['kondisi_unit']['ringkasan']['per_lokasi'] ?? [];
                $totAllB = 0; $totAllK = 0; $totAllAset = 0;
                $totAdaR = 0; $totTanpaR = 0; $totAdaP = 0; $totTanpaP = 0;

                $sumBBaik = 0; $sumBRR = 0; $sumBRB = 0; $sumBBlm = 0;
                $sumKBaik = 0; $sumKRR = 0; $sumKRB = 0; $sumKBlm = 0;
            @endphp
            @forelse ($lokasiData as $idx => $rLok)
                @php
                    $bBaik = $grafik['kondisi_unit']['barang_baik'][$idx] ?? 0;
                    $bRR = $grafik['kondisi_unit']['barang_rusak_ringan'][$idx] ?? 0;
                    $bRB = $grafik['kondisi_unit']['barang_rusak_berat'][$idx] ?? 0;
                    $bBlm = $grafik['kondisi_unit']['barang_belum_diisi'][$idx] ?? 0;
                    $bSub = $rLok['barang_count'] ?? ($bBaik + $bRR + $bRB + $bBlm);

                    $kBaik = $grafik['kondisi_unit']['kendaraan_baik'][$idx] ?? 0;
                    $kRR = $grafik['kondisi_unit']['kendaraan_rusak_ringan'][$idx] ?? 0;
                    $kRB = $grafik['kondisi_unit']['kendaraan_rusak_berat'][$idx] ?? 0;
                    $kBlm = $grafik['kondisi_unit']['kendaraan_belum_diisi'][$idx] ?? 0;
                    $kSub = $rLok['kendaraan_count'] ?? ($kBaik + $kRR + $kRB + $kBlm);

                    $totLok = $rLok['total_aset'] ?? ($bSub + $kSub);
                    $adaRuang = $rLok['dengan_ruangan'] ?? 0;
                    $tanpaRuang = $rLok['tanpa_ruangan'] ?? 0;
                    $adaPemegang = $rLok['dengan_pemegang'] ?? 0;
                    $tanpaPemegang = $rLok['tanpa_pemegang'] ?? 0;

                    $sumBBaik += $bBaik; $sumBRR += $bRR; $sumBRB += $bRB; $sumBBlm += $bBlm;
                    $sumKBaik += $kBaik; $sumKRR += $kRR; $sumKRB += $kRB; $sumKBlm += $kBlm;
                    $totAllB += $bSub; $totAllK += $kSub; $totAllAset += $totLok;
                    $totAdaR += $adaRuang; $totTanpaR += $tanpaRuang;
                    $totAdaP += $adaPemegang; $totTanpaP += $tanpaPemegang;
                @endphp
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="font-bold">{{ $rLok['lokasi'] }}</td>
                    {{-- Barang --}}
                    <td class="text-right">{{ $bBaik }}</td>
                    <td class="text-right">{{ $bRR }}</td>
                    <td class="text-right">{{ $bRB }}</td>
                    <td class="text-right">{{ $bBlm }}</td>
                    <td class="text-right font-bold" style="background-color: #ecfdf5;">{{ $bSub }}</td>
                    {{-- Kendaraan --}}
                    <td class="text-right">{{ $kBaik }}</td>
                    <td class="text-right">{{ $kRR }}</td>
                    <td class="text-right">{{ $kRB }}</td>
                    <td class="text-right">{{ $kBlm }}</td>
                    <td class="text-right font-bold" style="background-color: #f0fdf4;">{{ $kSub }}</td>
                    {{-- Total Aset Lokasi --}}
                    <td class="text-right font-bold" style="background-color: #e2e8f0;">{{ $totLok }}</td>
                    {{-- Ruangan --}}
                    <td class="text-right">{{ $adaRuang }}</td>
                    <td class="text-right" style="color: {{ $tanpaRuang > 0 ? '#b91c1c' : '#64748b' }};">{{ $tanpaRuang }}</td>
                    {{-- Pemegang --}}
                    <td class="text-right">{{ $adaPemegang }}</td>
                    <td class="text-right" style="color: {{ $tanpaPemegang > 0 ? '#b91c1c' : '#64748b' }};">{{ $tanpaPemegang }}</td>
                </tr>
            @empty
                <tr><td colspan="17" class="text-center text-muted">Tidak ada data sebaran lokasi aset</td></tr>
            @endforelse
            <tr class="row-total">
                <td colspan="2" class="text-center">TOTAL KONSOLIDASI</td>
                {{-- Total Barang --}}
                <td class="text-right">{{ $sumBBaik }}</td>
                <td class="text-right">{{ $sumBRR }}</td>
                <td class="text-right">{{ $sumBRB }}</td>
                <td class="text-right">{{ $sumBBlm }}</td>
                <td class="text-right font-bold">{{ $totAllB }}</td>
                {{-- Total Kendaraan --}}
                <td class="text-right">{{ $sumKBaik }}</td>
                <td class="text-right">{{ $sumKRR }}</td>
                <td class="text-right">{{ $sumKRB }}</td>
                <td class="text-right">{{ $sumKBlm }}</td>
                <td class="text-right font-bold">{{ $totAllK }}</td>
                {{-- Grand Total --}}
                <td class="text-right font-bold" style="color: #065f46;">{{ $totAllAset }}</td>
                {{-- Ruangan & Pemegang --}}
                <td class="text-right">{{ $totAdaR }}</td>
                <td class="text-right">{{ $totTanpaR }}</td>
                <td class="text-right">{{ $totAdaP }}</td>
                <td class="text-right">{{ $totTanpaP }}</td>
            </tr>
        </tbody>
    </table>
    <div style="font-size: 7.2pt; color: #64748b; font-style: italic; margin-bottom: 8px;">
        * Lokasi fisik murni menggunakan aset.id_lokasi. Khusus armada kendaraan bermotor menggunakan lokasi master dengan fallback lokasi ruangan pemegang aktif. Register Tanah KIB A disajikan terpisah dari tabel ini.
    </div>

    {{-- SEKSI 7 & 8: AKTIVITAS ASET & PAJAK KENDARAAN (2 KOLOM) --}}
    <table class="layout-table">
        <tr>
            {{-- Kolom Kiri: Aktivitas Mutasi & Pengadaan --}}
            <td style="width: 50%; padding-right: 8px;">
                <div class="section-header">X. AKTIVITAS PENGADAAN &amp; MUTASI ASET (FLOW PERIODE)</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 8%;">No</th>
                            <th style="width: 42%;" class="text-left">Interval Waktu / Bulan</th>
                            <th style="width: 25%;">Pengadaan Baru</th>
                            <th style="width: 25%;">Mutasi Aset</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $aktLabels = $grafik['aktivitas']['labels'] ?? [];
                            $aktPengadaan = $grafik['aktivitas']['pengadaan'] ?? [];
                            $aktMutasi = $grafik['aktivitas']['mutasi'] ?? [];
                            $totAktP = 0; $totAktM = 0;
                        @endphp
                        @forelse ($aktLabels as $idx => $lbl)
                            @php
                                $p = $aktPengadaan[$idx] ?? 0;
                                $m = $aktMutasi[$idx] ?? 0;
                                $totAktP += $p; $totAktM += $m;
                            @endphp
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $lbl }}</td>
                                <td class="text-right">{{ number_format($p, 0, ',', '.') }} unit</td>
                                <td class="text-right">{{ number_format($m, 0, ',', '.') }} transaksi</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Tidak ada aktivitas pada periode ini</td></tr>
                        @endforelse
                        <tr class="row-total">
                            <td colspan="2" class="text-center">TOTAL AKTIVITAS</td>
                            <td class="text-right">{{ number_format($totAktP, 0, ',', '.') }} unit</td>
                            <td class="text-right">{{ number_format($totAktM, 0, ',', '.') }} transaksi</td>
                        </tr>
                        <tr>
                            <td colspan="4" style="background-color: #f8fafc; font-size: 7.5pt; color: #475569;">
                                Total Realisasi Belanja Modal Pengadaan Baru: <strong>Rp {{ number_format($k['pengadaan_baru_nilai'], 0, ',', '.') }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>

            {{-- Kolom Kanan: Realisasi & Kepatuhan Pajak Kendaraan --}}
            <td style="width: 50%; padding-left: 8px;">
                <div class="section-header">XI. REALISASI &amp; KEPATUHAN PAJAK KENDARAAN DINAS</div>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th style="width: 48%;" class="text-left">Indikator Pajak Kendaraan Bermotor</th>
                            <th style="width: 26%;" class="text-right">Realisasi</th>
                            <th style="width: 26%;" class="text-left">Satuan / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-left">Pembayaran Pajak Dalam Periode (Flow)</td>
                            <td class="text-right font-bold nowrap">{{ number_format($k['pajak_flow_transaksi_count'], 0, ',', '.') }}</td>
                            <td class="text-left">Transaksi Terbayar</td>
                        </tr>
                        <tr>
                            <td class="text-left">Jumlah Unit Kendaraan Terbayar Pajak</td>
                            <td class="text-right font-bold nowrap">{{ number_format($k['pajak_flow_kendaraan_count'], 0, ',', '.') }}</td>
                            <td class="text-left">Unit Armada</td>
                        </tr>
                        <tr>
                            <td class="text-left">Total Realisasi Belanja Pajak (PKB/SWDKLLJ)</td>
                            <td class="text-right font-bold nowrap" style="color: #065f46;">Rp {{ number_format($k['pajak_flow_total_nominal'], 0, ',', '.') }}</td>
                            <td class="text-left">Rupiah Terealisasi</td>
                        </tr>
                        <tr style="background-color: #f1f5f9;">
                            <td colspan="3" style="font-weight: bold; color: #0f172a; font-size: 7.5pt;" class="text-left">
                                Status Kepatuhan Masa Berlaku Pajak (Snapshot per {{ $periode['acuan']->translatedFormat('d M Y') }}):
                            </td>
                        </tr>
                        <tr>
                            <td class="text-left">&bull; Masa Pajak Aman / Aktif (> 30 Hari)</td>
                            <td class="text-right nowrap" style="color: #047857;">{{ number_format($k['pajak_snapshot_aman'], 0, ',', '.') }}</td>
                            <td class="text-left">Unit Patuh</td>
                        </tr>
                        <tr>
                            <td class="text-left">&bull; Jatuh Tempo Kritis (<= 30 Hari)</td>
                            <td class="text-right nowrap" style="color: #d97706;">{{ number_format($k['pajak_snapshot_kritis'], 0, ',', '.') }}</td>
                            <td class="text-left">Unit Perlu Perhatian</td>
                        </tr>
                        <tr>
                            <td class="text-left">&bull; Terlambat / Menunggak Pajak</td>
                            <td class="text-right nowrap" style="color: #b91c1c;">{{ number_format($k['pajak_snapshot_terlambat'], 0, ',', '.') }}</td>
                            <td class="text-left">Unit Menunggak</td>
                        </tr>
                        <tr>
                            <td class="text-left">&bull; Belum Ada Data Masa Pajak</td>
                            <td class="text-right nowrap" style="color: #64748b;">{{ number_format($k['pajak_snapshot_tanpa_data'], 0, ',', '.') }}</td>
                            <td class="text-left">Unit Belum Terdata</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- SEKSI 9: METODOLOGI & CATATAN KETERBATASAN DATA --}}
    <div class="avoid-break">
        <div class="notes-box">
            <strong>XII. METODOLOGI PERHITUNGAN &amp; CATATAN KETERBATASAN DATA (DISCLAIMER):</strong>
            <ol style="margin: 2px 0 0 16px; padding: 0;">
                <li><strong>Rekonstruksi Saldo Neraca Historis (Opsi A SAP):</strong> Angka saldo aset merekonstruksi data aset yang tanggal perolehannya <= tanggal acuan laporan ({{ $periode['acuan']->translatedFormat('d F Y') }} pukul 23:59:59 WIB) dan belum dihapuskan sebelum tanggal tersebut (atau berstatus dihapuskan setelah tanggal acuan).</li>
                <li><strong>Metode Depresiasi:</strong> Nilai penyusutan dihitung menggunakan metode garis lurus (straight-line) tanpa residu berdasarkan tahun penggunaan penuh (siklus tahunan sejak tanggal pengadaan) sesuai masa manfaat kelompok barang Standar Akuntansi Pemerintahan (SAP).</li>
                <li><strong>Perlakuan Tanah KIB A:</strong> Sesuai SAP, aset tanah daerah tidak disusutkan (nilai buku = nilai perolehan) dan disajikan terpisah dari aset peralatan/mesin. Hanya ditampilkan bagi pengguna berhak akses.</li>
                <li><strong>Kondisi Seluruh Aset Aktif:</strong> Kondisi seluruh aset aktif berdasarkan data kondisi terakhir (kondisi master data terkini saat laporan diunduh). Rekap kondisi mencakup seluruh aset yang masih aktif dalam cakupan unit laporan, tanpa membatasi tahun pengadaan. Aset dari tahun pengadaan sebelumnya tetap dihitung selama masih menjadi aset aktif Disbun.</li>
                <li><strong>Penentuan Lokasi Operasional:</strong> Lokasi fisik murni merujuk pada master lokasi aset. Untuk armada kendaraan operasional, jika lokasi master kosong, sistem menggunakan lokasi ruangan pemegang aktif.</li>
                <li><strong>Strictly No Drilldown:</strong> Laporan eksekutif ini merupakan dokumen agregat manajerial akuntabilitas dan secara sengaja tidak memuat tautan detail individual aset maupun identitas usulan penghapusan.</li>
            </ol>
        </div>

        {{-- SEKSI 10: TANDA TANGAN & PENGESAHAN DOKUMEN --}}
        <table class="ttd-table">
            <tr>
                <td style="width: 35%;">
                    Mengetahui,<br>
                    <strong>Pengguna Barang / Kuasa Pengguna Barang</strong><br>
                    Dinas Perkebunan Provinsi Jawa Timur
                    <br><br><br><br>
                    <u><strong>........................................................</strong></u><br>
                    NIP. ....................................................
                </td>
                <td style="width: 30%;">
                    &nbsp;
                </td>
                <td style="width: 35%;">
                    Surabaya, {{ $periode['acuan']->translatedFormat('d F Y') }}<br>
                    <strong>Pengurus Barang Pengguna / Pembantu</strong><br>
                    Dinas Perkebunan Provinsi Jawa Timur
                    <br><br><br><br>
                    <u><strong>........................................................</strong></u><br>
                    NIP. ....................................................
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
