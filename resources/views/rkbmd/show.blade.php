@extends('layouts.app')

@section('title', 'Detail Usulan RKBMD #' . $usulan->id_usulan)
@section('page-title', 'Detail Usulan RKBMD')
@section('breadcrumb', 'Perencanaan - RKBMD - Detail Usulan #' . $usulan->id_usulan)

@section('content')
@php
    $statusLabel = match ($usulan->status_usulan) {
        'Draft' => 'Draf',
        'Diajukan' => 'Diajukan',
        'Disetujui Pengurus Barang' => 'Diterima',
        'Ditolak' => 'Ditolak',
        default => $usulan->status_usulan ?: '-',
    };

    $badgeJenis = [
        'Pengadaan' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
        'Pemeliharaan' => 'bg-amber-50 text-amber-700 border-amber-200/80',
        'Penghapusan' => 'bg-rose-50 text-rose-700 border-rose-200/80',
        'Pemindahtanganan' => 'bg-purple-50 text-purple-700 border-purple-200/80',
        'Pemanfaatan' => 'bg-blue-50 text-blue-700 border-blue-200/80',
    ][$usulan->jenis_usulan] ?? 'bg-slate-50 text-slate-600 border-slate-200/80';

    $badgeStatus = [
        'Draft' => 'bg-slate-100 text-slate-700',
        'Diajukan' => 'bg-amber-100 text-amber-800',
        'Disetujui Pengurus Barang' => 'bg-emerald-100 text-emerald-800',
        'Ditolak' => 'bg-rose-100 text-rose-800',
    ][$usulan->status_usulan] ?? 'bg-slate-100 text-slate-700';

    $opt = $usulan->barang_optimalisasi;
    $adaOpt = $opt && (isset($opt['nama']) || isset($opt['kode']) || isset($opt['jumlah']));
@endphp

<div class="mx-auto max-w-6xl space-y-6 pb-12">
    <x-alert type="error" />
    <x-alert type="success" />

    {{-- Header & Navigasi Utama --}}
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3.5">
                <a href="{{ route('rkbmd.index') }}"
                   class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-2xs transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-500"
                   title="Kembali ke Daftar Usulan RKBMD"
                   aria-label="Kembali ke Daftar Usulan RKBMD">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $usulan->nama_barang }}</h1>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-slate-600">#{{ $usulan->id_usulan }}</span>
                    </div>
                    <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500 sm:text-sm">
                        <span class="font-semibold text-slate-700">{{ $usulan->skpd?->nama_skpd ?? '-' }}</span>
                        <span class="text-slate-300">&bull;</span>
                        <span>Tahun Anggaran {{ $usulan->tahun_anggaran }}</span>
                    </p>
                </div>
            </div>

            {{-- Badges Jenis Usulan & Status (Sejajar, Proporsional, Modern) --}}
            <div class="flex flex-wrap items-center gap-2 sm:self-center">
                <span class="inline-flex items-center rounded-xl border px-3 py-1 text-xs font-bold tracking-wide {{ $badgeJenis }}">
                    Usulan {{ $usulan->jenis_usulan }}
                </span>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold tracking-wide {{ $badgeStatus }}">
                    <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current opacity-75"></span>
                    {{ $statusLabel }}
                </span>
            </div>
        </div>

        {{-- Aksi Persetujuan / Penolakan (HANYA Super Admin / Pengurus Barang bila status Diajukan) --}}
        @if ($isAdmin && $usulan->status_usulan === 'Diajukan')
            <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-amber-700 font-bold text-[10px]">!</span>
                    <span>Menunggu verifikasi dan keputusan Pengurus Barang.</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <form method="POST" action="{{ route('rkbmd.decide', $usulan->id_usulan) }}"
                          onsubmit="return confirm('Setujui usulan ini sebagai usulan resmi RKBMD?');" class="inline">
                        @csrf
                        <input type="hidden" name="keputusan" value="setuju">
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Setujui Usulan</span>
                        </button>
                    </form>

                    <button type="button" data-open-modal="modalTolakHalaman"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3.5 py-2 text-xs font-bold text-rose-600 shadow-2xs transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Tolak Usulan</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- BARIS 1: Informasi Umum & Program/Kegiatan (2 Kolom Berdampingan) --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- 1. INFORMASI UMUM USULAN --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">1. Informasi Umum Usulan</h2>
                    <p class="text-[11px] text-slate-400">Unit kerja pengusul dan identitas usulan</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Unit Kerja / SKPD</span>
                    <span class="mt-0.5 block font-bold text-slate-800">{{ $usulan->skpd?->nama_skpd ?? '-' }}</span>
                    <span class="text-[10px] text-slate-400">Tipe: {{ $usulan->skpd?->jenis_skpd ?? 'Bidang / Dinas' }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Nama Pengusul</span>
                    <span class="mt-0.5 block font-bold text-slate-800">{{ $usulan->pegawai?->nama_pegawai ?? '-' }}</span>
                    @if ($usulan->pegawai?->nip)
                        <span class="block font-mono text-[10px] text-slate-400">NIP: {{ $usulan->pegawai->nip }}</span>
                    @endif
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Tahun Anggaran</span>
                    <span class="mt-0.5 block font-extrabold text-slate-900">TA {{ $usulan->tahun_anggaran }}</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Tanggal Pengajuan</span>
                    <span class="mt-0.5 block font-semibold text-slate-700">
                        {{ $usulan->tanggal_usulan ? $usulan->tanggal_usulan->format('d F Y') : '-' }}
                    </span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Jenis Usulan</span>
                    <span class="mt-1 inline-flex items-center rounded-xl border px-2.5 py-0.5 text-[11px] font-bold {{ $badgeJenis }}">
                        {{ $usulan->jenis_usulan }}
                    </span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Status Usulan</span>
                    <span class="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeStatus }}">
                        <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current opacity-75"></span>
                        {{ $statusLabel }}
                    </span>
                </div>
            </div>
        </div>

        {{-- 2. PROGRAM DAN KEGIATAN RKBMD --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">2. Program &amp; Kegiatan RKBMD</h2>
                    <p class="text-[11px] text-slate-400">Penetapan kegiatan berdasarkan master RKBMD</p>
                </div>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Bidang Urusan</span>
                    <span class="mt-0.5 block font-bold text-slate-800">
                        {{ $usulan->bidang_urusan ?: ($usulan->kegiatan?->bidang_urusan ?: 'URUSAN PEMERINTAHAN BIDANG PERTANIAN') }}
                    </span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Program</span>
                    <span class="mt-0.5 block font-bold text-slate-800">
                        {{ $usulan->nama_program ?: ($usulan->kegiatan?->nama_program ?: '-') }}
                    </span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Sub Kegiatan</span>
                    <span class="mt-0.5 block font-bold text-slate-800">
                        {{ $usulan->nama_sub_kegiatan ?: ($usulan->kegiatan?->nama_sub_kegiatan ?: '-') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS 2: Rincian Barang yang Diusulkan, Kebutuhan Maksimum & Riil, serta Tabel Optimalisasi --}}
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft space-y-6">
        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-slate-900">3. Rincian Barang yang Diusulkan &amp; Analisis Kebutuhan</h2>
                <p class="text-[11px] text-slate-400">Detail barang, kuantitas kebutuhan, dan optimalisasi barang yang ada</p>
            </div>
        </div>

        {{-- 3.1 Data Pokok Barang & Kuantitas --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-xs">
            <div>
                <span class="block text-[11px] font-semibold text-slate-400">Kode Barang</span>
                <span class="mt-1 inline-block rounded-lg bg-emerald-50 px-2.5 py-1 font-mono text-xs font-bold text-emerald-800 border border-emerald-200/60">
                    {{ $usulan->kode_barang ?: '-' }}
                </span>
            </div>
            <div>
                <span class="block text-[11px] font-semibold text-slate-400">Nama Barang</span>
                <span class="mt-1 block text-sm font-bold text-slate-900">{{ $usulan->nama_barang }}</span>
            </div>
            <div>
                <span class="block text-[11px] font-semibold text-slate-400">Jumlah Kebutuhan</span>
                <span class="mt-1 block text-lg font-extrabold text-slate-900">
                    {{ $usulan->jumlah }} <span class="text-xs font-semibold text-slate-500">{{ $usulan->satuan }}</span>
                </span>
            </div>
        </div>

        {{-- 4. KEBUTUHAN MAKSIMUM & KEBUTUHAN RIIL (Berdampingan) --}}
        <div>
            <span class="block text-xs font-bold text-slate-700 mb-2.5">4. Perbandingan Kebutuhan Maksimum &amp; Kebutuhan Riil</span>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kebutuhan Maksimum</span>
                        <span class="text-[10px] text-slate-400">Standar Kebutuhan</span>
                    </div>
                    <p class="mt-1 text-2xl font-extrabold text-slate-800">
                        {{ $usulan->kebutuhan_maksimum ?? 0 }} <span class="text-xs font-semibold text-slate-500">{{ $usulan->satuan }}</span>
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">Batas maksimum kepemilikan BMD sesuai standar sarana &amp; prasarana.</p>
                </div>
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Kebutuhan Riil</span>
                        <span class="text-[10px] font-semibold text-emerald-600">Hasil Analisis</span>
                    </div>
                    <p class="mt-1 text-2xl font-extrabold text-emerald-700">
                        {{ $usulan->kebutuhan_riil ?? 0 }} <span class="text-xs font-semibold text-emerald-600">{{ $usulan->satuan }}</span>
                    </p>
                    <p class="mt-1 text-[11px] text-emerald-700/80">Kebutuhan riil setelah dikurangi barang eksisting yang dapat dioptimalkan.</p>
                </div>
            </div>
        </div>

        {{-- 5. DAFTAR BARANG YANG DAPAT DIOPTIMALISASI (Tabel Terstruktur) --}}
        <div>
            <div class="mb-2.5 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700">5. Daftar Barang yang Dapat Dioptimalisasi</span>
                <span class="text-[10px] text-slate-400">Tabel Rincian Optimalisasi</span>
            </div>
            <div class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-3.5 py-2.5">Kode Barang Lama</th>
                            <th class="px-3.5 py-2.5">Nama Barang Lama</th>
                            <th class="px-3.5 py-2.5 text-center">Jumlah</th>
                            <th class="px-3.5 py-2.5 text-center">Satuan</th>
                            <th class="px-3.5 py-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if ($adaOpt)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-3.5 py-3 font-mono text-[11px] font-bold text-slate-700">{{ $opt['kode'] ?: '-' }}</td>
                                <td class="px-3.5 py-3 font-semibold text-slate-900">{{ $opt['nama'] ?: '-' }}</td>
                                <td class="px-3.5 py-3 text-center font-bold text-slate-900">{{ $opt['jumlah'] ?: '0' }}</td>
                                <td class="px-3.5 py-3 text-center text-slate-600">{{ $opt['satuan'] ?? $usulan->satuan }}</td>
                                <td class="px-3.5 py-3 text-center">
                                    <span class="inline-block rounded-md border border-emerald-200/80 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                        Dioptimalkan
                                    </span>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td colspan="5" class="px-3.5 py-4 text-center text-[11px] text-slate-400 italic">
                                    Tidak ada barang yang dioptimalkan (kebutuhan pengadaan murni).
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- BARIS 3: Spesifikasi Teknis & Alasan Kebutuhan (2 Kolom Berdampingan) --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- 6. SPESIFIKASI TEKNIS --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft space-y-3">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">6. Spesifikasi Teknis</h2>
                    <p class="text-[11px] text-slate-400">Deskripsi teknis barang yang diusulkan</p>
                </div>
            </div>
            <div class="min-h-[120px] rounded-2xl border border-slate-100 bg-slate-50/60 p-4 text-xs leading-relaxed text-slate-700 whitespace-pre-line">
                {{ $usulan->spesifikasi ?: 'Tidak ada spesifikasi khusus tercatat.' }}
            </div>
        </div>

        {{-- 7. ALASAN KEBUTUHAN --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft space-y-3">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">7. Alasan Kebutuhan</h2>
                    <p class="text-[11px] text-slate-400">Justifikasi urgensi kebutuhan barang</p>
                </div>
            </div>
            <div class="min-h-[120px] rounded-2xl border border-slate-100 bg-slate-50/60 p-4 text-xs leading-relaxed text-slate-700 whitespace-pre-line">
                {{ $usulan->alasan_kebutuhan ?: 'Tidak ada justifikasi kebutuhan tercatat.' }}
            </div>
        </div>
    </div>

    {{-- BARIS 4: Catatan Pengurus Barang & Status Riwayat Verifikasi --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- 8. CATATAN PENGURUS BARANG --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft space-y-3">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">8. Catatan Pengurus Barang</h2>
                    <p class="text-[11px] text-slate-400">Catatan koreksi, evaluasi, atau justifikasi penolakan</p>
                </div>
            </div>

            @if ($usulan->catatan_pengurus)
                <div class="rounded-2xl border border-rose-200/80 bg-rose-50/60 p-4 text-xs leading-relaxed text-rose-800 whitespace-pre-line">
                    {{ $usulan->catatan_pengurus }}
                </div>
            @else
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 text-xs text-slate-400 italic">
                    Tidak ada catatan pengurus barang untuk usulan ini.
                </div>
            @endif
        </div>

        {{-- 9. STATUS DAN RIWAYAT VERIFIKASI --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">9. Status &amp; Riwayat Verifikasi</h2>
                    <p class="text-[11px] text-slate-400">Informasi verifikasi dan penetapan akhir</p>
                </div>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400">Status Saat Ini</span>
                    <span class="mt-1 inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $badgeStatus }}">
                        <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current opacity-75"></span>
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <span class="block text-[11px] font-semibold text-slate-400">Waktu Verifikasi</span>
                    <span class="mt-0.5 block font-semibold text-slate-800">
                        {{ $usulan->approved_at ? $usulan->approved_at->format('d F Y, H:i') . ' WIB' : 'Belum diverifikasi' }}
                    </span>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <span class="block text-[11px] font-semibold text-slate-400">Petugas Verifikasi</span>
                    @if ($usulan->approver)
                        <span class="mt-0.5 block font-bold text-slate-800">
                            {{ $usulan->approver->namaLengkap() ?? $usulan->approver->username }}
                        </span>
                        <span class="text-[10px] text-slate-400">Pengurus Barang / Admin Aset</span>
                    @else
                        <span class="mt-0.5 block text-slate-400 italic">Menunggu verifikasi Super Admin</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('modals')
    {{-- MODAL TOLAK (Khusus Super Admin bila usulan berstatus Diajukan) --}}
    @if ($isAdmin && $usulan->status_usulan === 'Diajukan')
        <div id="modalTolakHalaman" class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-modal>
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs" data-close></div>
            <div class="relative w-full max-w-md rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Tolak Usulan RKBMD</h3>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $usulan->nama_barang }}</p>
                        </div>
                    </div>
                    <button type="button" data-close class="text-slate-400 transition hover:text-slate-600" title="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('rkbmd.decide', $usulan->id_usulan) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="keputusan" value="tolak">
                    <div>
                        <label for="catatan-tolak-page" class="mb-1.5 block text-xs font-semibold text-slate-700">
                            Alasan Penolakan <span class="text-rose-500">*</span>
                        </label>
                        <textarea id="catatan-tolak-page" name="catatan_pengurus" rows="4" maxlength="1000" required
                                  placeholder="Tuliskan justifikasi penolakan usulan..."
                                  class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 shadow-2xs placeholder:text-slate-400 focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-400"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" data-close
                                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-700">
                            Kirim Penolakan
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-open-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-open-modal');
                const modal = document.getElementById(targetId);
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            });
        });

        document.querySelectorAll('[data-modal]').forEach(function (modal) {
            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            modal.querySelectorAll('[data-close]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-modal]').forEach(function (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                });
            }
        });
    });
</script>
@endpush

@endsection
