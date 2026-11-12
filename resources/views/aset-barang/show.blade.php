@extends('layouts.app')

@section('title', 'Detail Aset Barang')

@section('page-title', 'Detail Aset Barang')

@section('content')
    {{-- Navigasi Kembali --}}
    <div class="mb-4">
        <a href="{{ $kembaliUrl ?? route('aset-barang.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-disbun-700">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ $kembaliLabel ?? 'Kembali ke Aset Barang' }}
        </a>
    </div>

    {{-- Header Halaman & Action Buttons --}}
    <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Aset {{ $pegawai->nama_pegawai }}</h2>
            <p class="mt-1 text-sm text-slate-500">Daftar aset inventaris yang dipegang dan menjadi tanggung jawab pegawai.</p>
        </div>

        @if (($canPrintLabel ?? false) || (($canManage ?? false) && $asetPasang->isNotEmpty()))
        <div class="flex flex-wrap items-center justify-end gap-2">
            {{-- 1. Cetak Semua Label --}}
            @if ($canPrintLabel ?? false)
            <a href="{{ route('aset-barang.label.download', $pegawai->id_pegawai) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs font-semibold text-amber-700 shadow-2xs transition hover:bg-amber-100"
               title="Unduh semua label aset pegawai dalam format Excel">
                <svg class="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h.008v.008H6V6z"/></svg>
                <span>Cetak Semua Label</span>
            </a>
            @endif

            {{-- 2. Tombol Utama: Pasang Aset (existing master, bukan membuat master baru) --}}
            @if (($canManage ?? false) && $asetPasang->isNotEmpty())
            <button type="button" id="btn-pasang" class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-disbun-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Pasang Aset
            </button>
            @endif
        </div>
        @endif
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- Kartu Profil Pegawai --}}
    @php \Carbon\Carbon::setLocale('id'); @endphp
    <div class="mb-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
        <div class="flex flex-row items-center justify-between gap-6">
            {{-- Sisi Kiri: Avatar & Identitas --}}
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center">
                @php
                    $words = preg_split('/\s+/', trim((string) $pegawai->nama_pegawai));
                    $initOne = mb_substr($words[0] ?? '?', 0, 1);
                    $initTwo = isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0] ?? '', 1, 1);
                    $inisial = strtoupper($initOne.$initTwo);
                @endphp
                {{-- Avatar Kotak --}}
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-xl font-extrabold tracking-wide text-white shadow-md">
                    {{ $inisial }}
                </div>

                <div class="min-w-0">
                    <h3 class="break-words text-xl font-bold text-slate-900">{{ $pegawai->nama_pegawai }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $pegawai->jabatan ?? 'Staf' }} &bull; {{ $pegawai->skpd?->nama_skpd ?? 'Dinas Perkebunan' }}
                    </p>
                </div>
            </div>

            {{-- Sisi Kanan: Kartu Statistik Ringkas (NIP, Total Aset Barang & Total Kendaraan) --}}
            <div class="inline-flex flex-wrap items-center gap-x-5 gap-y-3 rounded-2xl border border-slate-100 bg-slate-50/60 px-6 py-4 shrink-0">
                <div class="min-w-0">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">NIP</span>
                    <span class="mt-1 block break-all font-mono text-sm font-bold text-slate-700">{{ $pegawai->nip ?? '-' }}</span>
                </div>

                <span class="h-8 w-px bg-slate-200"></span>

                <div class="min-w-0">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Aset Barang</span>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold leading-none text-emerald-600">{{ $asetList->count() }}</span>
                        <span class="text-[10px] font-semibold text-slate-400">Unit Barang</span>
                    </div>
                </div>

                <span class="h-8 w-px bg-slate-200"></span>

                {{-- Sumber sama dengan tabel "Aset Kendaraan Dipegang": hanya
                     kendaraan dengan nama pemegang cocok PERSIS & UNIK. --}}
                <div class="min-w-0">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Kendaraan</span>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold leading-none text-emerald-600">{{ $kendaraanList->count() }}</span>
                        <span class="text-[10px] font-semibold text-slate-400">Unit Kendaraan</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daftar aset --}}
    <div class="mt-6">
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Aset Barang Dipegang</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kartu Barang</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Merk</th>
                            @if ($isAdminAset)
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            @endif
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kondisi</th>
                            @if ($isAdminAset)
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SPPBI</th>
                            @endif
                            @if (($canPrintLabel ?? false) || ($canManage ?? false))
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($asetList as $i => $item)
                            @php $aset = $item->aset; @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $aset->barang->nama_barang ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nomor_kartu_barang ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->merk ?? '-' }}</td>
                                @if ($isAdminAset)
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->status_aset ?? '-' }}</td>
                                @endif
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @php
                                        $kondisiColor = match ($aset->kondisi) {
                                            'Baik' => 'bg-disbun-100 text-disbun-800',
                                            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                                            'Rusak Berat' => 'bg-red-100 text-red-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                                </td>
                                @if ($isAdminAset)
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if ($item->id_pegawai && ($aset->siap_sppbi ?? false))
                                            <a href="{{ route('aset-barang.sppbi.download.word', $item->id_pegawai) }}"
                                               title="Unduh SPPBI {{ $pegawai->nama_pegawai }}"
                                               class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                                SPPBI
                                            </a>
                                        @else
                                            {{-- SPPBI hanya tampil bila penempatan aktif sudah difinalisasi (Sudah Difinalisasi). --}}
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                @endif
                                @if (($canPrintLabel ?? false) || ($canManage ?? false))
                                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                                        <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                            @if ($canPrintLabel ?? false)
                                                <a href="{{ route('aset-barang.cetak.label.single', $aset->id_aset) }}" target="_blank" title="Cetak Label" class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h.01"/></svg>
                                                </a>
                                            @endif
                                            @if ($canManage ?? false)
                                                <button type="button" data-delete-target="{{ $aset->id_aset }}" data-delete-name="{{ $aset->barang->nama_barang ?? $aset->nomor_kartu_barang }}" title="Hapus" class="flex h-7 w-7 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($isAdminAset ? 8 : 4) + (($canPrintLabel ?? false) || ($canManage ?? false) ? 1 : 0) }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada aset yang dipegang pegawai ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Daftar kendaraan yang dipegang --}}
    <div class="mt-6">
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Aset Kendaraan Dipegang</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Kendaraan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plat Aktif</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pajak Tahunan</th>
                            @if ($isAdminAset)
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SPPKD</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($kendaraanList as $i => $k)
                            @php
                                $platAktif = $k->platAktif;
                                $pajakAktif = $k->pajakAktif;
                                $pajakJatuh = $pajakAktif?->tanggal_berakhir;
                                $now = \Carbon\Carbon::today();
                                $diffPajak = $pajakJatuh ? $now->diffInDays($pajakJatuh, false) : null;
                                $pajakClass = match (true) {
                                    $diffPajak === null => 'bg-slate-100 text-slate-600',
                                    $diffPajak < 0 => 'bg-red-100 text-red-700',
                                    $diffPajak <= 30 => 'bg-amber-100 text-amber-700',
                                    default => 'bg-emerald-100 text-emerald-700',
                                };
                                $pajakLabel = match (true) {
                                    $diffPajak === null => '-',
                                    $diffPajak < 0 => $pajakJatuh->format('d M Y') . ' (Terlambat)',
                                    $pajakJatuh->isSameDay($now) => $pajakJatuh->format('d M Y') . ' (Jatuh Hari Ini)',
                                    $diffPajak <= 30 => $pajakJatuh->format('d M Y') . ' (' . (int) round($diffPajak) . ' hari lagi)',
                                    default => $pajakJatuh->format('d M Y'),
                                };
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <a href="{{ route('kendaraan.show', $k->id_kendaraan) }}" class="font-medium text-disbun-700 hover:underline">
                                        {{ $k->namaTampil() ?? 'Kendaraan' }}
                                    </a>
                                    <p class="text-xs text-slate-400">{{ $k->aset?->nomor_kartu_barang ?? '-' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $k->jenis_kendaraan ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-700">{{ $platAktif?->nomor_plat ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @if ($pajakJatuh)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pajakClass }}">
                                            {{ $pajakLabel }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                @if ($isAdminAset)
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if (trim((string) $k->pemegang) !== '')
                                            <a href="{{ route('kendaraan.sppkd-aktif.download', $k->id_kendaraan) }}"
                                               title="Unduh SPPKD {{ $k->namaTampil() ?? 'Kendaraan' }}"
                                               class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                                SPPKD
                                            </a>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdminAset ? 6 : 5 }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada kendaraan yang dipegang pegawai ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    @if ($canManage ?? false)
        {{-- Modal Pasang Aset ke Pegawai.
             PENEMPATAN AWAL: memasang master aset yang sudah ada.
             Tidak membuat record `aset` baru. Ruangan ditentukan server dari
             ruang kerja pegawai sehingga tidak ada input ruangan di sini. --}}
        <div id="pasang-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="fixed inset-0" data-pasang-close></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Pasang Aset</h3>
                        <p class="mt-0.5 text-sm text-slate-500">Pilih master aset yang sudah ada untuk {{ $pegawai->nama_pegawai }}.</p>
                    </div>
                    <button type="button" data-pasang-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form id="pasang-form" method="POST" autocomplete="off">
                    @csrf
                    <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-6">
                        <div class="space-y-1.5">
                            <label for="field-aset-select" class="block text-sm font-medium text-slate-700">Master Aset <span class="text-red-500">*</span></label>
                            <select id="field-aset-select" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <option value="">-- Pilih Master Aset --</option>
                                @foreach ($asetPasang as $kandidat)
                                    <option value="{{ $kandidat->id_aset }}">{{ $kandidat->barang?->nama_barang ?? '-' }} — {{ $kandidat->nomor_kartu_barang }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_aset"></p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                            <p class="font-semibold text-slate-700">Ruangan mengikuti ruang kerja {{ $pegawai->nama_pegawai }}.</p>
                            <p class="mt-1">Ruang kerja: <span class="font-semibold text-slate-800">{{ $pegawai->ruangan?->nama_ruangan ?? 'Pegawai belum punya ruangan' }}</span></p>
                            <p class="mt-1">Ruangan tidak dapat dipilih di sini — pemegangnya yang menentukan lokasi aset.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                        <button type="button" data-pasang-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                        <button type="submit" id="btn-pasang-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                            Pasang
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Konfirmasi Hapus --}}
        <div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="fixed inset-0" data-delete-close></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
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

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const isAdminAset = @json($isAdminAset);

        // PENTING: URL dasar aset dipakai oleh aksi "Usulkan Hapus" (DELETE).
        const asetUrl = @json(route('aset-barang.aset.destroy', ['aset' => '__ID__']));
        const pasangUrlTemplate = @json(route('aset-barang.aset.pasang.pegawai', ['pegawai' => $pegawai->id_pegawai, 'aset' => '__ID__']));

        const deleteModal = document.getElementById('delete-modal');

        function openDeleteModal(id, name) {
            if (!deleteModal) return;
            document.getElementById('delete-name').textContent = name;
            deleteModal.setAttribute('data-current-id', id);
            deleteModal.classList.remove('hidden');
            deleteModal.classList.add('flex');
        }
        function closeDeleteModal() {
            if (!deleteModal) return;
            deleteModal.classList.add('hidden');
            deleteModal.classList.remove('flex');
        }

        // ---- Pasang Aset ke Pegawai (penempatan awal, tanpa membuat master) ----
        const pasangModal = document.getElementById('pasang-modal');
        const pasangForm = document.getElementById('pasang-form');
        const btnPasang = document.getElementById('btn-pasang');

        function clearPasangErrors() {
            if (!pasangForm) return;
            pasangForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }
        function showPasangErrors(errors) {
            if (!pasangForm) return;
            pasangForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors || {}).forEach(field => {
                const err = pasangForm.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }
        function openPasangModal() {
            if (!pasangModal) return;
            pasangModal.classList.remove('hidden');
            pasangModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            clearPasangErrors();
            const select = document.getElementById('field-aset-select');
            if (select) select.value = '';
        }
        function closePasangModal() {
            if (!pasangModal) return;
            pasangModal.classList.add('hidden');
            pasangModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            clearPasangErrors();
        }

        if (btnPasang && pasangModal) {
            btnPasang.addEventListener('click', openPasangModal);
            pasangModal.querySelectorAll('[data-pasang-close]').forEach(el => el.addEventListener('click', closePasangModal));
        }

        if (pasangForm) {
            pasangForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-pasang-submit');
                const original = submit.textContent;
                const idAset = document.getElementById('field-aset-select').value;

                if (!idAset) {
                    showPasangErrors({ id_aset: ['Pilih master aset yang akan dipasang.'] });
                    return;
                }

                submit.textContent = 'Memasang...';
                submit.disabled = true;
                try {
                    const res = await fetch(pasangUrlTemplate.replace('__ID__', idAset), {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    });

                    if (res.status === 422) {
                        const data = await res.json().catch(() => ({}));
                        showPasangErrors(data.errors || {});
                        submit.textContent = original;
                        submit.disabled = false;
                        return;
                    }

                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        window.location.reload();
                        if (data.warning) {
                            setTimeout(() => alert(data.warning), 100);
                        }
                        return;
                    }

                    alert(data.message || 'Gagal memasang aset.');
                    submit.textContent = original;
                    submit.disabled = false;
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    submit.textContent = original;
                    submit.disabled = false;
                }
            });
        }

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
                    const res = await fetch(asetUrl.replace('__ID__', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
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
    </script>
    @endpush
@endsection