@extends('layouts.app')

@section('title', 'Aset Barang')

@section('page-title', 'Aset Barang')

@section('breadcrumb', 'Kelola, pantau, dan cetak label aset barang beserta pemegang dan lokasinya')

@section('content')
    @if (session('import_summary'))
        @php $summary = session('import_summary'); @endphp
        <div class="mb-6 rounded-2xl border p-4 text-sm shadow-sm {{ $summary['status'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($summary['status'] === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-rose-200 bg-rose-50 text-rose-900') }}">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <h4 class="font-bold text-base">{{ $summary['title'] }}</h4>
                    <p class="font-medium">
                        Berhasil: <span class="font-bold text-emerald-700">{{ $summary['berhasil'] }} barang</span> &bull; 
                        Gagal: <span class="font-bold {{ $summary['gagal'] > 0 ? 'text-rose-700' : 'text-slate-600' }}">{{ $summary['gagal'] }} barang</span>
                    </p>
                </div>
                <button type="button" class="shrink-0 rounded-md p-1 opacity-70 hover:opacity-100" onclick="this.closest('div').remove()" aria-label="Tutup notifikasi">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @if (!empty($summary['errors']))
                <div class="mt-3 border-t border-slate-200/60 pt-3">
                    <p class="font-semibold mb-1 text-xs uppercase tracking-wider text-slate-700">Detail kegagalan:</p>
                    <ul class="max-h-48 overflow-y-auto space-y-1 text-xs font-mono list-disc list-inside">
                        @foreach ($summary['errors'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @else
        <x-alert type="success" />
        <x-alert type="warning" />
        <x-alert type="error" />
    @endif

    <x-card :padding="false">
        <div class="border-b border-disbun-card-border p-3 sm:px-4">
            <div class="overflow-x-auto">
                <form method="GET" action="{{ route('aset-barang.index') }}" class="flex items-center gap-2 w-full flex-nowrap">
                    {{-- 1. Field Pencarian (fleksibel 250-280px) --}}
                    <div class="relative flex-1 min-w-[200px] max-w-[280px] shrink">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Cari nama, kartu, pemegang barang..."
                            aria-label="Cari nama, kartu, pemegang barang"
                            autocomplete="off"
                            class="block h-9 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-xs text-slate-900 shadow-2xs transition placeholder:text-slate-400 focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400"
                        >
                    </div>

                    {{-- 2. Filter Semua Lokasi (130-140px) --}}
                    <select name="lokasi" aria-label="Filter lokasi" class="block h-9 w-[135px] shrink-0 rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-2xs transition focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400 truncate">
                        <option value="">Semua Lokasi</option>
                        @foreach ($lokasiFilterOptions as $lokasi)
                            <option value="{{ $lokasi->id_lokasi }}" @selected((string) request('lokasi') === (string) $lokasi->id_lokasi)>{{ $lokasi->nama_lokasi }}</option>
                        @endforeach
                        <option value="belum_ada" @selected(request('lokasi') === 'belum_ada')>Belum Ada Lokasi</option>
                    </select>

                    {{-- 3. Filter Semua Kondisi (120-130px) --}}
                    <select name="kondisi" aria-label="Filter kondisi" class="block h-9 w-[125px] shrink-0 rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-2xs transition focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400">
                        <option value="">Semua Kondisi</option>
                        <option value="Baik" @selected(request('kondisi') === 'Baik')>Baik</option>
                        <option value="Rusak Ringan" @selected(request('kondisi') === 'Rusak Ringan')>Rusak Ringan</option>
                    </select>

                    {{-- 4. Tombol Cari (65-75px) & Reset --}}
                    <button type="submit" class="inline-flex h-9 w-[68px] shrink-0 items-center justify-center gap-1 rounded-xl bg-disbun-700 text-xs font-bold text-white shadow-2xs transition hover:bg-disbun-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        <span>Cari</span>
                    </button>
                    @if (request('search') || request('lokasi') || request('kondisi'))
                        <a href="{{ route('aset-barang.index') }}" title="Reset Filter" class="inline-flex h-9 px-2.5 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                            Reset
                        </a>
                    @endif

                    {{-- 5. Tombol Finalisasi Terpilih (135-145px) --}}
                    @if ($canManage)
                        <button type="button" id="btn-finalisasi-terpilih" disabled
                                class="inline-flex h-9 w-[140px] shrink-0 items-center justify-center rounded-xl border border-disbun-card-border bg-white px-2 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 disabled:opacity-50 disabled:pointer-events-none whitespace-nowrap">
                            Finalisasi Terpilih (0)
                        </button>

                        {{-- 6. Tombol Import Excel (110-120px) --}}
                        @if ($isAdminAset)
                            <button type="button" id="btn-import"
                                    class="inline-flex h-9 w-[110px] shrink-0 items-center justify-center gap-1.5 rounded-xl border border-disbun-card-border bg-white px-2 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 whitespace-nowrap">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4m-9 8H5a2 2 0 00-2 2v2a2 2 0 002 2h14a2 2 0 002-2v-2a2 2 0 00-2-2h-2"/></svg>
                                <span>Import Excel</span>
                            </button>
                        @endif

                        {{-- 7. Tombol Tambah Aset (115-125px) --}}
                        <button type="button" id="btn-tambah"
                                class="inline-flex h-9 w-[115px] shrink-0 items-center justify-center gap-1.5 rounded-xl bg-disbun-700 px-2 text-xs font-bold text-white shadow-2xs transition hover:bg-disbun-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 whitespace-nowrap">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span>Tambah Aset</span>
                        </button>
                    @endif
                </form>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @if ($canManage)
                            <th scope="col" class="w-10 px-4 py-3 text-center">
                                <span class="sr-only">Pilih semua untuk finalisasi</span>
                                <input type="checkbox" id="pilih-semua-finalisasi" disabled class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-disbun-700 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Pilih semua aset yang memenuhi syarat">
                            </th>
                        @endif
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kartu Barang</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lokasi</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kondisi</th>
                        @if ($isAdminAset)
                            <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SPPBI</th>
                        @endif
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($asetList as $item)
                        @php
                            $aset = $item;
                            $pemegang = $aset->pemegangSaatIni?->pegawai;
                            $penempatanAktif = $aset->penempatanAktif;
                            $isFinalized = $penempatanAktif?->is_finalized;
                            $hasPenempatanAktif = (bool) $penempatanAktif;
                            // Flag dari backend (App\Services\AsetPenempatan) —
                            // sumber kebenaran yang sama dengan validasi server.
                            $bolehMutasi = (bool) ($aset->boleh_mutasi ?? false);
                            // Mutasi butuh penempatan aktif yang sudah difinalisasi
                            // (is_finalized = 1). Status 0 dan NULL menutup ikon.
                            $bolehMutasiFinal = (bool) ($aset->boleh_mutasi_final ?? false);
                            $bolehEditFinalisasi = (bool) ($aset->boleh_edit_finalisasi ?? false);
                            $kondisiColor = match ($aset->kondisi) {
                                'Baik' => 'bg-disbun-100 text-disbun-800',
                                'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                                'Rusak Berat' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            @if ($canManage)
                                <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                    @if ($bolehEditFinalisasi)
                                        <input type="checkbox" name="id_aset[]" value="{{ $aset->id_aset }}" data-finalisasi-checkbox class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-disbun-700" aria-label="Pilih aset untuk difinalisasi">
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                <a href="{{ route('aset-barang.aset.detail', $aset->id_aset) }}" class="font-medium text-disbun-700 transition hover:text-disbun-800 hover:underline">
                                    {{ $aset->barang?->nama_barang ?? '-' }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-xs text-slate-600">
                                <div title="{{ $aset->nomor_kartu_barang }}" class="inline-flex max-w-[200px] items-center gap-1.5 rounded-lg border border-slate-200/80 bg-slate-50/80 px-2 py-0.5">
                                    <span class="block max-w-[160px] truncate font-mono text-xs text-slate-700 leading-normal">{{ $aset->nomor_kartu_barang ?? '-' }}</span>
                                    @if ($aset->nomor_kartu_barang)
                                        <button type="button" data-copy-kartu data-copy-value="{{ $aset->nomor_kartu_barang }}" title="Salin Nomor Kartu" class="inline-flex items-center justify-center shrink-0 text-slate-400 transition hover:text-slate-600">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                @if ($pemegang)
                                    <span class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('aset-barang.show', ['pegawai' => $pemegang->id_pegawai, 'from' => 'aset-barang']) }}" class="font-medium text-disbun-700 transition hover:text-disbun-800 hover:underline">
                                            {{ $pemegang->nama_pegawai }}
                                        </a>
                                        @if ($canManage && $bolehEditFinalisasi)
                                            {{-- Edit + Finalisasi hanya untuk penempatan aktif yang belum difinalisasi (status 0). --}}
                                            <button type="button" data-edit-penempatan="{{ $aset->id_aset }}" data-mode="pegawai" title="Edit Pemegang" aria-label="Edit pemegang aset" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button type="button" data-finalisasi-penempatan="{{ $aset->id_aset }}" data-finalisasi-nama="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang ?? 'Aset' }}" title="Finalisasi Penempatan" aria-label="Finalisasi penempatan" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        @elseif ($canManage && $hasPenempatanAktif && $isFinalized === null)
                                            {{-- Penempatan LAMA (Perlu Verifikasi / is_finalized NULL): diverifikasi & difinalisasi
                                                 secara eksplisit per aset. --}}
                                            <button type="button" data-finalisasi-penempatan="{{ $aset->id_aset }}" data-finalisasi-url="{{ route('aset-barang.aset.verifikasi-finalisasi', ['aset' => '__ID__']) }}" data-finalisasi-nama="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang ?? 'Aset' }}" data-finalisasi-pesan="Penempatan lama berstatus Perlu Verifikasi. Verifikasi dilakukan manual per aset dan akan mengunci penempatan agar tidak dapat diedit lagi." title="Verifikasi &amp; Finalisasi Penempatan" aria-label="Verifikasi dan finalisasi penempatan" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-sky-50 text-sky-600 transition hover:bg-sky-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </button>
                                        @elseif ($hasPenempatanAktif && $isFinalized === 1)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">Sudah Difinalisasi</span>
                                        @endif
                                    </span>
                                @else
                                    @if (! $hasPenempatanAktif && $canManage)
                                        <button type="button" data-penempatan-awal="{{ $aset->id_aset }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-600 shadow-2xs hover:bg-slate-50" title="Penempatan awal">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                @endif
                            </td>
                            @php $ruanganLokasi = $penempatanAktif?->ruangan; @endphp
                            <td class="min-w-[200px] max-w-[280px] px-5 py-3.5 text-sm text-slate-600">
                                @if ($ruanganLokasi)
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="break-words font-medium text-slate-800">{{ $ruanganLokasi->nama_ruangan }}</span>
                                        @if ($canManage && ! $pemegang && $bolehEditFinalisasi)
                                            {{-- Aset tanpa pemegang aktif: Edit Ruangan + Finalisasi di samping nama ruangan. --}}
                                            <button type="button" data-edit-penempatan="{{ $aset->id_aset }}" data-mode="ruangan" title="Edit Ruangan" aria-label="Edit ruangan aset" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button type="button" data-finalisasi-penempatan="{{ $aset->id_aset }}" data-finalisasi-nama="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang ?? 'Aset' }}" title="Finalisasi Penempatan" aria-label="Finalisasi penempatan" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        @elseif ($canManage && ! $pemegang && $hasPenempatanAktif && $isFinalized === null)
                                            {{-- Penempatan LAMA (Perlu Verifikasi / is_finalized NULL): diverifikasi & difinalisasi
                                                 secara eksplisit per aset. --}}
                                            <button type="button" data-finalisasi-penempatan="{{ $aset->id_aset }}" data-finalisasi-url="{{ route('aset-barang.aset.verifikasi-finalisasi', ['aset' => '__ID__']) }}" data-finalisasi-nama="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang ?? 'Aset' }}" data-finalisasi-pesan="Penempatan lama berstatus Perlu Verifikasi. Verifikasi dilakukan manual per aset dan akan mengunci penempatan agar tidak dapat diedit lagi." title="Verifikasi &amp; Finalisasi Penempatan" aria-label="Verifikasi dan finalisasi penempatan" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-sky-50 text-sky-600 transition hover:bg-sky-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </button>
                                        @elseif (! $pemegang && $hasPenempatanAktif && $isFinalized === 1)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">Sudah Difinalisasi</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 break-words text-xs text-slate-400">{{ $ruanganLokasi->lokasi?->nama_lokasi ?? '-' }}</div>
                                @else
                                    @if (! $hasPenempatanAktif && $canManage && ! $pemegang)
                                        <button type="button" data-penempatan-ruangan="{{ $aset->id_aset }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-600 shadow-2xs hover:bg-slate-50" title="Penempatan awal ruangan">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                            </td>
                            @if ($isAdminAset)
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                    @if ($pemegang && ($aset->siap_sppbi ?? false))
                                        <a href="{{ route('aset-barang.sppbi.download.word', $pemegang->id_pegawai) }}"
                                           title="Unduh SPPBI {{ $pemegang->nama_pegawai }}"
                                           class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                            SPPBI
                                        </a>
                                    @else
                                        {{-- SPPBI hanya tampil bila penempatan aktif sudah difinalisasi (Sudah Difinalisasi). --}}
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3.5 text-sm">
                                <div class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
                                    <a href="{{ route('aset-barang.aset.detail', $aset->id_aset) }}" title="Detail" aria-label="Lihat detail aset" class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if ($canPrintLabel)
                                        <a href="{{ route('aset-barang.cetak.label.single', $aset->id_aset) }}" target="_blank" rel="noopener" title="Cetak Label" aria-label="Cetak label aset" class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.01"/></svg>
                                        </a>
                                    @endif
                                    @if ($canManage)
                                        {{-- Edit Aset Master: tersedia untuk semua aset yang boleh diakses pengelola aset. --}}
                                        <button type="button" data-edit-aset="{{ $aset->id_aset }}" title="Edit Aset" aria-label="Edit aset" class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-disbun-700 transition hover:bg-disbun-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        @if ($bolehMutasi && ($bolehMutasiFinal ?? false))
                                            <button type="button" data-mutasi-modal="{{ $aset->id_aset }}" title="Mutasi" aria-label="Mutasi aset" class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/></svg>
                                            </button>
                                        @endif
                                        <button type="button" data-delete-target="{{ $aset->id_aset }}" data-delete-name="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang }}" title="Usulkan Hapus" aria-label="Ajukan penghapusan aset" class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($canManage ? 1 : 0) + 5 + ($isAdminAset ? 1 : 0) + 1 }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada data aset.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $asetList->links() }}
        </div>
    </x-card>

    @push('scripts')
    <script>
        // Copy nomor kartu barang (clipboard API + fallback)
        function fallbackCopy(text, done) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }

        document.querySelectorAll('[data-copy-kartu]').forEach(btn => {
            btn.addEventListener('click', () => {
                const text = btn.dataset.copyValue || '';
                const done = () => {
                    const original = btn.innerHTML;
                    btn.innerHTML = '<svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                    btn.classList.add('text-emerald-600');
                    btn.classList.remove('text-slate-400');
                    setTimeout(() => { btn.innerHTML = original; btn.classList.remove('text-emerald-600'); btn.classList.add('text-slate-400'); }, 1200);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
                } else {
                    fallbackCopy(text, done);
                }
            });
        });
    </script>

    @if ($canManage)
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const canManage = @json($canManage);
        const isAdminAset = @json($isAdminAset);

        const showUrl = @json(route('aset-barang.aset.show', ['aset' => '__ID__']));
        const storeUrl = @json(route('aset-barang.store.flat'));
        const updateUrl = @json(route('aset-barang.aset.update', ['aset' => '__ID__']));
        const mutasiUrl = @json(route('aset-barang.aset.mutasi', ['aset' => '__ID__']));
        const destroyUrl = @json(route('aset-barang.aset.destroy', ['aset' => '__ID__']));
        const penempatanAwalUrl = @json(route('aset-barang.aset.penempatan-awal', ['aset' => '__ID__']));
        const finalisasiPenempatanUrl = @json(route('aset-barang.aset.finalisasi-penempatan', ['aset' => '__ID__']));
        const finalisasiMassalUrl = @json(route('aset-barang.aset.finalisasi-penempatan-massal'));
        const editPenempatanUrl = @json(route('aset-barang.aset.edit-penempatan', ['aset' => '__ID__']));

        const modal = document.getElementById('aset-modal');
        const deleteModal = document.getElementById('delete-modal');
        const mutasiModal = document.getElementById('mutasi-modal');
        const penempatanAwalModalEl = document.getElementById('penempatan-awal-modal');
        const formPenempatanAwal = document.getElementById('form-penempatan-awal');
        const paPegawai = document.getElementById('pa-pegawai');
        const paRuanganInfo = document.getElementById('pa-ruangan-info');
        const paLokasiInfo = document.getElementById('pa-lokasi-info');
        const paSkpdInfo = document.getElementById('pa-skpd-info');
        const paRuanganHidden = document.getElementById('pa-ruangan');
        const penempatanRuanganModalEl = document.getElementById('penempatan-ruangan-modal');
        const formPenempatanRuangan = document.getElementById('form-penempatan-ruangan');
        const prRuangan = document.getElementById('pr-ruangan');
        const prRuanganSearch = document.getElementById('pr-ruangan-search');
        const prRuanganInfo = document.getElementById('pr-ruangan-info');
        const prLokasiInfo = document.getElementById('pr-lokasi-info');
        const prSkpdInfo = document.getElementById('pr-skpd-info');
        const form = document.getElementById('aset-form');

        function showErrors(errors) {
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors).forEach(field => {
                const err = form.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }

        function openModal() {
            modal.style.visibility = 'visible';
            document.body.classList.add('overflow-hidden');
        }
        function closeModal() {
            modal.style.visibility = 'hidden';
            document.body.classList.remove('overflow-hidden');
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }

        function openDeleteModal(id, name) {
            if (!deleteModal) return;
            document.getElementById('delete-name').textContent = name;
            deleteModal.setAttribute('data-current-id', id);
            deleteModal.style.visibility = 'visible';
        }
        function closeDeleteModal() {
            if (!deleteModal) return;
            deleteModal.style.visibility = 'hidden';
        }

        function syncPenempatanAwalInfo() {
            if (!paPegawai) return;
            const opt = paPegawai.options[paPegawai.selectedIndex];
            const ruanganNama = (opt && opt.dataset.ruanganNama) ? opt.dataset.ruanganNama : '';
            const lokasiNama = (opt && opt.dataset.lokasiNama) ? opt.dataset.lokasiNama : '';
            const skpdNama = (opt && opt.dataset.skpdNama) ? opt.dataset.skpdNama : '';
            const ruanganId = (opt && opt.dataset.ruangan) ? opt.dataset.ruangan : '';
            if (paRuanganInfo) paRuanganInfo.value = ruanganNama || '-';
            if (paLokasiInfo) paLokasiInfo.value = lokasiNama || '-';
            if (paSkpdInfo) paSkpdInfo.value = skpdNama || '-';
            if (paRuanganHidden) paRuanganHidden.value = ruanganId || '';
        }
        if (paPegawai) {
            paPegawai.addEventListener('change', syncPenempatanAwalInfo);
        }

        const paPegawaiSearch = document.getElementById('pa-pegawai-search');
        if (paPegawaiSearch && paPegawai) {
            paPegawaiSearch.addEventListener('input', () => {
                const term = paPegawaiSearch.value.toLowerCase();
                Array.from(paPegawai.options).forEach(opt => {
                    opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }

        document.querySelectorAll('[data-penempatan-awal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-penempatan-awal');
                if (!formPenempatanAwal || !penempatanAwalModalEl) return;
                formPenempatanAwal.setAttribute('action', penempatanAwalUrl.replace('__ID__', id));
                if (paPegawai) { paPegawai.value = ''; }
                if (paPegawaiSearch) { paPegawaiSearch.value = ''; Array.from(paPegawai.options).forEach(o => o.style.display = ''); }
                if (paRuanganHidden) paRuanganHidden.value = '';
                syncPenempatanAwalInfo();
                penempatanAwalModalEl.classList.remove('hidden');
                penempatanAwalModalEl.classList.add('flex');
            });
        });
        document.querySelectorAll('#penempatan-awal-modal [data-close-pa]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!penempatanAwalModalEl) return;
                penempatanAwalModalEl.classList.add('hidden');
                penempatanAwalModalEl.classList.remove('flex');
            });
        });
        if (formPenempatanAwal) {
            formPenempatanAwal.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(formPenempatanAwal);
                try {
                    const res = await fetch(formPenempatanAwal.getAttribute('action'), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: formData,
                    });
                    const data = await res.json();
                    if (!res.ok || data.success === false) {
                        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                        alert(firstError || data.message || 'Gagal melakukan penempatan awal.');
                        return;
                    }
                    window.location.reload();
                } catch (err) {
                    alert('Gagal melakukan penempatan awal.');
                }
            });
        }

        function syncPenempatanRuanganInfo() {
            if (!prRuangan) return;
            const opt = prRuangan.options[prRuangan.selectedIndex];
            const nama = (opt && opt.dataset.ruanganNama) ? opt.dataset.ruanganNama : '';
            const lokasi = (opt && opt.dataset.lokasiNama) ? opt.dataset.lokasiNama : '';
            const skpd = (opt && opt.dataset.skpdNama) ? opt.dataset.skpdNama : '';
            if (prRuanganInfo) prRuanganInfo.value = nama || '-';
            if (prLokasiInfo) prLokasiInfo.value = lokasi || '-';
            if (prSkpdInfo) prSkpdInfo.value = skpd || '-';
        }
        if (prRuangan) {
            prRuangan.addEventListener('change', syncPenempatanRuanganInfo);
        }
        if (prRuanganSearch && prRuangan) {
            prRuanganSearch.addEventListener('input', () => {
                const term = prRuanganSearch.value.toLowerCase();
                Array.from(prRuangan.options).forEach(opt => {
                    opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }
        document.querySelectorAll('[data-penempatan-ruangan]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-penempatan-ruangan');
                if (!formPenempatanRuangan || !penempatanRuanganModalEl) return;
                formPenempatanRuangan.setAttribute('action', penempatanAwalUrl.replace('__ID__', id));
                if (prRuangan) { prRuangan.value = ''; }
                if (prRuanganSearch) { prRuanganSearch.value = ''; Array.from(prRuangan.options).forEach(o => o.style.display = ''); }
                syncPenempatanRuanganInfo();
                penempatanRuanganModalEl.classList.remove('hidden');
                penempatanRuanganModalEl.classList.add('flex');
            });
        });
        document.querySelectorAll('#penempatan-ruangan-modal [data-close-pr]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!penempatanRuanganModalEl) return;
                penempatanRuanganModalEl.classList.add('hidden');
                penempatanRuanganModalEl.classList.remove('flex');
            });
        });
        if (formPenempatanRuangan) {
            formPenempatanRuangan.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(formPenempatanRuangan);
                try {
                    const res = await fetch(formPenempatanRuangan.getAttribute('action'), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: formData,
                    });
                    const data = await res.json();
                    if (!res.ok || data.success === false) {
                        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                        alert(firstError || data.message || 'Gagal melakukan penempatan awal ruangan.');
                        return;
                    }
                    window.location.reload();
                } catch (err) {
                    alert('Gagal melakukan penempatan awal ruangan.');
                }
            });
        }

        // ---- Konfirmasi Finalisasi Penempatan (modal dialog) ----
        const finalisasiModalEl = document.getElementById('finalisasi-modal');
        const btnFinalisasiConfirm = document.getElementById('btn-finalisasi-confirm');
        const btnFinalisasiLabel = document.getElementById('btn-finalisasi-label');
        const btnFinalisasiSpinner = document.getElementById('btn-finalisasi-spinner');
        const spanFinalisasiNama = document.getElementById('finalisasi-aset-nama');
        const spanFinalisasiPesan = document.getElementById('finalisasi-pesan-extra');
        let finalisasiIdAset = null;
        // URL override dipakai tombol "Verifikasi & Finalisasi" (penempatan
        // lama berstatus NULL) agar endpoint yang dipanggil berbeda tanpa
        // modal tambahan.
        let finalisasiUrlOverride = null;

        document.querySelectorAll('[data-finalisasi-penempatan]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!btn || !finalisasiModalEl || !btnFinalisasiConfirm) return;
                finalisasiIdAset = btn.getAttribute('data-finalisasi-penempatan');
                if (!finalisasiIdAset) return;
                finalisasiUrlOverride = btn.getAttribute('data-finalisasi-url') || null;
                spanFinalisasiNama.textContent = btn.getAttribute('data-finalisasi-nama') || 'Aset';
                if (spanFinalisasiPesan) {
                    const pesan = btn.getAttribute('data-finalisasi-pesan');
                    spanFinalisasiPesan.textContent = pesan || '';
                    spanFinalisasiPesan.classList.toggle('hidden', !pesan);
                }
                btnFinalisasiConfirm.disabled = false;
                btnFinalisasiLabel.textContent = finalisasiUrlOverride ? 'Verifikasi & Finalisasi' : 'Finalisasi';
                btnFinalisasiSpinner.classList.add('hidden');
                finalisasiModalEl.style.visibility = 'visible';
                document.body.classList.add('overflow-hidden');
            });
        });

        function closeFinalisasiModal() {
            if (!finalisasiModalEl) return;
            finalisasiModalEl.style.visibility = 'hidden';
            document.body.classList.remove('overflow-hidden');
            finalisasiIdAset = null;
            finalisasiUrlOverride = null;
        }
        document.querySelectorAll('#finalisasi-modal [data-finalisasi-close]').forEach(el => {
            el.addEventListener('click', closeFinalisasiModal);
        });

        if (btnFinalisasiConfirm) {
            btnFinalisasiConfirm.addEventListener('click', async () => {
                if (!finalisasiIdAset) return closeFinalisasiModal();

                btnFinalisasiConfirm.disabled = true;
                btnFinalisasiLabel.textContent = 'Memproses...';
                btnFinalisasiSpinner.classList.remove('hidden');

                try {
                    const url = (finalisasiUrlOverride || finalisasiPenempatanUrl).replace('__ID__', finalisasiIdAset);

                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    });

                    // Server bisa membalas non-JSON (mis. 403/500) — tetap harus
                    // bisa ditampilkan sebagai pesan error yang jelas.
                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = {};
                    }

                    if (!res.ok || data.success === false) {
                        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                        const pesan = firstError
                            || data.message
                            || (res.status === 403 ? 'Anda tidak memiliki hak akses untuk memfinalisasi penempatan aset ini.'
                                : (res.status === 422 ? 'Penempatan tidak dapat difinalisasi. Muat ulang halaman lalu coba lagi.'
                                    : 'Gagal memfinalisasi penempatan. Coba lagi.'));
                        alert(pesan);
                        return;
                    }
                    closeFinalisasiModal();
                    window.location.reload();
                } catch (err) {
                    alert('Gagal memfinalisasi penempatan. Periksa koneksi Anda lalu coba lagi.');
                } finally {
                    btnFinalisasiConfirm.disabled = false;
                    btnFinalisasiLabel.textContent = 'Finalisasi';
                    btnFinalisasiSpinner.classList.add('hidden');
                }
            });
        }

        // ---- Finalisasi Massal ----
        const btnFinalisasiTerpilih = document.getElementById('btn-finalisasi-terpilih');
        const pilihSemuaFinalisasi = document.getElementById('pilih-semua-finalisasi');
        const finalisasiMassalModalEl = document.getElementById('finalisasi-massal-modal');
        const btnFinalisasiMassalConfirm = document.getElementById('btn-finalisasi-massal-confirm');
        const btnFinalisasiMassalLabel = document.getElementById('btn-finalisasi-massal-label');
        const btnFinalisasiMassalSpinner = document.getElementById('btn-finalisasi-massal-spinner');
        const btnFinalisasiMassalBatal = document.getElementById('btn-finalisasi-massal-batal');
        const finalisasiMassalPesan = document.getElementById('finalisasi-massal-pesan');
        const finalisasiMassalHasil = document.getElementById('finalisasi-massal-hasil');

        // Hanya baris yang Layak (penempatan aktif + is_finalized = 0) punya
        // checkbox; backend tetap memvalidasi ulang tiap aset.
        function checkboxFinalisasiTerpilih() {
            return Array.from(document.querySelectorAll('[data-finalisasi-checkbox]'));
        }

        function asetTerpilih() {
            return checkboxFinalisasiTerpilih().filter(cb => cb.checked).map(cb => cb.value);
        }

        function perbaruiTombolTerpilih() {
            if (!btnFinalisasiTerpilih) return;
            const jumlah = asetTerpilih().length;
            btnFinalisasiTerpilih.textContent = 'Finalisasi Terpilih (' + jumlah + ')';
            btnFinalisasiTerpilih.disabled = jumlah === 0;

            if (pilihSemuaFinalisasi) {
                const total = checkboxFinalisasiTerpilih().length;
                pilihSemuaFinalisasi.disabled = total === 0;
                pilihSemuaFinalisasi.checked = total > 0 && jumlah === total;
                pilihSemuaFinalisasi.indeterminate = jumlah > 0 && jumlah < total;
            }
        }

        checkboxFinalisasiTerpilih().forEach(cb => {
            cb.addEventListener('change', perbaruiTombolTerpilih);
        });

        if (pilihSemuaFinalisasi) {
            pilihSemuaFinalisasi.addEventListener('change', () => {
                const dicentang = pilihSemuaFinalisasi.checked;
                checkboxFinalisasiTerpilih().forEach(cb => { cb.checked = dicentang; });
                perbaruiTombolTerpilih();
            });
        }

        let finalisasiMassalSelesai = false;

        function closeFinalisasiMassalModal() {
            if (!finalisasiMassalModalEl) return;
            if (finalisasiMassalSelesai) {
                window.location.reload();
                return;
            }
            finalisasiMassalModalEl.style.visibility = 'hidden';
            document.body.classList.remove('overflow-hidden');
        }

        function bukaModalFinalisasiMassal() {
            const ids = asetTerpilih();
            if (ids.length === 0) return;

            finalisasiMassalSelesai = false;
            if (finalisasiMassalPesan) {
                finalisasiMassalPesan.innerHTML = '<span id="finalisasi-massal-jumlah" class="font-medium text-slate-700">' + ids.length + '</span> akan ditandai <span class="font-medium text-slate-700">Sudah Difinalisasi</span> dan tidak dapat diedit lagi.';
            }
            if (finalisasiMassalHasil) {
                finalisasiMassalHasil.innerHTML = '';
                finalisasiMassalHasil.classList.add('hidden');
            }
            if (btnFinalisasiMassalLabel) btnFinalisasiMassalLabel.textContent = 'Finalisasi';
            if (btnFinalisasiMassalSpinner) btnFinalisasiMassalSpinner.classList.add('hidden');
            if (btnFinalisasiMassalConfirm) btnFinalisasiMassalConfirm.disabled = false;
            if (btnFinalisasiMassalBatal) {
                btnFinalisasiMassalBatal.textContent = 'Batal';
                btnFinalisasiMassalBatal.classList.remove('hidden');
            }

            finalisasiMassalModalEl.style.visibility = 'visible';
            document.body.classList.add('overflow-hidden');
        }

        if (btnFinalisasiTerpilih) {
            btnFinalisasiTerpilih.addEventListener('click', bukaModalFinalisasiMassal);
            perbaruiTombolTerpilih();
        }

        document.querySelectorAll('#finalisasi-massal-modal [data-finalisasi-massal-close]').forEach(el => {
            el.addEventListener('click', closeFinalisasiMassalModal);
        });

        function tampilkanHasilMassal(data) {
            if (!finalisasiMassalHasil) return;

            const bagian = [];
            bagian.push('<div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><span class="font-semibold">' + (data.berhasil || 0) + '</span> aset berhasil difinalisasi.</div>');

            const gagal = Array.isArray(data.detail_gagal) ? data.detail_gagal : [];
            if (gagal.length > 0) {
                bagian.push('<div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-800">');
                bagian.push('<p class="font-semibold">' + gagal.length + ' aset gagal difinalisasi:</p>');
                bagian.push('<ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">');
                gagal.forEach(g => {
                    bagian.push('<li><span class="font-medium">' + (g.nama || ('Aset #' + g.id_aset)) + '</span> — ' + (g.alasan || 'Tidak diketahui') + '</li>');
                });
                bagian.push('</ul></div>');
            }

            finalisasiMassalHasil.innerHTML = bagian.join('');
            finalisasiMassalHasil.classList.remove('hidden');
        }

        if (btnFinalisasiMassalConfirm) {
            btnFinalisasiMassalConfirm.addEventListener('click', async () => {
                // Setelah selesai: tombol berfungsi menutup modal + muat ulang.
                if (finalisasiMassalSelesai) {
                    window.location.reload();
                    return;
                }

                const ids = asetTerpilih();
                if (ids.length === 0) return closeFinalisasiMassalModal();

                btnFinalisasiMassalConfirm.disabled = true;
                btnFinalisasiMassalLabel.textContent = 'Memproses...';
                btnFinalisasiMassalSpinner.classList.remove('hidden');

                try {
                    const res = await fetch(finalisasiMassalUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: (() => {
                            const body = new FormData();
                            ids.forEach(id => body.append('id_aset[]', id));
                            return body;
                        })(),
                    });

                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = {};
                    }

                    if (!res.ok || data.success === false) {
                        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                        const pesan = firstError
                            || data.message
                            || (res.status === 403 ? 'Anda tidak memiliki hak akses untuk memfinalisasi penempatan aset.'
                                : (res.status === 422 ? 'Daftar aset tidak valid. Muat ulang halaman lalu coba lagi.'
                                    : 'Gagal memfinalisasi penempatan. Coba lagi.'));
                        alert(pesan);
                        btnFinalisasiMassalConfirm.disabled = false;
                        btnFinalisasiMassalLabel.textContent = 'Finalisasi';
                        btnFinalisasiMassalSpinner.classList.add('hidden');
                        return;
                    }

                    // Tampilkan laporan hasil (sukses + aset yang gagal).
                    tampilkanHasilMassal(data);
                    finalisasiMassalSelesai = true;

                    // Tombol berubah jadi satu tombol utama hijau "Tutup & Muat Ulang Tabel":
                    // tombol Batal disembunyikan agar hanya tersisa satu tombol penutup.
                    btnFinalisasiMassalConfirm.disabled = false;
                    btnFinalisasiMassalLabel.textContent = 'Tutup & Muat Ulang Tabel';
                    btnFinalisasiMassalSpinner.classList.add('hidden');
                    if (btnFinalisasiMassalBatal) {
                        btnFinalisasiMassalBatal.classList.add('hidden');
                    }

                    // Hapus pilihan aset yang BERHASIL difinalisasi; aset yang
                    // gagal tetap terpilih agar user bisa mencoba lagi.
                    const berhasil = new Set((data.detail_berhasil || []).map(b => String(b.id_aset)));
                    checkboxFinalisasiTerpilih().forEach(cb => {
                        if (berhasil.has(String(cb.value))) cb.checked = false;
                    });
                    perbaruiTombolTerpilih();
                } catch (err) {
                    alert('Gagal memfinalisasi penempatan. Periksa koneksi Anda lalu coba lagi.');
                    btnFinalisasiMassalConfirm.disabled = false;
                    btnFinalisasiMassalLabel.textContent = 'Finalisasi';
                    btnFinalisasiMassalSpinner.classList.add('hidden');
                }
            });
        }

        const editPenempatanModalEl = document.getElementById('edit-penempatan-modal');
        const formEditPenempatan = document.getElementById('form-edit-penempatan');
        const epMode = document.getElementById('ep-mode');
        const epTitle = document.getElementById('ep-title');
        const epSubtitle = document.getElementById('ep-subtitle');
        const epFieldPegawai = document.getElementById('ep-field-pegawai');
        const epFieldRuangan = document.getElementById('ep-field-ruangan');
        const epPegawai = document.getElementById('ep-pegawai');
        const epPegawaiSearch = document.getElementById('ep-pegawai-search');
        const epRuangan = document.getElementById('ep-ruangan');
        const epRuanganSearch = document.getElementById('ep-ruangan-search');
        const epRuanganInfo = document.getElementById('ep-ruangan-info');
        const epLokasiInfo = document.getElementById('ep-lokasi-info');
        const epSkpdInfo = document.getElementById('ep-skpd-info');

        function syncEditPenempatanInfo() {
            const select = epMode && epMode.value === 'ruangan' ? epRuangan : epPegawai;
            if (!select) return;
            const opt = select.options[select.selectedIndex];
            const ruanganNama = (opt && opt.dataset.ruanganNama) ? opt.dataset.ruanganNama : '';
            const lokasiNama = (opt && opt.dataset.lokasiNama) ? opt.dataset.lokasiNama : '';
            const skpdNama = (opt && opt.dataset.skpdNama) ? opt.dataset.skpdNama : '';
            if (epRuanganInfo) epRuanganInfo.value = ruanganNama || '-';
            if (epLokasiInfo) epLokasiInfo.value = lokasiNama || '-';
            if (epSkpdInfo) epSkpdInfo.value = skpdNama || '-';
        }
        if (epPegawai) epPegawai.addEventListener('change', syncEditPenempatanInfo);
        if (epRuangan) epRuangan.addEventListener('change', syncEditPenempatanInfo);

        function bindSearch(input, select) {
            if (!input || !select) return;
            input.addEventListener('input', () => {
                const term = input.value.toLowerCase();
                Array.from(select.options).forEach(opt => {
                    opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }
        bindSearch(epPegawaiSearch, epPegawai);
        bindSearch(epRuanganSearch, epRuangan);

        document.querySelectorAll('[data-edit-penempatan]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!formEditPenempatan || !editPenempatanModalEl) return;
                const id = btn.getAttribute('data-edit-penempatan');
                const mode = btn.getAttribute('data-mode') === 'ruangan' ? 'ruangan' : 'pegawai';

                formEditPenempatan.setAttribute('action', editPenempatanUrl.replace('__ID__', id));
                if (epMode) epMode.value = mode;

                if (mode === 'ruangan') {
                    if (epTitle) epTitle.textContent = 'Edit Ruangan';
                    if (epSubtitle) epSubtitle.textContent = 'Perbaiki ruangan penempatan aset ini (tanpa pemegang).';
                    if (epFieldPegawai) epFieldPegawai.classList.add('hidden');
                    if (epFieldRuangan) epFieldRuangan.classList.remove('hidden');
                    if (epPegawai) epPegawai.disabled = true;
                    if (epRuangan) epRuangan.disabled = false;
                    if (epRuangan) epRuangan.value = '';
                    if (epRuanganSearch) { epRuanganSearch.value = ''; Array.from(epRuangan.options).forEach(o => o.style.display = ''); }
                } else {
                    if (epTitle) epTitle.textContent = 'Edit Pemegang';
                    if (epSubtitle) epSubtitle.textContent = 'Perbaiki pemegang penempatan aset ini.';
                    if (epFieldPegawai) epFieldPegawai.classList.remove('hidden');
                    if (epFieldRuangan) epFieldRuangan.classList.add('hidden');
                    if (epPegawai) epPegawai.disabled = false;
                    if (epRuangan) epRuangan.disabled = true;
                    if (epPegawai) epPegawai.value = '';
                    if (epPegawaiSearch) { epPegawaiSearch.value = ''; Array.from(epPegawai.options).forEach(o => o.style.display = ''); }
                }

                syncEditPenempatanInfo();
                editPenempatanModalEl.classList.remove('hidden');
                editPenempatanModalEl.classList.add('flex');
            });
        });

        function closeEditPenempatanModal() {
            if (!editPenempatanModalEl) return;
            editPenempatanModalEl.classList.add('hidden');
            editPenempatanModalEl.classList.remove('flex');
        }
        document.querySelectorAll('#edit-penempatan-modal [data-close-ep]').forEach(btn => {
            btn.addEventListener('click', closeEditPenempatanModal);
        });

        if (formEditPenempatan) {
            formEditPenempatan.addEventListener('submit', async (e) => {
                e.preventDefault();
                const mode = epMode ? epMode.value : 'pegawai';
                const body = new FormData(formEditPenempatan);
                // Form mengirim kedua field; hanya field sesuai mode yang dipakai server.
                if (mode === 'pegawai') body.delete('id_ruangan');
                if (mode === 'ruangan') body.delete('id_pegawai');

                try {
                    const res = await fetch(formEditPenempatan.getAttribute('action'), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body,
                    });
                    const data = await res.json();
                    if (!res.ok || data.success === false) {
                        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                        alert(firstError || data.message || 'Gagal mengedit penempatan.');
                        return;
                    }
                    window.location.reload();
                    if (data.warning) {
                        setTimeout(() => alert(data.warning), 100);
                    }
                } catch (err) {
                    alert('Gagal mengedit penempatan.');
                }
            });
        }

        // Tombol Tambah
        const btnTambah = document.getElementById('btn-tambah');
        if (btnTambah) {
            btnTambah.addEventListener('click', () => {
                form.reset();
                document.getElementById('field-id').value = '';
                form.action = storeUrl;
                document.getElementById('modal-title').textContent = 'Tambah Aset';
                document.getElementById('modal-subtitle').textContent = 'Lengkapi data master aset.';
                document.getElementById('btn-submit').textContent = 'Simpan';
                document.getElementById('modal-note').textContent = 'SKPD & Lokasi Fisik dihitung otomatis oleh server dari Pemegang/Ruangan. Bila Pemegang dipilih, Ruangan mengikuti ruang kerja pemegang.';
                form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                openModal();
            });
        }

        // Edit Aset Master: ikon pensil di kolom AKSI. Form yang dipakai SAMA
        // dengan form Tambah Aset (field, urutan, dan penanda wajib identik),
        // hanya nilainya diisi otomatis dari data aset yang dipilih.
        document.querySelectorAll('[data-edit-aset]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-edit-aset');
                if (!id) return;
                btn.disabled = true;
                try {
                    const res = await fetch(showUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('Gagal mengambil data aset');
                    const data = await res.json();

                    document.getElementById('field-id').value = data.id_aset ?? '';
                    document.getElementById('field-barang').value = data.nama_barang ?? '';
                    document.getElementById('field-kartu').value = data.nomor_kartu_barang ?? '';
                    document.getElementById('field-merk').value = data.merk ?? '';
                    document.getElementById('field-pengadaan').value = data.tanggal_pengadaan ?? '';
                    document.getElementById('field-tanggal-habis-pakai').value = data.tanggal_habis_pakai ?? '';
                    document.getElementById('field-nilai-perolehan').value = data.nilai_perolehan ?? '';
                    document.getElementById('field-kondisi').value = data.kondisi ?? '';
                    document.getElementById('field-status').value = data.status_aset ?? 'aktif';

                    form.action = updateUrl.replace('__ID__', id);
                    document.getElementById('modal-title').textContent = 'Edit Aset';
                    document.getElementById('modal-subtitle').textContent = 'Perbarui data master aset ini.';
                    document.getElementById('btn-submit').textContent = 'Perbarui';
                    document.getElementById('modal-note').textContent = 'Pemegang, Ruangan, SKPD, dan Lokasi Fisik tidak diubah dari form ini. Perubahan penempatan melalui Edit Penempatan atau Mutasi resmi; SKPD & Lokasi mengikuti master penempatan.';
                    form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                    openModal();
                } catch (e) {
                    alert(e.message);
                } finally {
                    btn.disabled = false;
                }
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submit = document.getElementById('btn-submit');
            const original = submit.textContent;
            submit.textContent = 'Menyimpan...';
            submit.disabled = true;
            const body = new FormData(form);
            try {
                const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                if (res.status === 422) {
                    const data = await res.json();
                    showErrors(data.errors);
                    submit.textContent = original;
                    submit.disabled = false;
                    return;
                }
                if (res.ok) {
                    const data = await res.json();
                    window.location.reload();
                    if (data.warning) {
                        setTimeout(() => alert(data.warning), 100);
                    }
                } else {
                    alert('Terjadi kesalahan. Coba lagi.');
                    submit.textContent = original;
                    submit.disabled = false;
                }
            } catch (err) {
                alert('Koneksi bermasalah. Coba lagi.');
                submit.textContent = original;
                submit.disabled = false;
            }
        });

        modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));

        // Form Tambah/Edit Aset Master hanya mengurus data master: tidak ada
        // field Pemegang/Ruangan/SKPD/Lokasi yang perlu disinkronkan di sini.

        document.querySelectorAll('[data-delete-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                openDeleteModal(btn.dataset.deleteTarget, btn.dataset.deleteName);
            });
        });
        if (deleteModal) {
            deleteModal.querySelectorAll('[data-delete-close]').forEach(el => el.addEventListener('click', closeDeleteModal));
            const btnConfirm = document.getElementById('btn-delete-confirm');
            btnConfirm.addEventListener('click', async () => {
                const id = deleteModal.getAttribute('data-current-id');
                btnConfirm.textContent = 'Memproses...';
                btnConfirm.disabled = true;
                try {
                    const res = await fetch(destroyUrl.replace('__ID__', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        alert('Gagal mengajukan usulan penghapusan.');
                        btnConfirm.textContent = 'Usulkan Hapus';
                        btnConfirm.disabled = false;
                    }
                } catch (err) {
                    alert('Koneksi bermasalah.');
                    btnConfirm.textContent = 'Usulkan Hapus';
                    btnConfirm.disabled = false;
                }
            });
        }

        // ---- Modal Import Excel (Partial Import AJAX) ----
        const importModal = document.getElementById('import-modal');
        const btnImport = document.getElementById('btn-import');
        const importForm = document.getElementById('import-form');
        const importResultSection = document.getElementById('import-result-section');
        const btnImportSubmit = document.getElementById('btn-import-submit');
        const btnImportText = document.getElementById('btn-import-text');
        const importFormError = document.getElementById('import-form-error');
        const btnImportRetry = document.getElementById('btn-import-retry');
        const btnImportDone = document.getElementById('btn-import-done');

        let importSucceededCount = 0;

        function resetImportModal() {
            if (importForm) importForm.reset();
            if (importFormError) { importFormError.classList.add('hidden'); importFormError.textContent = ''; }
            if (importForm) importForm.classList.remove('hidden');
            if (importResultSection) importResultSection.classList.add('hidden');
            if (btnImportSubmit) { btnImportSubmit.disabled = false; }
            if (btnImportText) { btnImportText.textContent = 'Import'; }
            importSucceededCount = 0;
        }

        if (btnImport && importModal) {
            btnImport.addEventListener('click', () => {
                resetImportModal();
                importModal.classList.remove('hidden');
                importModal.classList.add('flex');
            });
        }

        function closeImportModal(forceReload = false) {
            if (!importModal) return;
            importModal.classList.add('hidden');
            importModal.classList.remove('flex');
            if (forceReload || importSucceededCount > 0) {
                window.location.reload();
            }
        }

        if (importModal) {
            importModal.querySelectorAll('[data-import-close]').forEach(el => el.addEventListener('click', () => {
                closeImportModal(importSucceededCount > 0);
            }));
        }

        if (btnImportRetry) {
            btnImportRetry.addEventListener('click', () => {
                resetImportModal();
            });
        }

        if (btnImportDone) {
            btnImportDone.addEventListener('click', () => {
                closeImportModal(importSucceededCount > 0);
            });
        }

        if (importForm) {
            importForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fileInput = document.getElementById('import-file-input');
                if (!fileInput || !fileInput.files.length) {
                    if (importFormError) {
                        importFormError.textContent = 'Pilih file Excel/CSV terlebih dahulu.';
                        importFormError.classList.remove('hidden');
                    }
                    return;
                }

                if (importFormError) {
                    importFormError.classList.add('hidden');
                    importFormError.textContent = '';
                }

                if (btnImportSubmit) {
                    btnImportSubmit.disabled = true;
                }
                if (btnImportText) {
                    btnImportText.textContent = 'Mengimpor...';
                }

                const formData = new FormData(importForm);

                try {
                    const res = await fetch(importForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: formData
                    });

                    const data = await res.json();

                    if (!res.ok && res.status !== 422) {
                        throw new Error(data.message || 'Terjadi kesalahan pada server.');
                    }

                    // Tampilkan hasil pemrosesan import
                    importSucceededCount = data.berhasil || 0;
                    const failedCount = data.gagal || 0;

                    importForm.classList.add('hidden');
                    if (importResultSection) {
                        importResultSection.classList.remove('hidden');
                    }

                    const resultBanner = document.getElementById('import-result-banner');
                    const resultTitle = document.getElementById('import-result-title');
                    const countSuccess = document.getElementById('import-count-success');
                    const countFailed = document.getElementById('import-count-failed');
                    const errorWrapper = document.getElementById('import-error-list-wrapper');
                    const errorList = document.getElementById('import-error-list');
                    const resultIcon = document.getElementById('import-result-icon');

                    if (resultTitle) {
                        resultTitle.textContent = data.title || (data.status === 'success' ? 'Import Berhasil' : (data.status === 'warning' ? 'Import Selesai dengan Peringatan' : 'Import Gagal'));
                    }
                    if (countSuccess) {
                        countSuccess.textContent = `Berhasil: ${importSucceededCount} barang`;
                    }
                    if (countFailed) {
                        countFailed.textContent = `Gagal: ${failedCount} barang`;
                        countFailed.classList.toggle('hidden', failedCount === 0);
                    }

                    // Styling banner berdasarkan status
                    if (resultBanner) {
                        resultBanner.className = 'rounded-2xl border p-4 ' + (
                            data.status === 'success'
                                ? 'border-emerald-200 bg-emerald-50/70 text-emerald-900'
                                : (data.status === 'warning'
                                    ? 'border-amber-200 bg-amber-50/70 text-amber-900'
                                    : 'border-rose-200 bg-rose-50/70 text-rose-900')
                        );
                    }

                    // Ikon hasil
                    if (resultIcon) {
                        if (data.status === 'success') {
                            resultIcon.innerHTML = '<svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                        } else if (data.status === 'warning') {
                            resultIcon.innerHTML = '<svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
                        } else {
                            resultIcon.innerHTML = '<svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                        }
                    }

                    // Render rincian daftar kegagalan
                    if (errorList && errorWrapper) {
                        errorList.innerHTML = '';
                        const errorItems = data.detail_kegagalan || [];
                        if (errorItems.length > 0) {
                            errorWrapper.classList.remove('hidden');
                            errorItems.forEach(msg => {
                                const li = document.createElement('li');
                                li.textContent = msg;
                                errorList.appendChild(li);
                            });
                        } else {
                            errorWrapper.classList.add('hidden');
                        }
                    }

                    // Tombol tindakan
                    if (btnImportRetry && btnImportDone) {
                        if (importSucceededCount === 0) {
                            btnImportRetry.classList.remove('hidden');
                            btnImportDone.textContent = 'Tutup';
                        } else {
                            btnImportRetry.classList.add('hidden');
                            btnImportDone.textContent = 'Selesai & Muat Ulang';
                        }
                    }

                } catch (err) {
                    if (importFormError) {
                        importFormError.textContent = err.message || 'Koneksi bermasalah saat mengimpor file.';
                        importFormError.classList.remove('hidden');
                    }
                    if (btnImportSubmit) {
                        btnImportSubmit.disabled = false;
                    }
                    if (btnImportText) {
                        btnImportText.textContent = 'Import';
                    }
                }
            });
        }

        // ---- Modal Mutasi ----
        const mutasiForm = document.getElementById('mutasi-form');
        const mutasiTipeInput = document.getElementById('mutasi-tipe');
        const mutasiFieldPegawai = document.getElementById('mutasi-field-pegawai');
        const mutasiFieldRuangan = document.getElementById('mutasi-field-ruangan');
        const mutasiFieldSkpd = document.getElementById('mutasi-field-skpd');
        const mutasiRuanganSel = document.getElementById('mutasi-ruangan');
        const mutasiSkpdSel = document.getElementById('mutasi-skpd');
        const btnTipePegawai = document.getElementById('tipe-pegawai');
        const btnTipeRuangan = document.getElementById('tipe-ruangan');

        function setMutasiError(field, msg) {
            const err = mutasiForm.querySelector('[data-error-for="' + field + '"]');
            if (err) { err.textContent = msg; err.classList.remove('hidden'); }
        }

        function setMutasiMode(tipe) {
            mutasiTipeInput.value = tipe;
            const isPegawai = tipe === 'pegawai';
            btnTipePegawai.classList.toggle('bg-indigo-600', isPegawai);
            btnTipePegawai.classList.toggle('text-white', isPegawai);
            btnTipePegawai.classList.toggle('border-indigo-600', isPegawai);
            btnTipeRuangan.classList.toggle('bg-indigo-600', !isPegawai);
            btnTipeRuangan.classList.toggle('text-white', !isPegawai);
            btnTipeRuangan.classList.toggle('border-indigo-600', !isPegawai);
            mutasiFieldPegawai.classList.toggle('hidden', !isPegawai);
            mutasiFieldRuangan.classList.toggle('hidden', isPegawai);
            // SKPD hanya ditampilkan sebagai informasi saat Pindah Ruangan;
            // nilainya read-only dan dihitung server dari ruangan tujuan.
            mutasiFieldSkpd.classList.toggle('hidden', isPegawai);
        }
        if (btnTipePegawai) btnTipePegawai.addEventListener('click', () => setMutasiMode('pegawai'));
        if (btnTipeRuangan) btnTipeRuangan.addEventListener('click', () => setMutasiMode('ruangan'));

        // Pratinjau SKPD saat memilih ruangan tujuan (display saja).
        function syncMutasiSkpd() {
            if (!mutasiSkpdSel) return;
            const opt = mutasiRuanganSel.selectedOptions[0];
            mutasiSkpdSel.value = opt ? (opt.dataset.skpdNama ?? '') : '';
            mutasiSkpdSel.placeholder = opt && opt.dataset.skpdNama
                ? ''
                : '-- Mengikuti ruangan tujuan --';
        }
        if (mutasiRuanganSel) mutasiRuanganSel.addEventListener('change', syncMutasiSkpd);

        function openMutasiModal(id, name, hasPemegang) {
            if (!mutasiModal) return;
            mutasiForm.reset();
            mutasiForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            document.getElementById('mutasi-aset-id').value = id;
            document.getElementById('mutasi-aset-name').textContent = name;
            setMutasiMode(hasPemegang ? 'pegawai' : 'ruangan');
            syncMutasiSkpd();
            mutasiModal.style.visibility = 'visible';
            document.body.classList.add('overflow-hidden');
        }
        function closeMutasiModal() {
            if (!mutasiModal) return;
            mutasiModal.style.visibility = 'hidden';
            document.body.classList.remove('overflow-hidden');
        }
        if (mutasiModal) {
            mutasiModal.querySelectorAll('[data-mutasi-close]').forEach(el => el.addEventListener('click', closeMutasiModal));
        }

        document.querySelectorAll('[data-mutasi-modal]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.mutasiModal;
                try {
                    const res = await fetch(showUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('Gagal mengambil data');
                    const data = await res.json();
                    const label = (data.nama_barang || data.nomor_kartu_barang || 'Aset') + (data.nomor_kartu_barang ? ' (' + data.nomor_kartu_barang + ')' : '');
                    openMutasiModal(id, label, !!data.id_pegawai);
                } catch (e) {
                    alert(e.message);
                }
            });
        });

        if (mutasiForm) {
            mutasiForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-mutasi-submit');
                const original = submit.textContent;
                submit.textContent = 'Menyimpan...';
                submit.disabled = true;
                mutasiForm.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
                const body = new FormData(mutasiForm);
                body.delete('id_aset');
                try {
                    const res = await fetch(mutasiUrl.replace('__ID__', document.getElementById('mutasi-aset-id').value), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                    if (res.status === 422) {
                        const data = await res.json();
                        (data.errors.tipe || []).forEach(m => setMutasiError('tipe', m));
                        (data.errors.id_pegawai || []).forEach(m => setMutasiError('id_pegawai', m));
                        (data.errors.id_ruangan || []).forEach(m => setMutasiError('id_ruangan', m));
                        submit.textContent = original;
                        submit.disabled = false;
                        return;
                    }
                    if (res.ok) {
                        const data = await res.json();
                        closeMutasiModal();
                        if (data.download_bast_url) {
                            const suksesModal = document.getElementById('mutasi-sukses-modal');
                            if (suksesModal) {
                                document.getElementById('btn-download-bast').href = data.download_bast_url;
                                if (data.message) {
                                    document.getElementById('mutasi-sukses-message').textContent = data.message;
                                }
                                const warnEl = document.getElementById('mutasi-sukses-warning');
                                if (warnEl) {
                                    if (data.warning) {
                                        warnEl.textContent = data.warning;
                                        warnEl.classList.remove('hidden');
                                    } else {
                                        warnEl.classList.add('hidden');
                                    }
                                }
                                suksesModal.style.visibility = 'visible';
                                return;
                            }
                        }
                        window.location.reload();
                        if (data.warning) {
                            setTimeout(() => alert(data.warning), 100);
                        }
                    } else {
                        alert('Terjadi kesalahan. Coba lagi.');
                        submit.textContent = original;
                        submit.disabled = false;
                    }
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    submit.textContent = original;
                    submit.disabled = false;
                }
            });
        }
    </script>
    @endif
    @endpush

    @push('modals')
    @if ($canManage)
        @if ($isAdminAset)
            @include('aset-barang.partials.modal-import')
        @endif
        @include('aset-barang.partials.modal-tambah-edit')
        @include('aset-barang.partials.modal-mutasi')
        @include('aset-barang.partials.modal-penempatan')
        @include('aset-barang.partials.modal-finalisasi')

        {{-- Modal Konfirmasi Hapus --}}
        <div id="delete-modal" class="overflow-y-auto"
             style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; background-color: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden;">
            <div class="fixed inset-0" data-delete-close></div>
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-slate-900">Usulkan Penghapusan Aset</h3>
                        <p class="mt-1 text-sm text-slate-500"><span id="delete-name" class="font-medium text-slate-700"></span> akan masuk antrean usulan penghapusan dan hilang dari daftar aset aktif. Pembatalan dapat dilakukan di halaman Penghapusan.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" data-delete-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="button" id="btn-delete-confirm" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">Usulkan Hapus</button>
                </div>
            </div>
        </div>
    @endif
    @endpush
@endsection
