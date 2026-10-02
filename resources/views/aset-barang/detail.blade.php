@extends('layouts.app')

@section('title', 'Detail Aset')

@section('page-title', 'Detail Aset Barang')

@section('content')
    @php
        \Carbon\Carbon::setLocale('id');
        $kondisiColor = match ($aset->kondisi) {
            'Baik' => 'bg-disbun-100 text-disbun-800',
            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
            'Rusak Berat' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $statusAktif = ($aset->status_aset ?? 'aktif') === 'aktif';
        $pemegang = $aset->pemegangSaatIni?->pegawai;
        $ruangan = $aset->penempatanAktif?->ruangan;
        $words = preg_split('/\s+/', trim((string) ($pemegang->nama_pegawai ?? '?')));
        $inisial = strtoupper(mb_substr($words[0] ?? '?', 0, 1) . mb_substr($words[1] ?? ($words[0] ?? ''), 0, 1));
    @endphp

    {{-- Tombol Kembali --}}
    <div class="mb-4">
        <a href="{{ route('aset-barang.index') }}"
           class="inline-flex items-center gap-1.5 rounded-xl border border-disbun-card-border bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 focus-visible:ring-offset-2">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Aset Barang</span>
        </a>
    </div>

    {{-- Hero Card --}}
    <x-card class="mb-6">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div class="min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Detail Aset</h2>
                    <span class="inline-flex items-center gap-1.5 rounded-full {{ $statusAktif ? 'bg-disbun-50 text-disbun-800' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide border {{ $statusAktif ? 'border-disbun-200' : 'border-amber-200' }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ $aset->status_aset ?? 'aktif' }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-base font-semibold text-slate-900">{{ $aset->barang?->nama_barang ?? 'Barang' }}</p>
                    <span class="inline-flex items-center gap-2 rounded-full border border-disbun-card-border bg-slate-50 px-3 py-1 font-mono text-xs font-bold text-slate-700">
                        {{ $aset->nomor_kartu_barang ?? '-' }}
                        <button type="button" id="btn-copy-kartu" class="text-slate-400 transition hover:text-disbun-700" title="Salin Nomor Kartu Barang">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('aset-barang.aset.qr', $aset->id_aset) }}" target="_blank"
                   class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 focus-visible:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 3h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1zm11 0h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V4a1 1 0 011-1zM5 15h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4a1 1 0 011-1z"/></svg>
                    Cetak Label QR
                </a>
                @if ($isAdminAset)
                    <button type="button" data-edit-modal="{{ $aset->id_aset }}"
                            class="inline-flex items-center gap-2 rounded-2xl border border-disbun-card-border bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 focus-visible:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit Data
                    </button>
                @endif
            </div>
        </div>
    </x-card>

    {{-- Grid 6 Kartu Spesifikasi Barang --}}
    <div class="mb-2 flex items-end justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Informasi Spesifikasi Barang</h3>
            <p class="mt-0.5 text-xs text-slate-400">Detail data inventaris tercatat pada kartu barang.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Kategori</p>
            <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $aset->barang?->kategori?->nama_kategori ?? '-' }}</p>
        </div>

        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-3.75a6 6 0 0115.75-2.25z"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Nomor Kartu Barang</p>
            <p class="mt-1 break-all font-mono text-sm font-bold text-slate-900">{{ $aset->nomor_kartu_barang ?? '-' }}</p>
        </div>

        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Merk</p>
            <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $aset->merk ?? '-' }}</p>
        </div>

        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Kondisi</p>
            <p class="mt-1">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
            </p>
        </div>

        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Nilai Perolehan</p>
            <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</p>
        </div>

        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal Perolehan</p>
            <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $aset->tanggal_perolehan?->format('d M Y') ?? '-' }}</p>
        </div>

        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Unit Penanggung Jawab</p>
            <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $aset->skpd?->nama_skpd ?? '-' }}</p>
            @if ($aset->skpd?->jenis_skpd)
                <span class="mt-1.5 inline-flex items-center rounded-full border border-disbun-100 bg-disbun-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-disbun-800">{{ $aset->skpd->jenis_skpd }}</span>
            @endif
        </div>

        <div class="rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-disbun-50 text-disbun-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
            </div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-wider text-slate-400">Lokasi Fisik</p>
            <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $aset->lokasi?->nama_lokasi ?? '-' }}</p>
        </div>
    </div>

    {{-- Bar Informasi PIC & Ruangan Lokasi --}}
    <x-card class="mt-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 md:divide-x md:divide-slate-100">
            <div class="flex min-w-0 items-start gap-4 md:pr-6">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-disbun-700 text-sm font-extrabold text-white shadow-sm">
                    {{ $inisial }}
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">PIC / Pemegang Saat Ini</p>
                    @if ($pemegang)
                        <a href="{{ route('aset-barang.show', $pemegang->id_pegawai) }}"
                           class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-disbun-100 py-1 pl-3 pr-2.5 text-sm font-bold text-disbun-800 transition hover:bg-disbun-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 focus-visible:ring-offset-2">
                            <span class="truncate">{{ $pemegang->nama_pegawai }}</span>
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        </a>
                        <p class="mt-1.5 text-xs text-slate-500">
                            {{ $pemegang->jabatan ?? 'Staf' }}
                            @if ($pemegang->nip)
                                <span class="mx-1 text-slate-300">&bull;</span>
                                <span class="font-mono">{{ $pemegang->nip }}</span>
                            @endif
                        </p>
                    @else
                        <p class="mt-1 text-sm font-bold text-slate-400">Tanpa pemegang</p>
                    @endif
                </div>
            </div>

            <div class="min-w-0 md:pl-6">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ruangan Lokasi</p>
                @if ($ruangan)
                    <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $ruangan->nama_ruangan }}</p>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        @if ($ruangan->lantai)
                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                                Lantai {{ $ruangan->lantai }}
                            </span>
                        @endif
                        @if ($ruangan->skpd)
                            <span class="inline-flex items-center rounded-full border border-disbun-100 bg-disbun-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-disbun-800">{{ $ruangan->skpd->nama_skpd }}</span>
                        @endif
                    </div>
                @else
                    <p class="mt-1 text-sm font-bold text-slate-400">Belum ditempatkan</p>
                @endif
            </div>
        </div>
    </x-card>

    {{-- Masa Aset --}}
    <x-card class="mt-6">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:divide-x sm:divide-slate-100">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal Pengadaan</p>
                <p class="mt-1 text-sm font-bold text-slate-900">{{ $aset->tanggal_pengadaan?->format('d M Y') ?? '-' }}</p>
            </div>
            <div class="min-w-0 sm:pl-6">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal Habis Pakai</p>
                <p class="mt-1 text-sm font-bold text-slate-900">{{ $aset->tanggal_habis_pakai?->format('d M Y') ?? '-' }}</p>
            </div>
        </div>
    </x-card>

    {{-- Riwayat Mutasi --}}
    <x-card :padding="false" class="mt-6">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Riwayat Mutasi</h3>
                <p class="mt-0.5 text-xs text-slate-400">Jejak perpindahan pemegang & ruangan tercatat otomatis.</p>
            </div>
        </div>
        @php
            $riwayatGrouped = $riwayatMutasi
                ->groupBy(fn ($item) => $item->id_mutasi)
                ->sortByDesc(fn ($items) => $items->first()->mutasi?->tanggal_mutasi?->getTimestamp() ?? 0);
        @endphp

        @if ($riwayatGrouped->isEmpty())
            <div class="px-6 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-500">Belum ada riwayat mutasi untuk barang ini.</p>
            </div>
        @else
            <div class="px-6 pb-2">
                @foreach ($riwayatGrouped as $items)
                    @php
                        $mutasi = $items->first()->mutasi;
                        $jenisMutasi = $mutasi?->jenis_mutasi ?? '-';
                        $gantiPemegang = $jenisMutasi === 'Ganti Pemegang';
                    @endphp
                    <div class="relative flex gap-4 py-5">
                        @if (! $loop->last)
                            <span class="absolute left-[18px] top-[56px] bottom-[-20px] w-px bg-slate-200" aria-hidden="true"></span>
                        @endif

                        <span class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl shadow-sm {{ $gantiPemegang ? 'bg-disbun-700 text-white' : 'bg-slate-100 text-slate-600' }}">
                            @if ($gantiPemegang)
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                            @else
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-900">{{ $mutasi?->tanggal_mutasi?->format('d M Y') ?? '-' }}</span>
                                <span class="inline-flex items-center rounded-full border border-disbun-100 bg-disbun-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-disbun-700">{{ $jenisMutasi }}</span>
                                @if (($mutasi?->status_mutasi ?? '') !== '')
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $mutasi->status_mutasi }}</span>
                                @endif
                                @if ($gantiPemegang)
                                    <a href="{{ route('mutasi-aset.bast.download', $mutasi?->id_mutasi) }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 transition hover:bg-sky-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                                       title="Unduh Berkas BAST">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span>Unduh BAST</span>
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] italic text-slate-400" title="Pemindahan ruangan tercatat di KIR Ruangan">Tercatat di KIR Ruangan</span>
                                @endif
                            </div>

                            @if ($items->count() > 1)
                                <p class="mt-1 text-[11px] font-semibold text-slate-400">{{ $items->count() }} aset dalam satu peristiwa</p>
                            @endif

                            <div class="mt-2.5 flex flex-col gap-2">
                                @foreach ($items as $item)
                                    <p class="flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Pemegang</span>
                                        <span class="font-semibold text-slate-400">{{ $item->pegawaiLama?->nama_pegawai ?? '-' }}</span>
                                        <span class="text-slate-300">&rarr;</span>
                                        <span class="font-bold text-disbun-700">{{ $item->pegawaiBaru?->nama_pegawai ?? '-' }}</span>
                                    </p>
                                    <p class="flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Ruangan</span>
                                        <span class="font-semibold text-slate-400">{{ $item->ruanganLama?->nama_ruangan ?? '-' }}</span>
                                        <span class="text-slate-300">&rarr;</span>
                                        <span class="font-bold text-disbun-700">{{ $item->ruanganBaru?->nama_ruangan ?? '-' }}</span>
                                    </p>
                                @endforeach
                            </div>

                            @if (($mutasi?->keterangan ?? '') !== '')
                                <p class="mt-2.5 rounded-2xl border border-disbun-card-border bg-slate-50/70 px-3.5 py-2.5 text-xs text-slate-600">{{ $mutasi->keterangan }}</p>
                            @endif

                            <p class="mt-2.5 text-[11px] text-slate-400">
                                Diinput oleh
                                <span class="font-semibold text-slate-600">{{ $mutasi?->userPenginput?->pegawai?->nama_pegawai ?? $mutasi?->userPenginput?->username ?? '-' }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex flex-col gap-1 border-t border-slate-100 px-6 py-3 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $riwayatGrouped->count() }} peristiwa &middot; {{ $riwayatMutasi->count() }} detail riwayat mutasi</span>
            <span class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Terakhir disinkronkan {{ now()->translatedFormat('d M Y, H:i') }}
            </span>
        </div>
    </x-card>

    {{-- Modal Edit Aset --}}
    <div id="aset-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Edit Aset</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Perbarui data aset. Ganti pemegang / pindah ruangan via halaman Aset Barang.</p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="aset-form" method="POST" autocomplete="off">
                @csrf
                <input type="hidden" id="field-id" name="id" value="">
                <div class="max-h-[90vh] space-y-5 overflow-y-auto px-6 py-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="field-barang" class="block text-sm font-medium text-slate-700">Nama Barang <span class="text-red-500">*</span></label>
                            <input type="text" id="field-barang" name="nama_barang" required maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Laptop">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_barang"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-kartu" class="block text-sm font-medium text-slate-700">Nomor Kartu Barang <span class="text-red-500">*</span></label>
                            <input type="text" id="field-kartu" name="nomor_kartu_barang" maxlength="255" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: KIB-001">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_kartu_barang"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-merk" class="block text-sm font-medium text-slate-700">Merk</label>
                            <input type="text" id="field-merk" name="merk" maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Canon">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="merk"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-nilai" class="block text-sm font-medium text-slate-700">Nilai Perolehan</label>
                            <input type="number" id="field-nilai" name="nilai_perolehan" step="0.01" min="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 5000000">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nilai_perolehan"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-kondisi" class="block text-sm font-medium text-slate-700">Kondisi <span class="text-red-500">*</span></label>
                            <select id="field-kondisi" name="kondisi" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <option value="" selected>-- Pilih Kondisi --</option>
                                @foreach ($kondisiList as $kondisi)
                                    <option value="{{ $kondisi }}">{{ $kondisi }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="kondisi"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-status" class="block text-sm font-medium text-slate-700">Status Aset <span class="text-red-500">*</span></label>
                            <input type="text" id="field-status" name="status_aset" required maxlength="50" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: aktif">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="status_aset"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-pengadaan" class="block text-sm font-medium text-slate-700">Tanggal Pengadaan</label>
                            <input type="date" id="field-pengadaan" name="tanggal_pengadaan" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_pengadaan"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-perolehan" class="block text-sm font-medium text-slate-700">Tanggal Perolehan</label>
                            <input type="date" id="field-perolehan" name="tanggal_perolehan" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_perolehan"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-habis-pakai" class="block text-sm font-medium text-slate-700">Tanggal Habis Pakai</label>
                            <input type="date" id="field-habis-pakai" name="tanggal_habis_pakai" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_habis_pakai"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-skpd" class="block text-sm font-medium text-slate-700">Unit Penanggung Jawab</label>
                            <select id="field-skpd" name="id_skpd" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <option value="">-- Pilih Unit PJ --</option>
                                @foreach ($skpdOptions as $skpd)
                                    <option value="{{ $skpd->id_skpd }}">{{ $skpd->nama_skpd }} ({{ $skpd->jenis_skpd }})</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_skpd"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-lokasi" class="block text-sm font-medium text-slate-700">Lokasi Fisik</label>
                            <input
                                type="text"
                                id="field-lokasi"
                                readonly
                                placeholder="-- Mengikuti ruangan --"
                                class="block w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-600 shadow-sm focus:outline-none transition"
                            >
                            <p class="text-xs text-slate-400">Lokasi fisik mengikuti ruangan. Pindah lokasi via tombol Mutasi di halaman Aset Barang.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                        Perbarui
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const showUrl = @json(route('aset-barang.aset.show', ['aset' => '__ID__']));
        const updateUrl = @json(route('aset-barang.aset.update', ['aset' => '__ID__']));

        {{-- Salin Nomor Kartu Barang --}}
        document.getElementById('btn-copy-kartu').addEventListener('click', async (e) => {
            const text = @json($aset->nomor_kartu_barang ?? '');
            try {
                await navigator.clipboard.writeText(text);
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
            const btn = e.currentTarget;
            const old = btn.innerHTML;
            btn.innerHTML = '<svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            setTimeout(() => { btn.innerHTML = old; }, 1500);
        });

        {{-- Modal Edit Aset --}}
        const modal = document.getElementById('aset-modal');
        const form = document.getElementById('aset-form');

        function showErrors(errors) {
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors).forEach(field => {
                const err = form.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }

        function openModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }
        modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));

        document.querySelectorAll('[data-edit-modal]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.editModal;
                form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                try {
                    const res = await fetch(showUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('Gagal mengambil data');
                    const data = await res.json();
                    document.getElementById('field-id').value = data.id_aset;
                    document.getElementById('field-barang').value = data.nama_barang ?? '';
                    document.getElementById('field-kartu').value = data.nomor_kartu_barang ?? '';
                    document.getElementById('field-merk').value = data.merk ?? '';
                    document.getElementById('field-nilai').value = data.nilai_perolehan ?? '';
                    document.getElementById('field-kondisi').value = data.kondisi ?? '';
                    document.getElementById('field-status').value = data.status_aset ?? '';
                    document.getElementById('field-pengadaan').value = data.tanggal_pengadaan ?? '';
                    document.getElementById('field-perolehan').value = data.tanggal_perolehan ?? '';
                    document.getElementById('field-habis-pakai').value = data.tanggal_habis_pakai ?? '';
                    document.getElementById('field-skpd').value = data.id_skpd ?? '';
                    document.getElementById('field-lokasi').value = data.nama_lokasi ?? '';
                    form.action = updateUrl.replace('__ID__', id);
                    openModal();
                } catch (e) {
                    alert(e.message);
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
                    window.location.reload();
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
    </script>
    @endpush
@endsection
