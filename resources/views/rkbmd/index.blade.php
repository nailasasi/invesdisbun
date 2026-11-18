@extends('layouts.app')

@section('title', 'RKBMD')
@section('page-title', 'RKBMD')
@section('breadcrumb', 'Perencanaan - Rencana Kebutuhan Barang Milik Daerah')

@section('content')
    <div class="space-y-4">
    <x-alert type="error" />
    <x-alert type="success" />
    {{-- ARSIP DOKUMEN SAH --}}
    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-800">Arsip Dokumen Sah RKBMD</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">Dokumen pengesahan bertanda tangan Kepala Dinas &middot; {{ $arsipList->count() }} berkas terarsip</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if (in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Super Admin', 'Admin Bidang', 'Admin UPT'], true))
                    <button type="button" data-open-modal="modalArsipUpload"
                            class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8-4-4m0 0L8 8m4-4v12"/></svg>
                        Upload
                    </button>
                @endif
                <button type="button" data-open-modal="modalArsipList"
                        class="inline-flex items-center justify-center rounded-2xl border border-disbun-card-border bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    Lihat Arsip
                </button>
            </div>
        </div>
    </x-card>

    {{-- 3. DAFTAR USULAN: CARD BENTO + ACTION BAR --}}
    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('rkbmd.index') }}"
                  class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Filter Unit Kerja / Bidang</span>
                @if ($isAdmin)
                    <span class="text-[11px] text-slate-400 italic">Pilih satu atau lebih</span>
                @endif
            </div>

            @if ($isAdmin)
                <div class="mb-3 flex flex-wrap gap-1.5">
                    @foreach ($bidangOptions as $opt)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="bidang[]" value="{{ $opt->id_skpd }}"
                                   class="peer sr-only" @checked(in_array($opt->id_skpd, $activeBidang))>
                            <span class="inline-block rounded-xl border border-slate-200 bg-slate-50/60 px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100 peer-checked:border-disbun-600 peer-checked:bg-disbun-50 peer-checked:font-bold peer-checked:text-disbun-700">
                                {{ $opt->nama_skpd }}
                            </span>
                        </label>
                    @endforeach
                </div>
            @else
                <div class="mb-3">
                    <span class="inline-flex items-center gap-1.5 rounded-2xl border border-disbun-100 bg-disbun-50 px-2.5 py-1 text-xs font-semibold text-disbun-700">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $mySkpd?->nama_skpd ?? 'Unit tidak terdeteksi' }}
                    </span>
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        Usulan yang Anda buat otomatis tercatat atas unit kerja tersebut.
                    </p>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 border-t border-slate-50 pt-2.5">
                <div class="w-32">
                    <select id="filter-tahun" name="tahun" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-disbun-600 focus:ring-1 focus:ring-disbun-400/40">
                        <option value="">Semua Tahun</option>
                        @foreach ($tahunList as $tahun)
                            <option value="{{ $tahun }}" @selected(request('tahun') == $tahun)>{{ $tahun }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <select id="filter-jenis" name="jenis" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-disbun-600 focus:ring-1 focus:ring-disbun-400/40">
                        <option value="">Semua Usulan</option>
                        @foreach ($jenisList as $jenis)
                            <option value="{{ $jenis }}" @selected(request('jenis') === $jenis)>{{ $jenis }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ml-auto flex items-center gap-1.5">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-2xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                    <a href="{{ route('rkbmd.index') }}"
                       class="inline-flex items-center justify-center rounded-2xl border border-disbun-card-border bg-white px-3.5 py-2 text-xs font-bold text-slate-500 shadow-sm transition hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </div>
            </form>

            <x-slot name="actions">
                @php
                    $canExport = in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Super Admin', 'Admin Bidang', 'Admin UPT'], true);
                @endphp
                @if ($canExport)
                    {{-- Tombol Buka Popup Pilih Jenis Usulan Ekspor (Admin Aset, Super Admin, Admin Bidang, Admin UPT) --}}
                    <button type="button" data-open-modal="modalExportRkbmd"
                       class="inline-flex items-center gap-1.5 rounded-2xl border border-disbun-card-border bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                       title="Ekspor Data RKBMD ke Excel Berdasarkan Template Resmi">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Ekspor Data</span>
                    </button>
                @endif
                <button type="button" data-open-modal="modalUsulan"
                        class="inline-flex items-center gap-1.5 rounded-2xl bg-disbun-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Buat Usulan
                </button>
            </x-slot>
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                        <th class="w-12 px-3 py-2.5 text-center">#</th>
                        <th class="px-3 py-2.5 whitespace-nowrap">Bidang / Unit</th>
                        <th class="px-3 py-2.5 min-w-[220px] max-w-[320px]">Program / Kegiatan</th>
                        <th class="px-3 py-2.5">Jenis</th>
                        <th class="px-3 py-2.5 min-w-[200px] max-w-[260px]">Nama Barang &amp; Kode</th>
                        <th class="px-3 py-2.5 text-center whitespace-nowrap">Jumlah/Satuan</th>
                        <th class="px-3 py-2.5 min-w-[180px] max-w-[240px]">Keterangan</th>
                        <th class="px-3 py-2.5 text-center whitespace-nowrap">Status</th>
                        <th class="px-3 py-2.5 text-center whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($usulanList as $i => $usulan)
                        @php
                            $isOwner = $usulan->id_pegawai && $usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai;
                            $status = $usulan->status_usulan;

                            $statusLabel = match ($status) {
                                'Draft' => 'Draf',
                                'Diajukan' => 'Diajukan',
                                'Disetujui Pengurus Barang' => 'Diterima',
                                'Ditolak' => 'Ditolak',
                                default => $status ?: '-',
                            };

                            $badgeJenis = [
                                'Pengadaan' => 'bg-disbun-100 text-disbun-800',
                                'Pemeliharaan' => 'bg-amber-100 text-amber-700',
                                'Penghapusan' => 'bg-rose-100 text-rose-700',
                            ][$usulan->jenis_usulan] ?? 'bg-slate-100 text-slate-600';

                            $badgeStatus = match ($status) {
                                'Draft' => 'bg-slate-100 text-slate-700',
                                'Diajukan' => 'bg-amber-100 text-amber-800',
                                'Disetujui Pengurus Barang' => 'bg-emerald-100 text-emerald-800',
                                'Ditolak' => 'bg-rose-100 text-rose-800',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr class="align-top transition hover:bg-slate-50/80">
                            <td class="px-3 py-2.5 text-center font-mono text-[11px] text-slate-400">{{ $usulanList->firstItem() + $i }}</td>
                            <td class="px-3 py-2.5 font-medium text-slate-700">
                                <p class="font-semibold text-slate-800">{{ $usulan->skpd?->nama_skpd ?? '-' }}</p>
                                <p class="text-[10px] text-slate-400">TA {{ $usulan->tahun_anggaran }}</p>
                            </td>
                            <td class="min-w-[240px] max-w-[340px] px-3 py-2.5">
                                @php
                                    $bidangText = $usulan->bidang_urusan ?: null;
                                    $programText = $usulan->nama_program ?: null;
                                    $subKegiatanText = $usulan->nama_sub_kegiatan ?: null;

                                    if (! $bidangText && ! $programText && $usulan->program_kegiatan) {
                                        $parts = explode(' - ', (string) $usulan->program_kegiatan);
                                        if (count($parts) === 3) {
                                            $bidangText = $parts[0];
                                            $programText = $parts[1];
                                            $subKegiatanText = $parts[2];
                                        }
                                    }
                                @endphp

                                @if ($bidangText || $programText || $subKegiatanText)
                                    <div class="space-y-1.5 text-xs">
                                        @if ($bidangText)
                                            <div class="leading-tight">
                                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Bidang Urusan</span>
                                                <p class="font-medium text-slate-700 break-words">{{ $bidangText }}</p>
                                            </div>
                                        @endif
                                        @if ($programText)
                                            <div class="leading-tight">
                                                <span class="block text-[9px] font-bold uppercase tracking-wider text-emerald-600">Program</span>
                                                <p class="font-semibold text-slate-800 break-words">{{ $programText }}</p>
                                            </div>
                                        @endif
                                        @if ($subKegiatanText)
                                            <div class="leading-tight">
                                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Sub Kegiatan</span>
                                                <p class="text-slate-600 break-words">{{ $subKegiatanText }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-xs text-slate-700 leading-relaxed break-words">
                                        {{ $usulan->program_kegiatan ?: '-' }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-block rounded-lg px-2 py-0.5 text-[10px] font-bold {{ $badgeJenis }}">{{ $usulan->jenis_usulan }}</span>
                            </td>
                            <td class="max-w-[240px] px-3 py-2.5">
                                <p class="truncate font-bold text-slate-800" title="{{ $usulan->nama_barang }}">{{ $usulan->nama_barang ?: '-' }}</p>
                                <p class="font-mono text-[10px] text-slate-400">{{ $usulan->kode_barang ?: '-' }}</p>
                                @if ($usulan->spesifikasi)
                                    <p class="mt-0.5 line-clamp-1 text-[10px] text-slate-500" title="{{ $usulan->spesifikasi }}">{{ $usulan->spesifikasi }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                <span class="font-bold text-slate-800">{{ $usulan->jumlah ?? '-' }}</span>
                                @if ($usulan->satuan)
                                    <span class="text-[10px] text-slate-400">{{ $usulan->satuan }}</span>
                                @endif
                            </td>
                            <td class="max-w-[220px] px-3 py-2.5">
                                <p class="line-clamp-2 text-slate-600" title="{{ $usulan->alasan_kebutuhan }}">{{ $usulan->alasan_kebutuhan }}</p>
                                @if ($usulan->catatan_pengurus)
                                    <p class="mt-1 rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-600">
                                        <span class="font-bold">Catatan:</span> {{ $usulan->catatan_pengurus }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold {{ $badgeStatus }}">
                                    <span class="mr-1 h-1.5 w-1.5 rounded-full bg-current opacity-75"></span>
                                    {{ $statusLabel }}
                                </span>
                                @if ($usulan->approved_at)
                                    <p class="mt-0.5 text-[10px] text-slate-400">{{ $usulan->approved_at->format('d M Y') }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
                                    {{-- 1. Detail Usulan (Ikon Mata) - Selalu tampil untuk semua status --}}
                                    <a href="{{ route('rkbmd.show', $usulan->id_usulan) }}"
                                       title="Detail Usulan"
                                       aria-label="Lihat detail usulan"
                                       class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-slate-600 shadow-2xs transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    {{-- A. Status Draf (Hanya untuk pengusul pemilik usulan) --}}
                                    @if ($status === 'Draft' && $isOwner)
                                        {{-- Ajukan Usulan (Ikon Pesawat Kertas) --}}
                                        <form method="POST" action="{{ route('rkbmd.submit', $usulan->id_usulan) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    title="Ajukan Usulan"
                                                    aria-label="Ajukan usulan ke Pengurus Barang"
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-disbun-50 text-disbun-700 shadow-2xs transition hover:bg-disbun-100 hover:text-disbun-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                                </svg>
                                            </button>
                                        </form>

                                        {{-- Edit Usulan (Ikon Pensil) --}}
                                        <button type="button" data-open-modal="modalUsulan" data-edit-usulan
                                                data-action="{{ route('rkbmd.update', $usulan->id_usulan) }}"
                                                data-id-skpd="{{ $usulan->id_skpd }}"
                                                data-tahun="{{ $usulan->tahun_anggaran }}"
                                                data-jenis="{{ $usulan->jenis_usulan }}"
                                                data-id-kegiatan="{{ $usulan->id_kegiatan }}"
                                                data-bidang-urusan="{{ $usulan->bidang_urusan }}"
                                                data-nama-program="{{ $usulan->nama_program }}"
                                                data-nama-sub-kegiatan="{{ $usulan->nama_sub_kegiatan }}"
                                                data-program="{{ $usulan->program_kegiatan }}"
                                                data-kode="{{ $usulan->kode_barang }}"
                                                data-nama="{{ $usulan->nama_barang }}"
                                                data-spesifikasi="{{ $usulan->spesifikasi }}"
                                                data-jumlah="{{ $usulan->jumlah }}"
                                                data-satuan="{{ $usulan->satuan }}"
                                                data-kebutuhan-maks="{{ $usulan->kebutuhan_maksimum }}"
                                                data-kebutuhan-riil="{{ $usulan->kebutuhan_riil }}"
                                                data-opt="@json($usulan->barang_optimalisasi)"
                                                data-alasan="{{ $usulan->alasan_kebutuhan }}"
                                                title="Edit Usulan"
                                                aria-label="Edit usulan"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-disbun-700 shadow-2xs transition hover:bg-disbun-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>

                                        {{-- Batalkan Usulan (Ikon Circle-X) --}}
                                        <form method="POST" action="{{ route('rkbmd.destroy', $usulan->id_usulan) }}"
                                              onsubmit="return confirm('Batalkan usulan draf ini? Tindakan ini akan menghapus draf usulan.');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    title="Batalkan Usulan"
                                                    aria-label="Batalkan usulan draf"
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 shadow-2xs transition hover:bg-rose-100 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- B. Status Diajukan (Setujui & Tolak hanya untuk Super Admin / Admin Aset) --}}
                                    @if ($status === 'Diajukan' && $isAdmin)
                                        {{-- Setujui Usulan (Ikon Centang) --}}
                                        <form method="POST" action="{{ route('rkbmd.decide', $usulan->id_usulan) }}"
                                              onsubmit="return confirm('Setujui usulan ini sebagai usulan resmi RKBMD?');" class="inline">
                                            @csrf
                                            <input type="hidden" name="keputusan" value="setuju">
                                            <button type="submit"
                                                    title="Setujui Usulan"
                                                    aria-label="Setujui usulan"
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 shadow-2xs transition hover:bg-emerald-100 hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </button>
                                        </form>

                                        {{-- Tolak Usulan (Ikon X) --}}
                                        <button type="button" data-open-modal="modalTolak-{{ $usulan->id_usulan }}"
                                                title="Tolak Usulan"
                                                aria-label="Tolak usulan dengan catatan"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 shadow-2xs transition hover:bg-rose-100 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    @endif

                                    {{-- C & D. Status Diterima ('Disetujui Pengurus Barang') atau Ditolak (Hapus hanya untuk Super Admin / Admin Aset) --}}
                                    @if (in_array($status, ['Disetujui Pengurus Barang', 'Ditolak'], true) && $isAdmin)
                                        {{-- Hapus Usulan (Ikon Tempat Sampah) --}}
                                        <form method="POST" action="{{ route('rkbmd.destroy', $usulan->id_usulan) }}"
                                              onsubmit="return confirm('Hapus usulan RKBMD ini dari daftar?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    title="Hapus Usulan"
                                                    aria-label="Hapus usulan"
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-slate-400 shadow-2xs transition hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="mt-2 text-sm font-semibold text-slate-600">Belum ada usulan</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">Klik "Buat Usulan" untuk menyusun kebutuhan BMD unit Anda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usulanList->hasPages())
            <div class="border-t border-disbun-card-border px-6 py-4">
                {{ $usulanList->links() }}
            </div>
        @endif
    </x-card>
    </div>

    @push('modals')
        @include('rkbmd.partials.modal-create')

        @if (in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Super Admin', 'Admin Bidang', 'Admin UPT'], true))
            {{-- MODAL UPLOAD ARSIP --}}
            <div id="modalArsipUpload" class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-modal>
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-close></div>
                <div class="relative w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/></svg>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Unggah Dokumen Sah RKBMD</h3>
                                <p class="mt-0.5 text-xs text-slate-400">PDF/scan SK pengesahan bertanda tangan Kadis</p>
                            </div>
                        </div>
                        <button type="button" data-close class="text-slate-400 transition hover:text-slate-600" title="Tutup">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('rkbmd.arsip.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        @if ($isAdmin)
                            <div>
                                <label for="arsip-skpd" class="mb-1.5 block text-sm font-semibold text-slate-700">Bidang / Unit Kerja <span class="text-red-500">*</span></label>
                                <select id="arsip-skpd" name="id_skpd" required
                                        class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                                    <option value="" disabled selected>-- Pilih Unit Kerja --</option>
                                    @foreach ($bidangOptions as $opt)
                                        <option value="{{ $opt->id_skpd }}">{{ $opt->nama_skpd }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700">Bidang / Unit Kerja</label>
                                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-700">
                                    <svg class="h-4 w-4 text-disbun-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span>{{ $mySkpd?->nama_skpd ?? 'Unit Kerja Terdaftar' }}</span>
                                </div>
                                <input type="hidden" name="id_skpd" value="{{ $mySkpd?->id_skpd }}">
                                <p class="mt-1 text-[11px] text-slate-400">Unit kerja ditentukan secara otomatis sesuai akun Anda.</p>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="arsip-tahun" class="mb-1.5 block text-sm font-semibold text-slate-700">Tahun Anggaran <span class="text-red-500">*</span></label>
                                <input type="number" id="arsip-tahun" name="tahun_anggaran" min="2000" max="2100"
                                       value="{{ old('tahun_anggaran', now()->year + 1) }}" required
                                       class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            </div>
                            <div>
                                <label for="arsip-tanggal" class="mb-1.5 block text-sm font-semibold text-slate-700">Tanggal Pengesahan Kadis</label>
                                <input type="date" id="arsip-tanggal" name="tanggal_pengesahan" value="{{ old('tanggal_pengesahan', now()->toDateString()) }}"
                                       class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            </div>
                        </div>

                        <div>
                            <label for="arsip-jenis" class="mb-1.5 block text-sm font-semibold text-slate-700">Jenis Dokumen <span class="text-red-500">*</span></label>
                            <select id="arsip-jenis" name="jenis_dokumen" required
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                                <option value="" disabled {{ old('jenis_dokumen') ? '' : 'selected' }}>-- Pilih Jenis Dokumen --</option>
                                @foreach (\App\Models\ArsipRkbmd::JENIS_DOKUMEN as $jenisDokumen)
                                    <option value="{{ $jenisDokumen }}" @selected(old('jenis_dokumen') === $jenisDokumen)>{{ $jenisDokumen }}</option>
                                @endforeach
                            </select>
                            @error('jenis_dokumen')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="arsip-nama" class="mb-1.5 block text-sm font-semibold text-slate-700">Nama Dokumen <span class="font-normal text-slate-400">(opsional)</span></label>
                            <input type="text" id="arsip-nama" name="nama_dokumen" maxlength="255"
                                   placeholder="Contoh: SK Pengesahan RKBMD 2027"
                                   value="{{ old('nama_dokumen') }}"
                                   class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        </div>

                        <div>
                            <label for="arsip-file" class="mb-1.5 block text-sm font-semibold text-slate-700">File Dokumen (PDF) <span class="text-red-500">*</span></label>
                            <input type="file" id="arsip-file" name="file_dokumen" accept="application/pdf" required
                                   class="block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-disbun-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-disbun-700 hover:bg-slate-100">
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button type="button" data-close
                                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-2xl bg-disbun-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800">
                                Unggah Dokumen
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/></svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- MODAL LIHAT ARSIP --}}
        @include('rkbmd.partials.modal-list-arsip')

        {{-- MODAL PILIH JENIS USULAN EKSPOR RKBMD --}}
        <div id="modalExportRkbmd" class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-modal>
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" data-close></div>
            <div class="relative w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">PILIH JENIS USULAN RKBMD</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Pilih jenis usulan RKBMD yang ingin diekspor berdasarkan template resmi.</p>
                        </div>
                    </div>
                    <button type="button" data-close class="rounded-xl p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" title="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4 space-y-2.5">
                    @php
                        $opsiExport = [
                            [
                                'jenis' => 'pengadaan',
                                'label' => 'Usulan RKBMD Pengadaan',
                                'deskripsi' => 'Format 15 kolom: usulan, kebutuhan maksimum, optimalisasi & kebutuhan riil',
                                'icon_color' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            ],
                            [
                                'jenis' => 'pemeliharaan',
                                'label' => 'Usulan RKBMD Pemeliharaan',
                                'deskripsi' => 'Format 14 kolom: barang dipelihara dan kolom isian manual kondisi barang',
                                'icon_color' => 'bg-sky-50 text-sky-700 border-sky-200',
                            ],
                            [
                                'jenis' => 'penghapusan',
                                'label' => 'Usulan RKBMD Penghapusan',
                                'deskripsi' => 'Format resmi 10 kolom: spesifikasi barang, NIBAR, jumlah dan alasan penghapusan',
                                'icon_color' => 'bg-rose-50 text-rose-700 border-rose-200',
                            ],
                            [
                                'jenis' => 'pemindahtanganan',
                                'label' => 'Usulan RKBMD Pemindahtanganan',
                                'deskripsi' => 'Format resmi 12 kolom: identitas barang, NIBAR, bentuk dan alasan pemindahtanganan',
                                'icon_color' => 'bg-amber-50 text-amber-700 border-amber-200',
                            ],
                            [
                                'jenis' => 'pemanfaatan',
                                'label' => 'Usulan RKBMD Pemanfaatan',
                                'deskripsi' => 'Format 24 kolom: SKPD pengusul, validasi data pemanfaatan & rencana kebutuhan',
                                'icon_color' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            ],
                        ];
                    @endphp

                    @foreach ($opsiExport as $opsi)
                        <a href="{{ route('rkbmd.export', array_merge(request()->query(), ['jenis' => $opsi['jenis']])) }}"
                           class="group flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50/50 p-3.5 transition hover:border-emerald-500 hover:bg-emerald-50/30 hover:shadow-sm">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border {{ $opsi['icon_color'] }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 transition group-hover:text-emerald-700">{{ $opsi['label'] }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $opsi['deskripsi'] }}</p>
                                </div>
                            </div>
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-slate-400 shadow-xs transition group-hover:bg-emerald-600 group-hover:text-white">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </span>
                        </a>
                    @endforeach
                </div>

                <div class="mt-5 flex items-center justify-end border-t border-slate-100 pt-3">
                    <button type="button" data-close
                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                        Batal
                    </button>
                </div>
            </div>
        </div>


        {{-- MODAL TOLAK (per usulan, khusus admin) --}}
        @foreach ($usulanList as $usulan)
            @if (!$usulan->status_usulan || in_array($usulan->status_usulan, ['Draft', 'Diajukan']))
                <div id="modalTolak-{{ $usulan->id_usulan }}" class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-modal>
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-close></div>
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
                                <label for="catatan-{{ $usulan->id_usulan }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Alasan Penolakan <span class="text-red-500">*</span>
                                </label>
                                <textarea id="catatan-{{ $usulan->id_usulan }}" name="catatan_pengurus" rows="4" maxlength="1000" required
                                          placeholder="Contoh: spesifikasi tidak sesuai kebutuhan riil unit, atau melebihi standar harga"
                                          class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400"></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-close
                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-700">
                                    Kirim Penolakan
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach
    @endpush

    @push('scripts')
        <script>
            // Buka/tutup modal generik (data-open-modal, data-modal, data-close).
            document.querySelectorAll('[data-open-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const modal = document.getElementById(button.getAttribute('data-open-modal'));
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
                });
            });

            document.querySelectorAll('[data-modal]').forEach(function (modal) {
                function close() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                modal.querySelectorAll('[data-close]').forEach(function (el) {
                    el.addEventListener('click', close);
                });
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) close();
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

            </script>
    @endpush
@endsection
