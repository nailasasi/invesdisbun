@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $backUrl = url()->previous() != url()->current()
        ? url()->previous()
        : route('tanah.index');

    $isAdmin = in_array(
        auth()->user()?->role?->nama_role ?? '',
        ['Admin Aset', 'Admin UPT P2DP']
    );

    $roleLower = strtolower((string) (
        auth()->user()?->role?->nama_role
        ?? auth()->user()?->level
        ?? ''
    ));
    $canEdit = in_array($roleLower, [
        'admin',
        'admin_aset',
        'admin aset',
        'admin_p2btp',
        'admin p2btp',
        'upt p2btp',
        'p2btp',
    ]);

    $kondisiTeks = trim((string) ($tanah->kondisi ?? ''));
    $kondisiColor = match ($kondisiTeks) {
        'Baik' => 'bg-disbun-100 text-disbun-800',
        'Rusak Ringan' => 'bg-amber-100 text-amber-700',
        'Rusak Berat' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };

    $retribusiRows = $tanah->retribusi->sortByDesc('tahun')->values();
    $sumCol = fn ($col) => (float) $retribusiRows->sum(fn ($r) => (float) ($r->$col ?? 0));
    $totalBiayaKebun = $sumCol('biaya_pengurusan');
    $totalPad = $sumCol('PAD');
    $totalTarif = $sumCol('tarif_retribusi');
    $totalSewa = $sumCol('total_tarif_sewa');
    $satuanTarif = $retribusiRows->first(fn ($r) => !empty($r->satuan))?->satuan ?? '-';
    $latestRetribusi = $retribusiRows->first();

    $rupiah = fn ($n) => $n > 0
        ? 'Rp ' . number_format((float) $n, 0, ',', '.')
        : '-';
    $rupiah0 = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

    $fotoUrl = null;
    if (!empty($tanah->foto_tanah)) {
        $f = $tanah->foto_tanah;
        if (Str::startsWith(Str::lower($f), ['http://', 'https://'])) {
            $fotoUrl = $f;
        } elseif (Storage::disk('public')->exists($f)) {
            $fotoUrl = Storage::disk('public')->url($f);
        } elseif (Storage::disk('local')->exists($f)) {
            $fotoUrl = Storage::url($f);
        }
    }

    $formatTanggal = fn ($t) => $t?->format('d M Y') ?? '-';
    $namaUser = fn ($u) => $u?->pegawai?->nama_pegawai ?? $u?->username ?? 'Sistem';
@endphp

@extends('layouts.app')

@section('title', 'Detail Tanah')
@section('page-title', 'Detail Tanah')
@section('breadcrumb', 'Inventaris Tanah • Detail Aset')

@section('content')
    {{-- Kembali minimalis --}}
    <div class="mb-3">
        <a href="{{ $backUrl }}"
           class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Kembali ke Inventaris Tanah</span>
        </a>
    </div>

    {{-- Breadcrumb --}}
    <div class="mb-3">
        <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
            <a href="{{ route('tanah.index') }}"
               class="transition hover:text-slate-900">
                Inventaris Tanah
            </a>
            <span class="text-slate-300">•</span>
            <span class="text-slate-400">Detail Aset</span>
        </nav>
    </div>

    {{-- Header Judul + Aksi --}}
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-800">Detail Tanah</h1>
            <p class="mt-0.5 truncate font-mono text-xs text-slate-500">
                {{ $tanah->kib ? 'KIB ' . $tanah->kib : 'Aset Tanah' }}
                — {{ $tanah->deskripsi_objek ?: ($tanah->alamat ?: 'Aset Tanah') }}
            </p>
        </div>
        @if ($canEdit)
            <a href="{{ route('tanah.edit', $tanah->id_tanah) }}"
               class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-disbun-700 px-4 text-xs font-bold text-white shadow-2xs transition hover:bg-disbun-800">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Edit Data</span>
            </a>
        @endif
    </div>

    {{-- Error dari modal (upload PBB) --}}
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.82 15A2 2 0 003.2 22h17.6a2 2 0 001.73-3.14l-8.82-15a2 2 0 00-3.42 0Z"/></svg>
                <div>
                    <p class="text-sm font-semibold text-red-700">Data belum dapat disimpan</p>
                    <ul class="mt-1 list-inside list-disc text-sm text-red-600">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Layout 2 Kolom: 4 kiri / 8 kanan --}}
    <div class="mt-4 grid grid-cols-1 items-start gap-5 lg:grid-cols-12">

        {{-- ============ KIRI: HERO & SUMMARY ============ --}}
        <div class="space-y-4 lg:col-span-4">
            <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-2xs">
                {{-- Media: foto aset --}}
                <div class="relative h-48 w-full overflow-hidden rounded-2xl border border-slate-100 bg-slate-100 sm:h-52">
                    @if ($fotoUrl)
                        <img src="{{ $fotoUrl }}" alt="Foto aset tanah" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full flex-col items-center justify-center">
                            <svg class="h-14 w-14 shrink-0 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01"/>
                            </svg>
                            <p class="mt-2 text-xs font-semibold text-emerald-400">Belum ada foto aset</p>
                        </div>
                    @endif
                </div>

                {{-- Body hero --}}
                <div class="p-5">
<div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">KIB</p>
                                <p class="mt-0.5 truncate font-mono text-lg font-bold text-slate-900">{{ $tanah->kib ?: '-' }}</p>
                            </div>
                        </div>

                    <p class="mt-2 text-sm text-slate-600">{{ $tanah->deskripsi_objek ?: 'Belum ada deskripsi objek.' }}</p>

                    @if ($tanah->penggunaan)
                        <span class="mt-3 inline-flex items-center rounded-full bg-disbun-50 px-2.5 py-0.5 text-xs font-medium text-disbun-800">
                            <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-disbun-600"></span>
                            {{ $tanah->penggunaan }}
                        </span>
                    @endif

                    {{-- Ringkasan info --}}
                    <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Petugas Pemelihara</p>
                                <p class="truncate text-sm font-medium text-slate-800">{{ $tanah->nama_petugas ?: '-' }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Kontak No HP</p>
                                @if ($tanah->nomor_hp_petugas)
                                    <a href="tel:{{ $tanah->nomor_hp_petugas }}" class="truncate text-sm font-medium text-slate-800 transition hover:text-disbun-700">{{ $tanah->nomor_hp_petugas }}</a>
                                @else
                                    <p class="truncate text-sm font-medium text-slate-800">-</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Kondisi Aset</p>
                                <span class="mt-0.5 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase {{ $kondisiColor }}">{{ $kondisiTeks ?: '-' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Aksi lokasi --}}
                    @if ($tanah->google_maps)
                        <a href="{{ $tanah->google_maps }}" target="_blank" rel="noopener noreferrer"
                           class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-disbun-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Buka Lokasi di Google Maps</span>
                        </a>
                    @else
                        <div class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-medium text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Lokasi belum dicantumkan</span>
                        </div>
                    @endif

                    {{-- Footer: editor terakhir --}}
                    @php
                        $lastEdit = $tanah->histories()->latest()->first();
                    @endphp
                    @if ($lastEdit)
                        <div class="mt-2 flex items-center gap-1.5 border-t border-slate-100 pt-2 text-[11px] text-slate-400">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Terakhir diedit oleh:</span>
                            <span class="font-semibold text-slate-500">{{ $lastEdit->user?->pegawai?->nama_pegawai ?? $lastEdit->user?->username ?? 'Admin' }}</span>
                            <span>•</span>
                            <span>{{ \Carbon\Carbon::parse($lastEdit->created_at)->diffForHumans() }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ KANAN: TABBED CONTENT ============ --}}
        <div class="space-y-4 lg:col-span-8">
            {{-- Tab Pills --}}
            <div class="overflow-x-auto">
                <div class="flex w-max min-w-full items-center gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-2xs">
                    <button type="button" data-tab-target="tab-kib"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl bg-disbun-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Data KIB A &amp; Legalitas
                    </button>
                    <button type="button" data-tab-target="tab-retribusi"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Pemanfaatan &amp; Retribusi
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $tanah->retribusi->count() }}</span>
                    </button>
                    <button type="button" data-tab-target="tab-pbb"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Dokumen PBB
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $tanah->dokumenPbb->count() }}</span>
                    </button>
                    <button type="button" data-tab-target="tab-history"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition" role="tab">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Riwayat Perubahan
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $tanah->histories->count() }}</span>
                    </button>
                </div>
            </div>

            {{-- ===== TAB 1: KIB & LEGALITAS ===== --}}
            <div data-tab-panel id="tab-kib" class="mt-4">
                <x-card :padding="false">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h3 class="text-base font-bold text-slate-900">Data KIB A &amp; Legalitas</h3>
                        <p class="text-xs text-slate-400">Informasi dasar, fisik, dan legalitas aset tanah</p>
                    </div>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">KIB</dt>
                            <dd class="font-mono text-sm font-bold text-slate-900">{{ $tanah->kib ?: '-' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Luas Tanah</dt>
                            <dd class="text-sm text-slate-900">
                                {{ $tanah->luas_tanah !== null ? number_format((float) $tanah->luas_tanah, 2, ',', '.') . ' m²' : '-' }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nilai Perolehan</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $rupiah((float) ($tanah->nilai_perolehan ?? 0)) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status Hak</dt>
                            <dd class="text-sm text-slate-900">{{ $tanah->status_hak ?: '-' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Sertifikat</dt>
                            <dd class="text-sm font-medium text-slate-900">{{ $tanah->nomor_sertifikat ?: '-' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Sertifikat</dt>
                            <dd class="text-sm text-slate-900">{{ $formatTanggal($tanah->tanggal_sertifikat) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Buku</dt>
                            <dd class="text-sm text-slate-900">{{ $formatTanggal($tanah->tanggal_buku) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Perolehan</dt>
                            <dd class="text-sm text-slate-900">{{ $formatTanggal($tanah->tanggal_perolehan) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Ketkel / Kecamatan</dt>
                            <dd class="text-sm text-slate-900">{{ $tanah->ketkel ?: '-' }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Alamat Lengkap</dt>
                            <dd class="text-sm text-slate-900">{{ $tanah->alamat ?: '-' }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Deskripsi Objek</dt>
                            <dd class="text-sm text-slate-900">{{ $tanah->deskripsi_objek ?: '-' }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Keterangan</dt>
                            <dd class="text-sm text-slate-900">{{ $tanah->keterangan ?: '-' }}</dd>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- ===== TAB 2: PEMANFAATAN & RETRIBUSI ===== --}}
            <div data-tab-panel id="tab-retribusi" class="mt-4 hidden">
                <x-card :padding="false">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Pemanfaatan &amp; Retribusi</h3>
                            <p class="text-xs text-slate-400">Perhitungan pemanfaatan aset tanah (kebun) oleh pihak ketiga</p>
                        </div>
                        @if ($isAdmin)
                            <a href="{{ route('tanah.retribusi.create', $tanah) }}"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-disbun-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                <span>Tambah Retribusi</span>
                            </a>
                        @endif
                    </div>

                    <div class="p-6">
                        {{-- Metrik nominal --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Biaya Kebun</p>
                                <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $rupiah($totalBiayaKebun) }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                            </div>
                            <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50/60 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Realisasi PAD</p>
                                <p class="mt-1 text-lg font-extrabold text-emerald-700">{{ $rupiah($totalPad) }}</p>
                                <p class="mt-0.5 text-[11px] text-emerald-500/70">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tarif Retribusi</p>
                                <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $rupiah($totalTarif) }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Potensi Sewa</p>
                                <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $rupiah($totalSewa) }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                            </div>
                        </div>

                        {{-- Detail pelengkap --}}
                        <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                            <div class="space-y-1">
                                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Satuan Tarif</dt>
                                <dd class="text-sm font-medium text-slate-900">{{ $satuanTarif }}</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Sumber Air</dt>
                                <dd class="text-sm text-slate-900">{{ $tanah->penggunaan_air ?: '-' }}</dd>
                            </div>
                            <div class="space-y-1 sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status Retribusi</dt>
                                <dd class="text-sm">
                                    @if ($latestRetribusi)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $latestRetribusi->status_pemanfaatan ?: 'Belum ditetapkan' }}</span>
                                        <span class="ml-2 text-xs text-slate-400">Tahun {{ $latestRetribusi->tahun }}</span>
                                    @else
                                        <span class="text-slate-400">Belum ada data pemanfaatan.</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="space-y-1 sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Keterangan</dt>
                                <dd class="text-sm text-slate-900">{{ $latestRetribusi?->keterangan ?: '-' }}</dd>
                            </div>
                        </div>
                    </div>

                    {{-- Rincian per tahun --}}
                    @if ($retribusiRows->count())
                        <div class="overflow-x-auto border-t border-slate-100">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tahun</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Biaya Kebun</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Realisasi PAD</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tarif</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Total Sewa</th>
                                        @if ($isAdmin)
                                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($retribusiRows as $r)
                                        <tr class="transition hover:bg-slate-50">
                                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $r->tahun }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $r->status_pemanfaatan ?: '-' }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->biaya_pengurusan) }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->PAD) }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->tarif_retribusi) }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->total_tarif_sewa) }}</td>
                                            @if ($isAdmin)
                                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                    <a href="{{ route('tanah.retribusi.edit', $r) }}" class="inline-flex items-center rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-100">Edit</a>
                                                    <form action="{{ route('tanah.retribusi.destroy', $r) }}" method="POST" class="ml-1 inline" onsubmit="return confirm('Hapus data retribusi tahun {{ $r->tahun }} ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">Hapus</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6">
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center">
                                <p class="text-sm font-medium text-slate-700">Belum ada data pemanfaatan &amp; retribusi.</p>
                                <p class="mt-1 text-sm text-slate-500">Data retribusi tahunan aset akan tampil di sini.</p>
                            </div>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ===== TAB 3: DOKUMEN PBB ===== --}}
            <div data-tab-panel id="tab-pbb" class="mt-4 hidden">
                <x-card :padding="false">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Dokumen PBB</h3>
                            <p class="text-xs text-slate-400">Arsip file SPPT / PBB yang terunggah untuk aset ini</p>
                        </div>
                        @if ($isAdmin)
                            <button type="button" data-open-pbb-modal
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-disbun-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                <span>Tambah Dokumen PBB</span>
                            </button>
                        @endif
                    </div>

                    @if ($tanah->dokumenPbb->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tahun PBB</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Upload</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Diunggah Oleh</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">File</th>
                                        @if ($isAdmin)
                                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($tanah->dokumenPbb as $pbb)
                                        <tr class="transition hover:bg-slate-50">
                                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $pbb->tahun_pbb ?: '-' }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pbb->tanggal_upload?->format('d M Y H:i') ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $namaUser($pbb->uploader) }}</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                                @if ($pbb->file_pbb)
                                                    <div class="flex items-center gap-1.5">
                                                        <a href="{{ Storage::url($pbb->file_pbb) }}" target="_blank" rel="noopener noreferrer"
                                                           class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                                            <svg class="mr-1 h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                            Lihat
                                                        </a>
                                                        <a href="{{ Storage::url($pbb->file_pbb) }}" download
                                                           class="inline-flex items-center rounded-lg bg-disbun-50 px-2.5 py-1.5 text-xs font-medium text-disbun-800 transition hover:bg-disbun-100">
                                                            <svg class="mr-1 h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            Unduh
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-slate-400">-</span>
                                                @endif
                                            </td>
                                            @if ($isAdmin)
                                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                    <form action="{{ route('tanah.dokumen.destroy', $pbb) }}" method="POST" onsubmit="return confirm('Hapus dokumen PBB ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">Hapus</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6">
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center">
                                <p class="text-sm font-medium text-slate-700">Belum ada dokumen PBB.</p>
                                <p class="mt-1 text-sm text-slate-500">Unggah file SPPT / PBB untuk melengkapi arsip aset ini.</p>
                            </div>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- ===== TAB 4: RIWAYAT PERUBAHAN ===== --}}
            <div data-tab-panel id="tab-history" class="mt-4 hidden">
                <div class="space-y-4 rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
                    <div class="border-b border-slate-100 pb-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            {{ $tanah->histories->count() ? 'Riwayat Aktivitas & Perubahan' : 'Riwayat Perubahan' }}
                        </h3>
                        <p class="text-[11px] text-slate-500">Catatan pengguna yang menambah atau memperbarui data tanah</p>
                    </div>

                    <div class="divide-y divide-slate-100 text-xs">
                        @forelse ($tanah->histories->sortByDesc('created_at') as $history)
                            @php
                                $isCreate = strtoupper((string) $history->aksi) === 'CREATE';
                                $user = $history->user;
                                $userName = $user?->pegawai?->nama_pegawai ?? $user?->username ?? 'Pengguna Sistem';
                                $userRole = $user?->role?->nama_role ?? $user?->pegawai?->jabatan ?? 'Admin';
                            @endphp
                            <div class="flex items-start justify-between gap-4 py-3.5">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-xl {{ $isCreate ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600' }}">
                                        @if ($isCreate)
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        @else
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        @endif
                                    </div>

                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ $isCreate ? 'bg-disbun-100 text-disbun-800' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $isCreate ? 'Tambah Data' : 'Edit Data' }}
                                            </span>
                                            <span class="font-bold text-slate-800">{{ $userName }}</span>
                                            <span class="text-[11px] text-slate-400">({{ $userRole }})</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-500">
                                            {{ $isCreate ? 'Menambahkan aset tanah baru ke dalam sistem' : 'Melakukan pembaruan informasi data tanah' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <span class="block font-mono text-[11px] text-slate-600">
                                        {{ \Carbon\Carbon::parse($history->created_at)->format('d M Y, H:i') }} WIB
                                    </span>
                                    <span class="text-[10px] text-slate-400">
                                        {{ \Carbon\Carbon::parse($history->created_at)->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">
                                Belum ada riwayat aktivitas.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL TAMBAH DOKUMEN PBB --}}
    @if ($isAdmin)
        <div id="dokumen-pbb-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="fixed inset-0" data-modal-close></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Tambah Dokumen PBB</h3>
                        <p class="text-xs text-slate-400">KIB {{ $tanah->kib ?: '-' }}</p>
                    </div>
                    <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form action="{{ route('tanah.dokumen.store', $tanah) }}" method="POST" enctype="multipart/form-data" class="space-y-4 px-6 py-6">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="pbb-tahun" class="block text-sm font-medium text-slate-700">Tahun PBB</label>
                        <input type="number" id="pbb-tahun" name="tahun_pbb" min="1900" max="2100" placeholder="contoh: 2025"
                               class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                    </div>
                    <div class="space-y-1.5">
                        <label for="pbb-file" class="block text-sm font-medium text-slate-700">File PBB <span class="text-red-500">*</span></label>
                        <input type="file" id="pbb-file" name="file_pbb" accept=".pdf,image/jpeg,image/png" required
                               class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 file:mr-3 file:rounded-lg file:border-0 file:bg-disbun-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-disbun-800 hover:file:bg-disbun-100">
                        <p class="text-xs text-slate-400">Format: PDF/JPG/PNG, maksimal 5MB.</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                        <button type="submit" class="rounded-xl bg-disbun-700 px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-disbun-800">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
        (function () {
            // Tabs
            const tabBtns = Array.from(document.querySelectorAll('[data-tab-target]'));
            const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));

            function activateTab(id) {
                tabBtns.forEach((b) => {
                    const on = b.dataset.tabTarget === id;
                    b.classList.toggle('bg-disbun-700', on);
                    b.classList.toggle('text-white', on);
                    b.classList.toggle('shadow-xs', on);
                    b.classList.toggle('text-slate-600', !on);
                });
                tabPanels.forEach((p) => p.classList.toggle('hidden', p.id !== id));
            }

            tabBtns.forEach((b) => b.addEventListener('click', () => activateTab(b.dataset.tabTarget)));

            // Modal Dokumen PBB
            const pbbModal = document.getElementById('dokumen-pbb-modal');
            if (pbbModal) {
                document.querySelectorAll('[data-open-pbb-modal]').forEach((b) => {
                    b.addEventListener('click', () => {
                        pbbModal.classList.remove('hidden');
                        pbbModal.classList.add('flex');
                        document.body.classList.add('overflow-hidden');
                    });
                });
                pbbModal.querySelectorAll('[data-modal-close]').forEach((x) => {
                    x.addEventListener('click', () => {
                        pbbModal.classList.add('hidden');
                        pbbModal.classList.remove('flex');
                        document.body.classList.remove('overflow-hidden');
                    });
                });
            }
        })();
    </script>
    @endpush
@endsection
