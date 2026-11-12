@extends('layouts.app')

@section('title', 'Kendaraan')

@section('page-title', 'Kendaraan Dinas')

@section('content')
    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('kendaraan.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative flex-1 sm:max-w-sm">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Cari nama, kartu, merk, tipe, rangka, mesin, nomor plat..."
                        autocomplete="off"
                        class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition"
                    >
                </div>
                <select name="status" class="block rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status') === 'nonaktif')>Non-Aktif</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-disbun-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-400">
                    Cari
                </button>
                @if (request('search') || request('status'))
                    <a href="{{ route('kendaraan.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300">
                        Reset
                    </a>
                @endif
            </form>

            @if ($isAdminAset || $isAdminUpt)
                <x-slot name="actions">
                    <button type="button" id="btn-finalisasi-kendaraan-massal"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-disbun-card-border bg-white px-3.5 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                            disabled>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Finalisasi Terpilih (<span id="finalisasi-kendaraan-count">0</span>)</span>
                    </button>
                    @if ($isAdminAset)
                        <div class="relative" id="export-menu-wrapper">
                            <button type="button" id="btn-export-menu"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-disbun-card-border bg-white px-3.5 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v2a2 2 0 002 2h14a2 2 0 002-2v-2M12 4v10"/></svg>
                                <span>Ekspor</span>
                            </button>
                            <div id="export-menu" class="absolute right-0 z-30 mt-2 hidden w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-xl">
                                <a href="{{ route('kendaraan.export', array_merge(request()->query(), ['mode' => 'semua'])) }}"
                                   class="block px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <span class="font-semibold">Semua Kendaraan</span>
                                    <span class="block text-xs text-slate-400">Seluruh kendaraan sesuai filter aktif</span>
                                </a>
                                <a href="{{ route('kendaraan.export', array_merge(request()->query(), ['mode' => 'segera'])) }}"
                                   class="block px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <span class="font-semibold">Segera Habis Pajak</span>
                                    <span class="block text-xs text-slate-400">Terlambat atau jatuh tempo &le; 30 hari</span>
                                </a>
                                <button type="button" id="btn-export-selected"
                                        class="block w-full px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50">
                                    <span class="font-semibold">Kendaraan Terpilih (<span id="export-selected-count">0</span>)</span>
                                    <span class="block text-xs text-slate-400">Hanya kendaraan yang dicentang</span>
                                </button>
                            </div>
                        </div>

                        {{-- Mode terpilih: POST ids[] dari pilihan lintas halaman. --}}
                        <form method="POST" action="{{ route('kendaraan.export') }}" id="export-selected-form" class="hidden">
                            @csrf
                            <input type="hidden" name="mode" value="pilih">
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <input type="hidden" name="status" value="{{ request('status') }}">
                            <div id="export-selected-inputs"></div>
                        </form>

                        <x-button type="button" id="btn-import" variant="secondary" icon="M12 16V4m0 0L8 8m4-4l4 4m-9 8H5a2 2 0 00-2 2v2a2 2 0 002 2h14a2 2 0 002-2v-2a2 2 0 00-2-2h-2">
                            Import Excel
                        </x-button>
                        <x-button type="button" id="btn-tambah" icon="M12 4v16m8-8H4">
                            Tambah Kendaraan
                        </x-button>
                    @endif
                </x-slot>
            @endif
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @if ($isAdminAset || $isAdminUpt)
                            <th scope="col" class="w-10 px-4 py-3 text-center">
                                <input type="checkbox" id="kendaraan-check-all"
                                       class="h-4 w-4 rounded border-slate-300 text-disbun-700 focus:ring-disbun-500"
                                       title="Pilih semua di halaman ini">
                            </th>
                        @endif
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Foto</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Kendaraan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plat Aktif</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pajak Tahunan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jatuh Tempo Plat (5 Tahunan)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang</th>
                        @if ($isAdminAset)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SPPKD</th>
                            <th scope="col" class="px-6 py-3 text-right whitespace-nowrap text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($kendaraanList as $index => $k)
                        @php
                            $platAktif = $k->platAktif;
                            $pajakAktif = $k->pajakAktif;
                            $now = \Carbon\Carbon::today();
                            $pajakJatuh = $pajakAktif?->tanggal_berakhir;
                            $platJatuh = $platAktif?->tanggal_berlaku;
                            $diffPajak = $pajakJatuh ? $now->diffInDays($pajakJatuh, false) : null;
                            $diffPlat = $platJatuh ? $now->diffInDays($platJatuh, false) : null;
                            $pajakClass = match (true) {
                                $diffPajak === null => 'bg-slate-100 text-slate-600',
                                $diffPajak < 0 => 'bg-red-100 text-red-700',
                                $diffPajak <= 30 => 'bg-amber-100 text-amber-700',
                                default => 'bg-emerald-100 text-emerald-700',
                            };
                            $platClass = match (true) {
                                $diffPlat === null => 'bg-slate-100 text-slate-600',
                                $diffPlat < 0 => 'bg-red-100 text-red-700',
                                $diffPlat <= 30 => 'bg-amber-100 text-amber-700',
                                default => 'bg-emerald-100 text-emerald-700',
                            };
                            $pajakLabel = match (true) {
                                $diffPajak === null => '-',
                                $diffPajak < 0 => $pajakJatuh->format('d M Y') . ' (Terlambat)',
                                $pajakJatuh->isSameDay($now) => $pajakJatuh->format('d M Y') . ' (Jatuh Hari Ini)',
                                $diffPajak <= 30 => $pajakJatuh->format('d M Y') . ' (' . (int) round($diffPajak) . ' hari lagi)',
                                default => $pajakJatuh->format('d M Y'),
                            };
                            $platLabel = match (true) {
                                $diffPlat === null => '-',
                                $diffPlat < 0 => $platJatuh->format('d M Y') . ' (Terlambat)',
                                $platJatuh->isSameDay($now) => $platJatuh->format('d M Y') . ' (Jatuh Hari Ini)',
                                $diffPlat <= 30 => $platJatuh->format('d M Y') . ' (' . (int) round($diffPlat) . ' hari lagi)',
                                default => $platJatuh->format('d M Y'),
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            @if ($isAdminAset || $isAdminUpt)
                                @php
                                    $penempatanAktifRow = $k->aset?->penempatanAktif;
                                    $isFinalizedRow = $penempatanAktifRow?->is_finalized;
                                    // Checkbox SELALU tersedia (untuk Ekspor Terpilih & Finalisasi).
                                    // Finalisasi Massal hanya menghitung yang memenuhi
                                    // syarat (penempatan aktif belum difinalisasi = 0).
                                    $bolehFinalMassal = $penempatanAktifRow && !is_null($isFinalizedRow) && (int)$isFinalizedRow === 0;
                                @endphp
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox"
                                           class="kendaraan-row-check kendaraan-finalisasi-check h-4 w-4 rounded border-slate-300 text-disbun-700 focus:ring-disbun-500"
                                           value="{{ $k->id_kendaraan }}"
                                           data-finalisasi-eligible="{{ $bolehFinalMassal ? '1' : '0' }}"
                                           @if (! $bolehFinalMassal) title="Kendaraan ini tidak memenuhi syarat Finalisasi Massal" @endif
                                           aria-label="Pilih kendaraan">
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $kendaraanList->firstItem() + $index }}</td>
                            <td class="whitespace-nowrap px-6 py-4">
                                @if ($k->foto)
                                    <img src="{{ asset('storage/' . $k->foto) }}" alt="Foto kendaraan" class="h-10 w-14 rounded-lg object-cover">
                                @else
                                    <span class="inline-flex h-10 w-14 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <a href="{{ route('kendaraan.show', $k->id_kendaraan) }}" class="font-medium text-disbun-700 hover:underline">
                                    {{ $k->namaTampil() ?? 'Kendaraan' }}
                                </a>
                                <p class="text-xs text-slate-400">{{ $k->aset?->nomor_kartu_barang ?? '-' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $k->jenis_kendaraan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-700">{{ $platAktif->nomor_plat ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($pajakJatuh)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pajakClass }}">
                                        {{ $pajakLabel }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($platJatuh)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $platClass }}">
                                        {{ $platLabel }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @php
                                    $penPem = $k->aset?->penempatanAktif;
                                    $finPem = $penPem?->is_finalized;
                                    // Sumber utama: PemegangAset aktif. Teks bebas
                                    // kendaraan.pemegang hanya fallback data lama.
                                    $pemegangAktifRow = $k->aset?->pemegangSaatIni;
                                    $namaPemegangRow = $pemegangAktifRow?->pegawai?->nama_pegawai ?: $k->pemegang;
                                    $idPemegang = $pemegangAktifRow?->pegawai?->id_pegawai
                                        ?? ($pemegangMap[mb_strtolower(trim((string) $k->pemegang))] ?? null);
                                @endphp
                                @if (trim((string) $namaPemegangRow) !== '')
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center">
                                            @if ($idPemegang && in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT'], true))
                                                <a href="{{ route('master-data.pegawai.show', $idPemegang) }}" class="font-medium text-disbun-700 transition hover:text-disbun-800 hover:underline">
                                                    {{ $namaPemegangRow }}
                                                </a>
                                            @else
                                                <span class="font-medium text-slate-800">{{ $namaPemegangRow }}</span>
                                            @endif
                                        </div>
                                        @if ($penPem)
                                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                                @if ($finPem === 0)
                                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">Belum Difinalisasi</span>
                                                    @if ($isAdminAset || $isAdminUpt)
                                                        <button type="button" data-edit-pemegang-kendaraan="{{ $k->id_kendaraan }}"
                                                                data-nama-kendaraan="{{ $k->namaTampil() ?? 'Kendaraan' }}"
                                                                data-plat-nomor="{{ $platAktif->nomor_plat ?? '' }}"
                                                                title="Edit Pemegang" aria-label="Edit pemegang kendaraan"
                                                                class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        </button>
                                                        <button type="button" data-kendaraan-finalisasi="{{ $k->id_kendaraan }}"
                                                                data-kendaraan-nama="{{ $k->namaTampil() ?? 'Kendaraan' }}"
                                                                data-kendaraan-plat="{{ $platAktif->nomor_plat ?? '-' }}"
                                                                data-kendaraan-id="{{ $k->id_kendaraan }}"
                                                                title="Finalisasi" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                        </button>
                                                    @endif
                                                @elseif (is_null($finPem))
                                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-700">Perlu Verifikasi</span>
                                                    @if ($isAdminAset || $isAdminUpt)
                                                        <button type="button" data-kendaraan-verifikasi="{{ $k->id_kendaraan }}"
                                                                data-kendaraan-nama="{{ $k->namaTampil() ?? 'Kendaraan' }}"
                                                                data-kendaraan-plat="{{ $platAktif->nomor_plat ?? '-' }}"
                                                                data-kendaraan-id="{{ $k->id_kendaraan }}"
                                                                title="Verifikasi & Finalisasi" class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-100">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        </button>
                                                    @endif
                                                @elseif ((int)$finPem === 1)
                                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">Sudah Difinalisasi</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    @if ($isAdminAset && $k->aset?->status_aset === 'aktif')
                                        <button type="button" data-penempatan-awal-kendaraan="{{ $k->id_kendaraan }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-600 shadow-sm hover:bg-slate-50" title="Penempatan awal">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                @endif
                            </td>
                            @if ($isAdminAset)
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @if ((int) $finPem === 1 && trim((string) $namaPemegangRow) !== '')
                                        <a href="{{ route('kendaraan.sppkd-aktif.download', $k->id_kendaraan) }}"
                                           class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                            SPPKD
                                        </a>
                                    @else
                                        {{-- SPPKD hanya tampil bila penempatan aktif sudah difinalisasi dan ada pemegang. --}}
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-right text-sm">
                                    <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                        <a href="{{ route('kendaraan.show', $k->id_kendaraan) }}" title="Detail Kendaraan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-white text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <button type="button" data-edit-target="{{ $k->id_kendaraan }}" title="Edit Kendaraan" aria-label="Edit kendaraan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-white text-disbun-700 transition hover:bg-disbun-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        @if ((int) $finPem === 1)
                                        <button type="button" onclick="openMutasiKendaraan({{ $k->id_kendaraan }})"
                                                data-mutasi-pemegang="{{ $k->id_kendaraan }}"
                                                data-mutasi-nama="{{ $k->namaTampil() ?? 'Kendaraan' }}"
                                                data-mutasi-plat="{{ $platAktif->nomor_plat ?? '' }}"
                                                data-mutasi-pemegang-lama="{{ $namaPemegangRow ?? '' }}"
                                                data-mutasi-pemegang-id="{{ $idPemegang ?? '' }}"
                                                title="Mutasi Pemegang"
                                                class="flex h-7 w-7 items-center justify-center rounded-xl bg-indigo-50/70 text-indigo-500 transition hover:bg-indigo-100 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                                        </button>
                                        @endif
                                        <button type="button" data-delete-target="{{ $k->id_kendaraan }}" data-delete-name="{{ $k->namaTampil() ?? 'Kendaraan' }}" title="Hapus Kendaraan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdminAset ? 11 : ($isAdminUpt ? 9 : 8) }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada data kendaraan.</td>
                            </tr>
                        @endforelse
                </tbody>
            </table>
        </div>

        @if ($kendaraanList->hasPages())
            <div class="px-6 py-4">
                {{ $kendaraanList->links() }}
            </div>
        @endif
    </x-card>
    @if ($isAdminAset)
        <x-modal-confirm id="delete-modal"
                         title="Usulkan Penghapusan Kendaraan?"
                         message="Kendaraan akan masuk antrean usulan penghapusan dan hilang dari daftar aktif. Pembatalan dilakukan di halaman Penghapusan."
                         confirm-label="Usulkan Hapus"
                         confirm-variant="danger" />

        @push('modals')
            @include('kendaraan.partials.modal-kendaraan')
            @include('kendaraan.partials.modal-import')
            @include('kendaraan.partials.modal-mutasi')
            @include('kendaraan.partials.modal-berkas-mutasi')
        @endpush
    @endif

    @if ($isAdminAset || $isAdminUpt)
        @push('modals')
            @include('kendaraan.partials.modal-penempatan')
            @include('kendaraan.partials.modal-finalisasi')
        @endpush
    @endif

    @include('kendaraan.partials.scripts-index')
@endsection
