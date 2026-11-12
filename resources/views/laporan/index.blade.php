@extends('layouts.app')

@section('title', 'Laporan Eksekutif - INVENSBUN')
@section('page-title', 'Laporan Eksekutif')
@section('breadcrumb')
    Dokumen &rsaquo; Laporan Eksekutif
@endsection

@section('content')
<div class="space-y-6">

    {{-- BILAH FILTER PERIODE & UNIT --}}
    <x-card :padding="false">
        <div class="p-5">
            <form method="GET" action="{{ route('laporan.index') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end">
                {{-- Jenis Laporan --}}
                <div class="w-full lg:w-48">
                    <label for="jenis" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Jenis Laporan</label>
                    <select id="jenis" name="jenis" onchange="toggleBulanInput()"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/30">
                        <option value="bulanan" {{ $jenis === 'bulanan' ? 'selected' : '' }}>Laporan Bulanan</option>
                        <option value="tahunan" {{ $jenis === 'tahunan' ? 'selected' : '' }}>Laporan Tahunan</option>
                    </select>
                </div>

                {{-- Tahun --}}
                <div class="w-full lg:w-36">
                    <label for="tahun" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tahun</label>
                    <select id="tahun" name="tahun"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/30">
                        @foreach ($daftarTahun as $t)
                            <option value="{{ $t }}" {{ $tahun === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Bulan (Otomatis Nonaktif jika Tahunan) --}}
                <div id="wrapper-bulan" class="w-full lg:w-44 {{ $jenis === 'tahunan' ? 'opacity-50 pointer-events-none' : '' }}">
                    <label for="bulan" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Bulan</label>
                    <select id="bulan" name="bulan" {{ $jenis === 'tahunan' ? 'disabled' : '' }}
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/30">
                        @foreach ($daftarBulan as $num => $nama)
                            <option value="{{ $num }}" {{ $bulan === $num ? 'selected' : '' }}>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Unit Kerja / Lokasi (Sesuai Hak Akses) --}}
                <div class="w-full lg:w-64">
                    <label for="unit" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Unit Kerja / Lokasi</label>
                    @if ($isAdminAset)
                        <select id="unit" name="unit"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/30">
                            <option value="semua" {{ $unitLokasiId === null ? 'selected' : '' }}>Semua Unit (Konsolidasi)</option>
                            @foreach ($lokasiList as $lok)
                                <option value="{{ $lok->id_lokasi }}" {{ $unitLokasiId === $lok->id_lokasi ? 'selected' : '' }}>
                                    {{ $lok->nama_lokasi }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" readonly value="{{ $namaUnitTerpilih }}"
                            class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600 shadow-sm cursor-not-allowed">
                    @endif
                </div>

                {{-- Tombol Tampilkan & Ekspor --}}
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Tampilkan
                    </button>

                    <a href="{{ route('laporan.export.excel', request()->query()) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 transition"
                        title="Unduh Laporan Format Excel (.xlsx) 6 Sheet">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Ekspor Excel
                    </a>

                    <a href="{{ route('laporan.export.pdf', request()->query()) }}" target="_blank"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800 transition"
                        title="Unduh Dokumen Laporan Resmi Format PDF A4 Landscape">
                        <svg class="h-4 w-4 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Ekspor PDF
                    </a>
                </div>
            </form>
        </div>
    </x-card>

    {{-- INFORMASI PERIODE & TANGGAL ACUAN --}}
    <div class="rounded-2xl border border-emerald-100 bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50/40 p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    {{ strtoupper($jenis) }}
                </span>
                <h1 class="text-xl font-bold text-slate-800 mt-1.5">{{ $periode['judul_periode'] }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">Cakupan Wilayah: <span class="font-semibold text-slate-700">{{ $namaUnitTerpilih }}</span></p>
            </div>
            <div class="rounded-xl bg-white/80 backdrop-blur border border-emerald-200/60 p-3 sm:text-right">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Tanggal Acuan Snapshot</p>
                <p class="text-sm font-bold text-emerald-800 mt-0.5">{{ $periode['acuan']->translatedFormat('d F Y') }}</p>
                <p class="text-[10px] text-slate-400">Pukul 23:59:59 WIB</p>
            </div>
        </div>
    </div>

    {{-- KARTU KPI UTAMA --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Barang & Kendaraan --}}
        <x-card>
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Aset Fisik</p>
                    <span class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </span>
                </div>
                <p class="text-3xl font-extrabold text-slate-800 mt-2">{{ number_format($k['total_barang_dan_kendaraan'], 0, ',', '.') }}</p>
                <div class="flex items-center gap-2 mt-2 text-xs text-slate-500">
                    <span class="font-medium text-slate-700">{{ $k['total_barang'] }} Barang</span>
                    <span>&bull;</span>
                    <span class="font-medium text-emerald-700">{{ $k['total_kendaraan'] }} Kendaraan</span>
                </div>
            </div>
        </x-card>

        {{-- Total Nilai Perolehan Gabungan --}}
        <x-card>
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Nilai Perolehan</p>
                    <span class="rounded-lg bg-blue-50 p-2 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <p class="text-2xl font-bold text-slate-800 mt-2 truncate" title="Rp {{ number_format($k['total_nilai_perolehan_gabungan'], 0, ',', '.') }}">
                    Rp {{ number_format($k['total_nilai_perolehan_gabungan'], 0, ',', '.') }}
                </p>
                <p class="text-xs text-slate-400 mt-2">Modal Pengadaan Historis</p>
            </div>
        </x-card>

        {{-- Akumulasi Penyusutan --}}
        <x-card>
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Akumulasi Penyusutan</p>
                    <span class="rounded-lg bg-amber-50 p-2 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                    </span>
                </div>
                <p class="text-2xl font-bold text-amber-700 mt-2 truncate" title="Rp {{ number_format($k['total_akumulasi_penyusutan_gabungan'], 0, ',', '.') }}">
                    Rp {{ number_format($k['total_akumulasi_penyusutan_gabungan'], 0, ',', '.') }}
                </p>
                <p class="text-xs text-slate-400 mt-2">Metode Garis Lurus (SAP)</p>
            </div>
        </x-card>

        {{-- Total Nilai Buku Bersih --}}
        <x-card>
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai Buku Bersih</p>
                    <span class="rounded-lg bg-teal-50 p-2 text-teal-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                </div>
                <p class="text-2xl font-bold text-teal-700 mt-2 truncate" title="Rp {{ number_format($k['total_nilai_buku_gabungan'], 0, ',', '.') }}">
                    Rp {{ number_format($k['total_nilai_buku_gabungan'], 0, ',', '.') }}
                </p>
                <p class="text-xs text-slate-400 mt-2">Valuasi Riil per Akhir Periode</p>
            </div>
        </x-card>
    </div>

    {{-- DUA KARTU TATA KELOLA & ARUS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Penempatan & Pemegang --}}
        <x-card>
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Status Penempatan & Pemegang</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Persentase aset yang telah memiliki ruangan atau pemegang sah</p>
                    </div>
                    <span class="text-2xl font-extrabold text-emerald-700">{{ $k['persentase_penempatan_pemegang'] }}%</span>
                </div>
                <div class="mt-4">
                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ min(100, $k['persentase_penempatan_pemegang']) }}%"></div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-3 text-xs text-slate-500">
                    <span>Terdaftar Penempatan / Pemegang: <strong class="text-slate-700">{{ $k['memiliki_penempatan_pemegang_count'] }} unit</strong></span>
                    <span>Total Aset Saldo: <strong class="text-slate-700">{{ $k['total_barang_dan_kendaraan'] }} unit</strong></span>
                </div>
            </div>
        </x-card>

        {{-- Arus Aktivitas Periode Berjalan (Flow) --}}
        <x-card>
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Aktivitas Periode Berjalan (Flow)</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Transaksi penambahan & mutasi dalam kurun {{ $periode['judul_periode'] }}</p>
                    </div>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Arus Transaksi</span>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                        <p class="text-xs text-slate-500">Pengadaan Baru</p>
                        <p class="text-lg font-bold text-slate-800 mt-0.5">{{ $k['pengadaan_baru_count'] }} Unit</p>
                        <p class="text-[11px] text-emerald-700 font-medium truncate mt-0.5">Rp {{ number_format($k['pengadaan_baru_nilai'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                        <p class="text-xs text-slate-500">Mutasi Aset Terlaksana</p>
                        <p class="text-lg font-bold text-slate-800 mt-0.5">{{ $k['mutasi_aset_count'] }} Transaksi</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Perpindahan Ruangan/Pegawai</p>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ========================================================================= --}}
    {{-- VISUALISASI GRAFIK INFORMASI EKSEKUTIF (PHASE 2)                         --}}
    {{-- Murni visual informasi (hover tooltip), tanpa aksi/navigasi drilldown.    --}}
    {{-- ========================================================================= --}}
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Visualisasi Analisis Eksekutif
                </h2>
                <p class="text-xs text-slate-500">Visualisasi terintegrasi filter periode {{ $periode['judul_periode'] }} &bull; Hover grafik untuk rincian data</p>
            </div>
            <span class="inline-flex items-center gap-1.5 self-start sm:self-auto rounded-full bg-emerald-50 border border-emerald-200/80 px-3 py-1 text-xs font-semibold text-emerald-800">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Visual Informasi &bull; Tanpa Drilldown
            </span>
        </div>

        {{-- GRID 6 GRAFIK EKSEKUTIF (2 KOLOM RESPONSIVE) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- GRAFIK A: DISTRIBUSI KONDISI ASET (DONUT CHART) --}}
            <x-card :padding="false">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">A. Distribusi Kondisi Aset</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Kondisi Fisik Terkini pada Populasi Saldo Aset Periode Laporan</p>
                    </div>
                    <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Donut</span>
                </div>
                <div class="p-5">
                    @if ($grafik['kondisi']['total'] > 0)
                        <div class="relative h-64 sm:h-72 chart-container">
                            <canvas id="chartKondisi"></canvas>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-100 text-center text-xs">
                            <div class="p-1.5 rounded-lg bg-emerald-50/60">
                                <span class="block text-[11px] text-slate-500 font-medium">Baik</span>
                                <strong class="text-sm text-emerald-700 font-bold">{{ $grafik['kondisi']['data'][0] ?? 0 }}</strong>
                                <span class="block text-[10px] text-slate-400">({{ $grafik['kondisi']['persen'][0] ?? 0 }}%)</span>
                            </div>
                            <div class="p-1.5 rounded-lg bg-amber-50/60">
                                <span class="block text-[11px] text-slate-500 font-medium">Rusak Ringan</span>
                                <strong class="text-sm text-amber-700 font-bold">{{ $grafik['kondisi']['data'][1] ?? 0 }}</strong>
                                <span class="block text-[10px] text-slate-400">({{ $grafik['kondisi']['persen'][1] ?? 0 }}%)</span>
                            </div>
                            <div class="p-1.5 rounded-lg bg-red-50/60">
                                <span class="block text-[11px] text-slate-500 font-medium">Rusak Berat</span>
                                <strong class="text-sm text-red-700 font-bold">{{ $grafik['kondisi']['data'][2] ?? 0 }}</strong>
                                <span class="block text-[10px] text-slate-400">({{ $grafik['kondisi']['persen'][2] ?? 0 }}%)</span>
                            </div>
                        </div>

                        {{-- Catatan Metodologi Transparansi Data Master vs Historis --}}
                        <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50/60 p-3 text-left">
                            <div class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div class="text-[11px] leading-relaxed text-blue-900/90">
                                    <strong class="font-semibold text-blue-950">Catatan Metodologi:</strong> Grafik menyajikan kondisi fisik terkini berdasarkan data master aset dari populasi saldo aset pada periode laporan. Sistem belum mencatat histori perubahan kondisi fisik aset, sehingga kondisi yang ditampilkan tidak merepresentasikan kondisi fisik pada tanggal laporan historis.
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-64 sm:h-72 text-slate-400 text-sm">
                            <svg class="h-10 w-10 mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>
                            <span>Tidak ada data kondisi aset pada periode ini</span>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- GRAFIK B: DISTRIBUSI KATEGORI ASET (BAR CHART) --}}
            <x-card :padding="false">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">B. Distribusi Kategori Aset</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Barang & Kendaraan tanpa duplikasi &bull; Tanah KIB A terpisah (*)</p>
                    </div>
                    <span class="rounded-lg bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700">Bar</span>
                </div>
                <div class="p-5">
                    @if ($grafik['kategori']['total'] > 0)
                        <div class="relative h-64 sm:h-72 chart-container">
                            <canvas id="chartKategori"></canvas>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-4 pt-2 border-t border-slate-100 text-center">
                            Total: <strong class="text-slate-700">{{ $grafik['kategori']['total'] }} unit/bidang</strong>. Kendaraan dihitung sebagai kategori tersendiri tanpa tumpang tindih barang.
                        </p>
                    @else
                        <div class="flex flex-col items-center justify-center h-64 sm:h-72 text-slate-400 text-sm">
                            <svg class="h-10 w-10 mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Tidak ada data kategori aset pada periode ini</span>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- GRAFIK C: NILAI PEROLEHAN VS NILAI BUKU (GROUPED BAR CHART) --}}
            <x-card :padding="false">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">C. Nilai Perolehan vs Nilai Buku</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Penyusutan per t_acuan untuk Barang Inventaris & Kendaraan Bermotor (SAP No. 07)</p>
                    </div>
                    <span class="rounded-lg bg-teal-50 px-2 py-0.5 text-xs font-bold text-teal-700">Komparasi</span>
                </div>
                <div class="p-5">
                    @if ($grafik['nilai']['total_perolehan'] > 0)
                        <div class="relative h-64 sm:h-72 chart-container">
                            <canvas id="chartNilai"></canvas>
                        </div>
                        <div class="flex items-center justify-between mt-4 pt-2 border-t border-slate-100 text-xs text-slate-500">
                            <span>Perolehan: <strong class="text-blue-700">Rp {{ number_format($grafik['nilai']['total_perolehan'], 0, ',', '.') }}</strong></span>
                            <span>Nilai Buku: <strong class="text-emerald-700">Rp {{ number_format($grafik['nilai']['total_buku'], 0, ',', '.') }}</strong></span>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-64 sm:h-72 text-slate-400 text-sm">
                            <svg class="h-10 w-10 mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Nilai perolehan bernilai Rp0 pada periode ini</span>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- GRAFIK D: TREN PENGADAAN ASET (LINE CHART) --}}
            <x-card :padding="false">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">D. Tren Pengadaan Aset</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Historis pengadaan per tahun perolehan (abaikan tanggal kosong)</p>
                    </div>
                    <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Tren Line</span>
                </div>
                <div class="p-5">
                    @if (array_sum($grafik['tren_pengadaan']['data_count']) > 0)
                        <div class="relative h-64 sm:h-72 chart-container">
                            <canvas id="chartTrenPengadaan"></canvas>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-4 pt-2 border-t border-slate-100 text-center">
                            Riwayat tercatat dari <strong class="text-slate-700">{{ reset($grafik['tren_pengadaan']['labels']) }}</strong> sampai <strong class="text-slate-700">{{ end($grafik['tren_pengadaan']['labels']) }}</strong>
                        </p>
                    @else
                        <div class="flex flex-col items-center justify-center h-64 sm:h-72 text-slate-400 text-sm">
                            <svg class="h-10 w-10 mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                            <span>Belum ada data pengadaan aset dengan tanggal valid</span>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- GRAFIK E: KONDISI ASET BERDASARKAN LOKASI FISIK (GROUPED STACKED BAR) --}}
            <x-card :padding="false">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">E. Kondisi Aset Berdasarkan Lokasi</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Distribusi kondisi Barang Inventaris dan Kendaraan Bermotor berdasarkan lokasi fisik aset.</p>
                    </div>
                    <span class="rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700">Grouped Stacked</span>
                </div>
                <div class="p-5">
                    @if (! empty($grafik['kondisi_unit']['labels']))
                        {{-- Legenda Dua Dimensi (Kelompok Batang & Warna Kondisi) --}}
                        <div class="flex flex-wrap items-center justify-between gap-2.5 text-xs mb-3 pb-2.5 border-b border-slate-100">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Kelompok Batang:</span>
                                <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                    <span class="h-3 w-3 rounded-sm bg-slate-500"></span> Batang Kiri: Barang Inventaris
                                </span>
                                <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                    <span class="h-3 w-3 rounded-sm bg-indigo-600"></span> Batang Kanan: Kendaraan Bermotor
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Warna Kondisi:</span>
                                <span class="inline-flex items-center gap-1 text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Baik</span>
                                <span class="inline-flex items-center gap-1 text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Rusak Ringan</span>
                                <span class="inline-flex items-center gap-1 text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> Rusak Berat</span>
                            </div>
                        </div>

                        <div class="relative h-64 sm:h-72 chart-container">
                            <canvas id="chartKondisiUnit"></canvas>
                        </div>

                        {{-- INFORMASI PENDUKUNG PEMEGANG DAN RUANGAN (RINGKASAN TANPA DRILLDOWN) --}}
                        @if (! empty($grafik['kondisi_unit']['ringkasan']['per_lokasi']))
                            <div class="mt-5 pt-4 border-t border-slate-100">
                                <div class="flex items-center justify-between mb-2.5">
                                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Ringkasan Penempatan & Pemegang per Lokasi</h4>
                                    <span class="text-[10px] text-slate-400">Ringkasan Eksekutif &bull; Tanpa Drilldown</span>
                                </div>
                                <div class="overflow-x-auto rounded-xl border border-slate-100">
                                    <table class="w-full text-left text-xs text-slate-600">
                                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100">
                                            <tr>
                                                <th class="px-3 py-2">Lokasi Fisik</th>
                                                <th class="px-3 py-2 text-right">Barang</th>
                                                <th class="px-3 py-2 text-right">Kendaraan</th>
                                                <th class="px-3 py-2 text-right">Total Aset</th>
                                                <th class="px-3 py-2 text-center">Status Pemegang</th>
                                                <th class="px-3 py-2 text-center">Penempatan Ruangan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            @foreach ($grafik['kondisi_unit']['ringkasan']['per_lokasi'] as $ringk)
                                                <tr class="hover:bg-slate-50/50">
                                                    <td class="px-3 py-2 font-medium text-slate-800">{{ $ringk['lokasi'] }}</td>
                                                    <td class="px-3 py-2 text-right font-medium text-slate-700">{{ number_format($ringk['barang_count']) }}</td>
                                                    <td class="px-3 py-2 text-right font-medium text-indigo-600">{{ number_format($ringk['kendaraan_count']) }}</td>
                                                    <td class="px-3 py-2 text-right font-bold text-slate-900">{{ number_format($ringk['total_aset']) }}</td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="inline-flex items-center gap-1 text-[11px]">
                                                            <span class="font-semibold text-emerald-700">{{ $ringk['dengan_pemegang'] }}</span> berpemegang
                                                            <span class="text-slate-300">/</span>
                                                            <span class="text-slate-400">{{ $ringk['tanpa_pemegang'] }} tanpa</span>
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <span class="inline-flex items-center gap-1 text-[11px]">
                                                            <span class="font-semibold text-teal-700">{{ $ringk['dengan_ruangan'] }}</span> di ruangan
                                                            <span class="text-slate-300">/</span>
                                                            <span class="text-slate-400">{{ $ringk['tanpa_ruangan'] }} tanpa</span>
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="border-t border-slate-200 bg-slate-50/80 font-bold text-slate-800 text-[11px]">
                                            <tr>
                                                <td class="px-3 py-2">Total Konsolidasi</td>
                                                <td class="px-3 py-2 text-right text-slate-800">{{ number_format($grafik['kondisi_unit']['ringkasan']['total']['barang_count']) }}</td>
                                                <td class="px-3 py-2 text-right text-indigo-700">{{ number_format($grafik['kondisi_unit']['ringkasan']['total']['kendaraan_count']) }}</td>
                                                <td class="px-3 py-2 text-right text-slate-900">{{ number_format($grafik['kondisi_unit']['ringkasan']['total']['total_aset']) }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="text-emerald-800">{{ $grafik['kondisi_unit']['ringkasan']['total']['dengan_pemegang'] }}</span> berpemegang /
                                                    <span class="text-slate-500">{{ $grafik['kondisi_unit']['ringkasan']['total']['tanpa_pemegang'] }} tanpa</span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="text-teal-800">{{ $grafik['kondisi_unit']['ringkasan']['total']['dengan_ruangan'] }}</span> di ruangan /
                                                    <span class="text-slate-500">{{ $grafik['kondisi_unit']['ringkasan']['total']['tanpa_ruangan'] }} tanpa</span>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Catatan Transparansi Lokasi Fisik --}}
                        <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50/80 p-2.5 text-left">
                            <div class="flex items-start gap-2">
                                <svg class="h-4 w-4 text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div class="text-[11px] leading-relaxed text-slate-600">
                                    <strong class="font-semibold text-slate-700">Catatan Metodologi:</strong> Menampilkan kondisi fisik terkini berdasarkan lokasi fisik aset (<code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-700">aset.id_lokasi</code>). Barang Inventaris dan Kendaraan Bermotor disajikan sebagai kelompok tersendiri. Tanah daerah (KIB A) tidak terikat pada lokasi kantor fisik sehingga dieksklusikan dari grafik ini.
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-64 sm:h-72 text-slate-400 text-sm">
                            <svg class="h-10 w-10 mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span>Tidak ada aset terdaftar pada lokasi fisik ini</span>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- GRAFIK F: AKTIVITAS ASET PERIODE BERJALAN (GROUPED BAR) --}}
            <x-card :padding="false">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">F. Aktivitas Arus Aset (Flow)</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Pengadaan Baru vs Mutasi Terlaksana kurun {{ $periode['judul_periode'] }}</p>
                    </div>
                    <span class="rounded-lg bg-purple-50 px-2 py-0.5 text-xs font-bold text-purple-700">Aktivitas</span>
                </div>
                <div class="p-5">
                    @if ($grafik['aktivitas']['total_pengadaan'] > 0 || $grafik['aktivitas']['total_mutasi'] > 0)
                        <div class="relative h-64 sm:h-72 chart-container">
                            <canvas id="chartAktivitas"></canvas>
                        </div>
                        <div class="flex items-center justify-between mt-4 pt-2 border-t border-slate-100 text-xs text-slate-500">
                            <span>Pengadaan Baru: <strong class="text-blue-700">{{ $grafik['aktivitas']['total_pengadaan'] }} Unit</strong></span>
                            <span>Mutasi Terlaksana: <strong class="text-purple-700">{{ $grafik['aktivitas']['total_mutasi'] }} Transaksi</strong></span>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-64 sm:h-72 text-slate-400 text-sm">
                            <svg class="h-10 w-10 mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span>Tidak ada aktivitas pengadaan baru atau mutasi pada kurun periode ini</span>
                        </div>
                    @endif
                </div>
            </x-card>

        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- SEKSI 1: RINGKASAN KONDISI FISIK ASET --}}
        <x-card :padding="false">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">1. Kondisi Fisik Inventaris</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Sumber data: status kondisi master pada tabel aset</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">{{ $k['total_barang_dan_kendaraan'] }} Unit</span>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between p-3 rounded-xl border border-emerald-100 bg-emerald-50/40">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Kondisi Baik</p>
                            <p class="text-xs text-slate-500">Siap & layak pakai operasional</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-base font-bold text-emerald-700">{{ $k['kondisi_baik'] }} Unit</p>
                        <p class="text-[11px] text-slate-400">{{ $k['total_barang_dan_kendaraan'] > 0 ? round(($k['kondisi_baik'] / $k['total_barang_dan_kendaraan']) * 100, 1) : 0 }}%</p>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl border border-amber-100 bg-amber-50/40">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-amber-500"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Kondisi Rusak Ringan</p>
                            <p class="text-xs text-slate-500">Memerlukan pemeliharaan rutin</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-base font-bold text-amber-700">{{ $k['kondisi_rusak_ringan'] }} Unit</p>
                        <p class="text-[11px] text-slate-400">{{ $k['total_barang_dan_kendaraan'] > 0 ? round(($k['kondisi_rusak_ringan'] / $k['total_barang_dan_kendaraan']) * 100, 1) : 0 }}%</p>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl border border-red-200 bg-red-50/50">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-red-600 animate-pulse"></span>
                        <div>
                            <p class="text-sm font-bold text-red-900">Kondisi Rusak Berat (Risiko Tinggi)</p>
                            <p class="text-xs text-red-600">Prioritas penggantian / penghapusan</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-base font-extrabold text-red-700">{{ $k['kondisi_rusak_berat'] }} Unit</p>
                        <p class="text-[11px] font-semibold text-red-500">{{ $k['total_barang_dan_kendaraan'] > 0 ? round(($k['kondisi_rusak_berat'] / $k['total_barang_dan_kendaraan']) * 100, 1) : 0 }}%</p>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- SEKSI 2: RINGKASAN MASA MANFAAT & PENYUSUTAN --}}
        <x-card :padding="false">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">2. Status Masa Manfaat Aset</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Dihitung per tanggal acuan {{ $periode['acuan']->format('d/m/Y') }}</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">MasaPakaiAset</span>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between p-3 rounded-xl border border-slate-100 bg-slate-50/60">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-blue-500"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Normal</p>
                            <p class="text-xs text-slate-500">Sisa masa manfaat > 30 hari</p>
                        </div>
                    </div>
                    <p class="text-base font-bold text-slate-700">{{ $k['masa_normal'] }} Unit</p>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl border border-amber-200 bg-amber-50/50">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-amber-500"></span>
                        <div>
                            <p class="text-sm font-bold text-amber-900">Kritis (Segera Habis)</p>
                            <p class="text-xs text-amber-600">Sisa masa manfaat 1 s.d. 30 hari</p>
                        </div>
                    </div>
                    <p class="text-base font-extrabold text-amber-700">{{ $k['masa_kritis'] }} Unit</p>
                </div>

                <div class="flex items-center justify-between p-3 rounded-xl border border-purple-100 bg-purple-50/50">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-purple-500"></span>
                        <div>
                            <p class="text-sm font-semibold text-purple-900">Masa Manfaat Habis (Nilai Buku Rp0)</p>
                            <p class="text-xs text-purple-600">Telah tersusutkan secara penuh</p>
                        </div>
                    </div>
                    <p class="text-base font-bold text-purple-700">{{ $k['masa_habis'] }} Unit</p>
                </div>
            </div>
        </x-card>

        {{-- SEKSI 3: AUDIT SENSUS FISIK SIKLUS 3 TAHUNAN --}}
        <x-card :padding="false">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">3. Progres Sensus Siklus Tiga Tahunan</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Siklus Aktif: <strong class="text-emerald-700">{{ $k['siklus_sensus_aktif'] }}</strong> (Anchor 2025)</p>
                </div>
                <span class="text-sm font-extrabold text-emerald-700">{{ $k['sensus_persentase'] }}%</span>
            </div>
            <div class="p-5">
                <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100 mb-4">
                    <div class="h-full bg-emerald-600 rounded-full transition-all" style="width: {{ min(100, $k['sensus_persentase']) }}%"></div>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center mb-4">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[11px] text-slate-400 uppercase font-semibold">Wajib Sensus</p>
                        <p class="text-base font-bold text-slate-700 mt-0.5">{{ $k['sensus_wajib_count'] }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-2.5">
                        <p class="text-[11px] text-emerald-600 uppercase font-semibold">Telah Disensus</p>
                        <p class="text-base font-bold text-emerald-800 mt-0.5">{{ $k['sensus_sudah_count'] }}</p>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-2.5">
                        <p class="text-[11px] text-amber-600 uppercase font-semibold">Belum Sensus</p>
                        <p class="text-base font-bold text-amber-800 mt-0.5">{{ $k['sensus_belum_count'] }}</p>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 text-xs text-slate-600 space-y-1">
                    <p class="font-semibold text-slate-700 mb-1">Hasil Verifikasi Fisik Sensus Siklus Ini:</p>
                    <div class="flex items-center justify-between">
                        <span>Fisik Baik:</span>
                        <strong class="text-emerald-700">{{ $k['sensus_baik'] }} Unit</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Fisik Rusak Ringan:</span>
                        <strong class="text-amber-700">{{ $k['sensus_rusak_ringan'] }} Unit</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Fisik Rusak Berat:</span>
                        <strong class="text-red-700">{{ $k['sensus_rusak_berat'] }} Unit</strong>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- SEKSI 4: KEPATUHAN & REALISASI PAJAK KENDARAAN --}}
        <x-card :padding="false">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">4. Pengelolaan Pajak Kendaraan</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Total Kendaraan Dikelola: <strong>{{ $k['total_kendaraan'] }} Unit</strong></p>
                </div>
                <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">PKB & SWDKLLJ</span>
            </div>
            <div class="p-5 space-y-4">
                {{-- Bagian Flow: Realisasi Pembayaran --}}
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3.5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold text-emerald-900 uppercase tracking-wide">Realisasi Bayar Periode Berjalan (Flow)</p>
                        <span class="text-xs font-semibold text-emerald-700">{{ $k['pajak_flow_transaksi_count'] }} Transaksi</span>
                    </div>
                    <p class="text-lg font-bold text-emerald-800 mt-1">Rp {{ number_format($k['pajak_flow_total_nominal'], 0, ',', '.') }}</p>
                    <p class="text-[11px] text-emerald-600 mt-0.5">{{ $k['pajak_flow_kendaraan_count'] }} Kendaraan menyelesaikan pajak pada periode ini</p>
                </div>

                {{-- Bagian Snapshot: Status Kepatuhan per Akhir Periode --}}
                <div>
                    <p class="text-xs font-bold text-slate-600 uppercase tracking-wide mb-2">Status Kepatuhan per {{ $periode['acuan']->format('d/m/Y') }} (Snapshot)</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-2">
                            <p class="text-[10px] text-emerald-600 font-semibold uppercase">Berlaku / Aman</p>
                            <p class="text-base font-bold text-emerald-800 mt-0.5">{{ $k['pajak_snapshot_aman'] }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50 p-2">
                            <p class="text-[10px] text-amber-600 font-semibold uppercase">&le; 30 Hari</p>
                            <p class="text-base font-bold text-amber-800 mt-0.5">{{ $k['pajak_snapshot_kritis'] }}</p>
                        </div>
                        <div class="rounded-xl border border-red-100 bg-red-50 p-2">
                            <p class="text-[10px] text-red-600 font-semibold uppercase">Terlambat</p>
                            <p class="text-base font-bold text-red-800 mt-0.5">{{ $k['pajak_snapshot_terlambat'] }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2">
                            <p class="text-[10px] text-slate-500 font-semibold uppercase">Tanpa Data</p>
                            <p class="text-base font-bold text-slate-700 mt-0.5">{{ $k['pajak_snapshot_tanpa_data'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- SEKSI 5: REGISTER TANAH DAERAH (KIB A) - BAGIAN TERPISAH --}}
    <x-card :padding="false">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">5. Register Aset Tanah Daerah (KIB A)</h2>
                <p class="text-xs text-slate-400 mt-0.5">Disajikan terpisah sebagai aset barang tidak bergerak (tidak mengalami penyusutan)</p>
            </div>
            @if ($k['bisa_akses_tanah'])
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">KIB A Tanah</span>
            @else
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">Akses Terkunci</span>
            @endif
        </div>
        <div class="p-5">
            @if ($k['bisa_akses_tanah'])
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Bidang Tanah</p>
                        <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($k['total_bidang_tanah'], 0, ',', '.') }} Bidang</p>
                        <p class="text-xs text-slate-400 mt-1">Register KIB A Terdaftar</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Luas Tanah</p>
                        <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($k['total_luas_tanah'], 0, ',', '.') }} m&sup2;</p>
                        <p class="text-xs text-slate-400 mt-1">Luasan Fisik Tercatat</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Nilai Perolehan / Buku</p>
                        <p class="text-2xl font-bold text-teal-700 mt-1">Rp {{ number_format($k['total_nilai_buku_tanah'], 0, ',', '.') }}</p>
                        <p class="text-xs text-slate-400 mt-1">Tanpa Penyusutan (SAP No. 07)</p>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-6 text-center text-sm text-slate-500">
                    <svg class="h-8 w-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    Data Register Tanah KIB A tidak dikelola oleh unit kerja ini sesuai ketetapan kewenangan <code class="text-xs bg-slate-200 px-1.5 py-0.5 rounded">TanahScope</code>.
                </div>
            @endif
        </div>
    </x-card>

</div>

{{-- SCRIPT INTERAKSI FILTER BULAN --}}
<script>
function toggleBulanInput() {
    const jenisSelect = document.getElementById('jenis');
    const bulanWrapper = document.getElementById('wrapper-bulan');
    const bulanSelect = document.getElementById('bulan');

    if (jenisSelect.value === 'tahunan') {
        bulanWrapper.classList.add('opacity-50', 'pointer-events-none');
        bulanSelect.disabled = true;
    } else {
        bulanWrapper.classList.remove('opacity-50', 'pointer-events-none');
        bulanSelect.disabled = false;
    }
}
</script>
@endsection

@push('scripts')
{{-- Prioritaskan pemuatan pustaka lokal Chart.js UMD v4.4.1 (offline/intranet) --}}
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}" id="chartjs-local-script"></script>

<script>
(function() {
    let chartsInitialized = false;

    // Tampilkan pesan kegagalan yang informatif pada setiap kontainer grafik jika seluruh sumber gagal
    function handleChartLoadError() {
        console.warn('Pustaka Chart.js gagal dimuat dari berkas lokal maupun CDN fallback.');
        document.querySelectorAll('.chart-container').forEach(function (container) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full p-6 text-center rounded-xl border border-amber-200 bg-amber-50/70 text-amber-900">
                    <svg class="h-9 w-9 text-amber-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <p class="text-sm font-semibold text-amber-800">Visualisasi grafik tidak dapat dimuat.</p>
                    <p class="text-xs text-amber-700/80 mt-1 max-w-sm">Silakan periksa ketersediaan pustaka grafik atau hubungi administrator sistem.</p>
                </div>
            `;
        });
    }

    function initExecutiveCharts() {
        if (chartsInitialized) return;
        if (typeof Chart === 'undefined') {
            handleChartLoadError();
            return;
        }
        chartsInitialized = true;

        try {
            // Default konfigurasi Chart.js
            Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
            Chart.defaults.color = '#64748b';

            const formatRupiah = function (num) {
                return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
            };

            const disableDrilldownOptions = {
                // Hanya respons event hover dan sentuhan; hindari perilaku tombol/drilldown
                events: ['mousemove', 'mouseout', 'touchstart', 'touchmove'],
                onHover: function (e) {
                    if (e && e.native && e.native.target) {
                        e.native.target.style.cursor = 'default';
                    }
                }
            };

            // -------------------------------------------------------------
            // GRAFIK A: DISTRIBUSI KONDISI ASET (DONUT)
            // -------------------------------------------------------------
            const canvasKondisi = document.getElementById('chartKondisi');
            if (canvasKondisi) {
                const dataKondisi = @json($grafik['kondisi']);
                new Chart(canvasKondisi, {
                    type: 'doughnut',
                    data: {
                        labels: dataKondisi.labels,
                        datasets: [{
                            data: dataKondisi.data,
                            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#94a3b8'],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 4
                        }]
                    },
                    options: Object.assign({}, disableDrilldownOptions, {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 12, font: { size: 11, weight: '500' } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const val = ctx.raw || 0;
                                        const total = dataKondisi.total || 1;
                                        const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                        return ` ${ctx.label}: ${val} unit (${pct}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '68%'
                    })
                });
            }

            // -------------------------------------------------------------
            // GRAFIK B: DISTRIBUSI KATEGORI ASET (BAR)
            // -------------------------------------------------------------
            const canvasKategori = document.getElementById('chartKategori');
            if (canvasKategori) {
                const dataKategori = @json($grafik['kategori']);
                new Chart(canvasKategori, {
                    type: 'bar',
                    data: {
                        labels: dataKategori.labels,
                        datasets: [{
                            label: 'Jumlah Unit/Bidang',
                            data: dataKategori.data,
                            backgroundColor: dataKategori.labels.map(function (lbl) {
                                if (lbl.indexOf('Tanah') !== -1) return '#0d9488'; // Teal untuk Tanah KIB A
                                if (lbl.indexOf('Kendaraan') !== -1) return '#6366f1'; // Indigo untuk Kendaraan
                                return '#3b82f6'; // Blue untuk kategori barang lainnya
                            }),
                            borderRadius: 6
                        }]
                    },
                    options: Object.assign({}, disableDrilldownOptions, {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ` ${ctx.raw} unit/bidang`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: { precision: 0, font: { size: 10 } }
                            },
                            y: {
                                grid: { display: false },
                                ticks: { font: { size: 11, weight: '500' } }
                            }
                        }
                    })
                });
            }

            // -------------------------------------------------------------
            // GRAFIK C: NILAI PEROLEHAN VS NILAI BUKU (GROUPED BAR)
            // -------------------------------------------------------------
            const canvasNilai = document.getElementById('chartNilai');
            if (canvasNilai) {
                const dataNilai = @json($grafik['nilai']);
                new Chart(canvasNilai, {
                    type: 'bar',
                    data: {
                        labels: dataNilai.labels,
                        datasets: [
                            {
                                label: 'Nilai Perolehan',
                                data: dataNilai.nilai_perolehan,
                                backgroundColor: '#3b82f6',
                                borderRadius: 6
                            },
                            {
                                label: 'Nilai Buku Bersih',
                                data: dataNilai.nilai_buku,
                                backgroundColor: '#10b981',
                                borderRadius: 6
                            }
                        ]
                    },
                    options: Object.assign({}, disableDrilldownOptions, {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 12, font: { size: 11, weight: '500' } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ` ${ctx.dataset.label}: ${formatRupiah(ctx.raw)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11, weight: '500' } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    callback: function (val) {
                                        if (val >= 1e9) return 'Rp ' + (val / 1e9).toFixed(1) + ' M';
                                        if (val >= 1e6) return 'Rp ' + (val / 1e6).toFixed(0) + ' Jt';
                                        return 'Rp ' + Number(val).toLocaleString('id-ID');
                                    },
                                    font: { size: 10 }
                                }
                            }
                        }
                    })
                });
            }

            // -------------------------------------------------------------
            // GRAFIK D: TREN PENGADAAN ASET (LINE CHART)
            // -------------------------------------------------------------
            const canvasTren = document.getElementById('chartTrenPengadaan');
            if (canvasTren) {
                const dataTren = @json($grafik['tren_pengadaan']);
                new Chart(canvasTren, {
                    type: 'line',
                    data: {
                        labels: dataTren.labels,
                        datasets: [{
                            label: 'Jumlah Pengadaan (Unit)',
                            data: dataTren.data_count,
                            borderColor: '#059669',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#059669',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: Object.assign({}, disableDrilldownOptions, {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 12, font: { size: 11, weight: '500' } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ` Pengadaan: ${ctx.raw} unit`;
                                    },
                                    afterLabel: function (ctx) {
                                        const idx = ctx.dataIndex;
                                        const val = dataTren.data_nilai[idx] || 0;
                                        return ` Nilai Pengadaan: ${formatRupiah(val)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: { precision: 0, font: { size: 10 } }
                            }
                        }
                    })
                });
            }

            // -------------------------------------------------------------
            // GRAFIK E: KONDISI PER LOKASI FISIK (GROUPED STACKED BAR)
            // -------------------------------------------------------------
            const canvasKondisiUnit = document.getElementById('chartKondisiUnit');
            if (canvasKondisiUnit) {
                const dataUnit = @json($grafik['kondisi_unit']);
                const datasetsUnit = [
                    // STACK 1: Barang Inventaris (borderWidth 0 & borderRadius 0 untuk presisi segmen kecil)
                    {
                        label: 'Barang: Baik',
                        data: dataUnit.barang_baik || dataUnit.baik,
                        backgroundColor: '#10b981',
                        borderWidth: 0,
                        stack: 'barang',
                        borderRadius: 0
                    },
                    {
                        label: 'Barang: Rusak Ringan',
                        data: dataUnit.barang_rusak_ringan || dataUnit.rusak_ringan,
                        backgroundColor: '#f59e0b',
                        borderWidth: 0,
                        stack: 'barang',
                        borderRadius: 0
                    },
                    {
                        label: 'Barang: Rusak Berat',
                        data: dataUnit.barang_rusak_berat || dataUnit.rusak_berat,
                        backgroundColor: '#ef4444',
                        borderWidth: 0,
                        stack: 'barang',
                        borderRadius: 0
                    },
                    // STACK 2: Kendaraan Bermotor (borderWidth 0 & borderRadius 0 agar segmen 1 unit tidak tertutup)
                    {
                        label: 'Kendaraan: Baik',
                        data: dataUnit.kendaraan_baik || [],
                        backgroundColor: '#10b981',
                        borderWidth: 0,
                        stack: 'kendaraan',
                        borderRadius: 0
                    },
                    {
                        label: 'Kendaraan: Rusak Ringan',
                        data: dataUnit.kendaraan_rusak_ringan || [],
                        backgroundColor: '#f59e0b',
                        borderWidth: 0,
                        stack: 'kendaraan',
                        borderRadius: 0
                    },
                    {
                        label: 'Kendaraan: Rusak Berat',
                        data: dataUnit.kendaraan_rusak_berat || [],
                        backgroundColor: '#ef4444',
                        borderWidth: 0,
                        stack: 'kendaraan',
                        borderRadius: 0
                    }
                ];

                // Tambahkan Belum Diisi jika ada data
                const hasBelumDiisiBarang = dataUnit.barang_belum_diisi && dataUnit.barang_belum_diisi.some(function(v) { return v > 0; });
                const hasBelumDiisiKendaraan = dataUnit.kendaraan_belum_diisi && dataUnit.kendaraan_belum_diisi.some(function(v) { return v > 0; });
                if (hasBelumDiisiBarang) {
                    datasetsUnit.push({
                        label: 'Barang: Belum Diisi',
                        data: dataUnit.barang_belum_diisi,
                        backgroundColor: '#94a3b8',
                        borderWidth: 0,
                        stack: 'barang',
                        borderRadius: 0
                    });
                }
                if (hasBelumDiisiKendaraan) {
                    datasetsUnit.push({
                        label: 'Kendaraan: Belum Diisi',
                        data: dataUnit.kendaraan_belum_diisi,
                        backgroundColor: '#94a3b8',
                        borderWidth: 0,
                        stack: 'kendaraan',
                        borderRadius: 0
                    });
                }

                // Mode interaksi kustom: groupedStack
                // Memungkinkan hit-area berbasis kolom stack (Barang vs Kendaraan) sehingga batang yang sangat kecil (seperti 1 unit)
                // mudah disentuh/di-hover tanpa mengharuskan kursor tepat berada di dalam piksel batang.
                if (typeof Chart !== 'undefined' && Chart.Interaction && Chart.Interaction.modes) {
                    Chart.Interaction.modes.groupedStack = function(chart, e, options, useFinalPosition) {
                        const area = chart.chartArea;
                        if (!area) return [];

                        const pos = (e && typeof e.x === 'number' && typeof e.y === 'number')
                            ? e
                            : (Chart.helpers && Chart.helpers.getRelativePosition ? Chart.helpers.getRelativePosition(e, chart) : { x: e.offsetX, y: e.offsetY });

                        if (!pos || pos.x < area.left || pos.x > area.right || pos.y < area.top || pos.y > area.bottom) {
                            return [];
                        }

                        const labels = chart.data.labels;
                        if (!labels || !labels.length) return [];

                        let bestDist = Infinity;
                        let targetLoc = null;
                        let targetStack = null;

                        // Cari batang terdekat sepanjang sumbu X
                        ['barang', 'kendaraan'].forEach(stackName => {
                            labels.forEach((_, locIndex) => {
                                let barX = null;
                                chart.data.datasets.forEach((ds, dsIndex) => {
                                    if (barX === null && ds.stack === stackName) {
                                        const meta = chart.getDatasetMeta(dsIndex);
                                        if (meta && !meta.hidden && meta.data && meta.data[locIndex]) {
                                            barX = meta.data[locIndex].x;
                                        }
                                    }
                                });

                                if (barX !== null) {
                                    const dist = Math.abs(pos.x - barX);
                                    if (dist < bestDist) {
                                        bestDist = dist;
                                        targetLoc = locIndex;
                                        targetStack = stackName;
                                    }
                                }
                            });
                        });

                        if (targetLoc === null || targetStack === null) return [];

                        // Batas jangkauan horizontal agar tidak melompat ke lokasi lain di luar area wajar
                        const maxAllowedDist = Math.max(area.width / (labels.length * 2), 40);
                        if (bestDist > maxAllowedDist) return [];

                        // Kumpulkan seluruh dataset aktif bernilai > 0 pada stack & lokasi terpilih
                        const items = [];
                        chart.data.datasets.forEach((ds, dsIndex) => {
                            if (ds.stack === targetStack) {
                                const val = ds.data && ds.data[targetLoc];
                                if (val && val > 0) {
                                    const meta = chart.getDatasetMeta(dsIndex);
                                    if (meta && !meta.hidden && meta.data && meta.data[targetLoc]) {
                                        items.push({
                                            datasetIndex: dsIndex,
                                            index: targetLoc,
                                            element: meta.data[targetLoc]
                                        });
                                    }
                                }
                            }
                        });

                        return items;
                    };
                }

                // Plugin inline: Menampilkan SATU angka total tunggal di atas setiap kelompok stack bar (hanya jika stackTotal > 0)
                const topStackLabelsPlugin = {
                    id: 'topStackLabels',
                    afterDatasetsDraw(chart) {
                        const { ctx } = chart;
                        const labels = chart.data.labels;
                        if (!labels || !labels.length) return;

                        ['barang', 'kendaraan'].forEach(stackName => {
                            labels.forEach((_, locIndex) => {
                                let stackTotal = 0;
                                let topY = Infinity;
                                let barX = null;

                                chart.data.datasets.forEach((dataset, dsIndex) => {
                                    if (dataset.stack === stackName) {
                                        const val = dataset.data[locIndex] || 0;
                                        if (val > 0) {
                                            stackTotal += val;
                                            const meta = chart.getDatasetMeta(dsIndex);
                                            if (meta && !meta.hidden && meta.data && meta.data[locIndex]) {
                                                const el = meta.data[locIndex];
                                                if (el && typeof el.y === 'number' && el.y < topY) {
                                                    topY = el.y;
                                                    barX = el.x;
                                                }
                                            }
                                        }
                                    }
                                });

                                if (stackTotal > 0 && barX !== null && topY !== Infinity) {
                                    ctx.save();
                                    ctx.font = 'bold 11px Inter, ui-sans-serif, system-ui, sans-serif';
                                    ctx.fillStyle = stackName === 'kendaraan' ? '#4338ca' : '#334155';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'bottom';
                                    const drawY = Math.max(chart.chartArea.top + 10, topY - 3);
                                    ctx.fillText(stackTotal.toString(), barX, drawY);
                                    ctx.restore();
                                }
                            });
                        });
                    }
                };

                new Chart(canvasKondisiUnit, {
                    type: 'bar',
                    data: {
                        labels: dataUnit.labels,
                        datasets: datasetsUnit
                    },
                    plugins: [topStackLabelsPlugin],
                    options: Object.assign({}, disableDrilldownOptions, {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'groupedStack',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                mode: 'groupedStack',
                                intersect: false,
                                displayColors: false,
                                filter: function (item) {
                                    return item.raw > 0;
                                },
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) return '';
                                        const jenisAset = items[0].dataset.stack === 'kendaraan' ? 'Kendaraan Bermotor' : 'Barang Inventaris';
                                        return [
                                            'Lokasi: ' + items[0].label,
                                            'Jenis Aset: ' + jenisAset
                                        ];
                                    },
                                    label: function (ctx) {
                                        let kondisi = 'Baik';
                                        if (ctx.dataset.label.includes('Rusak Ringan')) {
                                            kondisi = 'Rusak Ringan';
                                        } else if (ctx.dataset.label.includes('Rusak Berat')) {
                                            kondisi = 'Rusak Berat';
                                        } else if (ctx.dataset.label.includes('Belum Diisi')) {
                                            kondisi = 'Belum Diisi';
                                        }
                                        return 'Kondisi ' + kondisi + ': ' + ctx.raw + ' unit';
                                    },
                                    footer: function (items) {
                                        if (items.length > 1) {
                                            const total = items.reduce(function (sum, item) {
                                                return sum + (item.raw || 0);
                                            }, 0);
                                            return 'Total: ' + total + ' unit';
                                        }
                                        return '';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                grid: { display: false },
                                ticks: {
                                    maxRotation: 20,
                                    minRotation: 0,
                                    font: { size: 10 },
                                    callback: function (val, index) {
                                        const label = this.getLabelForValue(index);
                                        return label.length > 25 ? label.substring(0, 22) + '...' : label;
                                    }
                                }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                grace: '12%',
                                grid: { color: '#f1f5f9' },
                                ticks: { precision: 0, font: { size: 10 } }
                            }
                        }
                    })
                });
            }

            // -------------------------------------------------------------
            // GRAFIK F: AKTIVITAS ARUS ASET (GROUPED BAR)
            // -------------------------------------------------------------
            const canvasAktivitas = document.getElementById('chartAktivitas');
            if (canvasAktivitas) {
                const dataAkt = @json($grafik['aktivitas']);
                new Chart(canvasAktivitas, {
                    type: 'bar',
                    data: {
                        labels: dataAkt.labels,
                        datasets: [
                            {
                                label: 'Pengadaan Baru',
                                data: dataAkt.pengadaan,
                                backgroundColor: '#3b82f6',
                                borderRadius: 5
                            },
                            {
                                label: 'Mutasi Aset Terlaksana',
                                data: dataAkt.mutasi,
                                backgroundColor: '#8b5cf6',
                                borderRadius: 5
                            }
                        ]
                    },
                    options: Object.assign({}, disableDrilldownOptions, {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 12, font: { size: 11, weight: '500' } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const unitWord = ctx.datasetIndex === 0 ? 'unit' : 'transaksi';
                                        return ` ${ctx.dataset.label}: ${ctx.raw} ${unitWord}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: { precision: 0, font: { size: 10 } }
                            }
                        }
                    })
                });
            }
        } catch (err) {
            console.error('Error saat inisialisasi grafik:', err);
        }
    }

    // Pemuatan cadangan melalui CDN bila pustaka lokal gagal diunduh
    function loadChartCdnFallback() {
        if (typeof Chart !== 'undefined') {
            initExecutiveCharts();
            return;
        }

        console.info('Pustaka lokal tidak tersedia, memuat Chart.js dari CDN fallback...');
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        script.onload = function() {
            initExecutiveCharts();
        };
        script.onerror = function() {
            handleChartLoadError();
        };
        document.head.appendChild(script);
    }

    // Sambungkan penanganan error ke skrip lokal
    const localScript = document.getElementById('chartjs-local-script');
    if (localScript) {
        localScript.onerror = function() {
            loadChartCdnFallback();
        };
    }

    // Jalankan inisialisasi saat dokumen siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart !== 'undefined') {
                initExecutiveCharts();
            } else {
                setTimeout(function() {
                    if (typeof Chart !== 'undefined') {
                        initExecutiveCharts();
                    } else {
                        loadChartCdnFallback();
                    }
                }, 100);
            }
        });
    } else {
        if (typeof Chart !== 'undefined') {
            initExecutiveCharts();
        } else {
            loadChartCdnFallback();
        }
    }
})();
</script>
@endpush

