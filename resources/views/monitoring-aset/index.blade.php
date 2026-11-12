@extends('layouts.app')

@section('title', 'Monitoring Aset')

@section('page-title', 'Monitoring Aset')

@section('content')
<div class="space-y-6">

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- 3 Metrics Card (Grid 3 Kolom) --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        {{-- Card 1 --}}
        <x-card class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Nilai Buku Aset</p>
                <h3 class="mt-1 text-xl font-black text-emerald-600">
                    Rp {{ number_format($totalNilaiBuku ?? 0, 0, ',', '.') }}
                </h3>
                <p class="mt-1 text-[11px] text-slate-400">Akumulasi aset belum dihapus</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </x-card>

        {{-- Card 2 --}}
        <x-card class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Aset Nilai Buku Habis</p>
                <h3 class="mt-1 text-xl font-black text-rose-600">
                    {{ $asetHabisCount ?? 0 }} <span class="text-xs font-semibold text-slate-500">Aset</span>
                </h3>
                <p class="mt-1 text-[11px] text-slate-400">Nilai ekonomis habis (Rp 0)</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </x-card>

        {{-- Card 3 --}}
        <x-card class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Masa Pakai Kritis</p>
                <h3 class="mt-1 text-xl font-black text-amber-500">
                    {{ $asetKritisCount ?? 0 }} <span class="text-xs font-semibold text-slate-500">Aset</span>
                </h3>
                <p class="mt-1 text-[11px] text-slate-400">Sisa masa manfaat 1-30 hari</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </x-card>
    </div>

    {{-- Main Table Container --}}
    <x-card :padding="false">
        {{-- FILTER + AKSI --}}
        <x-action-bar>
            <form action="{{ route('monitoring-aset.index') }}" method="GET" class="m-0 flex min-w-0 flex-1 flex-wrap items-center gap-2">
                {{-- Search Input --}}
                <div class="relative w-64">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / kode aset..."
                           class="w-full rounded-2xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-disbun-600 focus:outline-hidden focus:ring-1 focus:ring-disbun-400/40">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                {{-- Filter Periode Sensus --}}
                <select name="periode" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-disbun-600 focus:outline-hidden focus:ring-1 focus:ring-disbun-400/40">
                    @foreach ($daftarPeriode ?? [] as $p)
                        <option value="{{ $p }}" {{ ($periodeDipilih ?? '') === $p ? 'selected' : '' }}>
                            Periode {{ $p }} {{ $p === ($periodeAktif ?? '') ? '(Aktif)' : '' }}
                        </option>
                    @endforeach
                </select>

                {{-- Filter Tahun --}}
                <select name="tahun" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-disbun-600 focus:outline-hidden focus:ring-1 focus:ring-disbun-400/40">
                    <option value="">Semua Tahun</option>
                    @foreach ($tahunList ?? [] as $th)
                        <option value="{{ $th }}" {{ request('tahun') == $th ? 'selected' : '' }}>{{ $th }}</option>
                    @endforeach
                </select>

                {{-- Filter Jenis Aset --}}
                <select name="jenis_aset" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-disbun-600 focus:outline-hidden focus:ring-1 focus:ring-disbun-400/40">
                    <option value="">Semua Aset</option>
                    <option value="barang" {{ request('jenis_aset') == 'barang' ? 'selected' : '' }}>Barang</option>
                    <option value="kendaraan" {{ request('jenis_aset') == 'kendaraan' ? 'selected' : '' }}>Kendaraan</option>
                </select>

                {{-- Filter Status --}}
                <select name="status" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-disbun-600 focus:outline-hidden focus:ring-1 focus:ring-disbun-400/40">
                    <option value="">Semua Status</option>
                    <option value="habis" {{ request('status') == 'habis' ? 'selected' : '' }}>Masa Pakai Habis</option>
                    <option value="kritis" {{ request('status') == 'kritis' ? 'selected' : '' }}>Kritis (1-30 Hari)</option>
                    <option value="normal" {{ request('status') == 'normal' ? 'selected' : '' }}>Normal (&gt; 30 Hari)</option>
                </select>

                <button type="submit" class="rounded-2xl bg-disbun-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    Filter
                </button>
                @if (request()->anyFilled(['search', 'tahun', 'status', 'jenis_aset']))
                    <a href="{{ route('monitoring-aset.index') }}" class="rounded-2xl border border-disbun-card-border bg-white px-3.5 py-2 text-xs font-bold text-slate-500 shadow-sm transition hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </form>

            <x-slot name="actions">
                <button type="button"
                        onclick="openExportModal()"
                        class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800 focus:outline-none focus:ring-2 focus:ring-disbun-600 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Export Data</span>
                </button>
            </x-slot>
        </x-action-bar>

        {{-- Tabel Monitoring --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-3">#</th>
                        <th class="py-3 px-3">Aset & Kode</th>
                        <th class="py-3 px-3">Lokasi / Pemegang</th>
                        <th class="py-3 px-3">Nilai Perolehan</th>
                        <th class="py-3 px-3">Nilai Buku</th>
                        <th class="py-3 px-3">Tgl Pengadaan</th>
                        <th class="py-3 px-3">Masa Pakai</th>
                        <th class="py-3 px-3">Hasil Sensus</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($asetList as $index => $item)
                        @php
                            $masaBadge = match ($item->masa_status) {
                                'habis' => 'bg-rose-50 text-rose-700',
                                'kritis' => 'bg-amber-50 text-amber-700',
                                'normal' => 'bg-disbun-50 text-disbun-700',
                                default => 'bg-slate-100 text-slate-400',
                            };
                            $masaText = match ($item->masa_status) {
                                'habis' => 'Habis',
                                'kritis', 'normal' => $item->sisa_hari . ' Hari',
                                default => '—',
                            };
                            $sensusBadge = match ($item->hasil_sensus ?? 'Belum Disensus') {
                                'Baik' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'Rusak Ringan' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'Rusak Berat' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-500 border-slate-200',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="py-3 px-3 font-mono text-slate-400">{{ ($asetList->firstItem() ?? 0) + $index }}</td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-bold text-slate-800">{{ $item->nama_barang ?? '-' }}</span>
                                    @if ($item->is_kendaraan && !empty($item->nomor_plat))
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-700 border border-slate-200" title="Nomor Polisi">
                                            {{ $item->nomor_plat }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    <span class="font-mono text-[11px] text-slate-400">{{ $item->nomor_kartu ?? '-' }}</span>
                                    @php
                                        $penempatanBadge = match ($item->status_penempatan ?? 'Belum Ditempatkan') {
                                            'Sudah Difinalisasi' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'Belum Difinalisasi' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            default => 'bg-slate-100 text-slate-500 border-slate-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[9px] font-semibold {{ $penempatanBadge }}">
                                        {{ $item->status_penempatan ?? 'Belum Ditempatkan' }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                <span class="font-medium {{ !empty($item->lokasi) ? 'text-slate-700' : 'italic text-slate-400' }}">
                                    {{ $item->lokasi ?? 'Lokasi Belum Ditentukan' }}
                                </span>
                                <span class="block text-[11px] text-slate-400">
                                    {{ $item->pemegang ? ((!empty($item->lokasi) ? 'Pemegang: ' : '') . $item->pemegang) : 'Tanpa pemegang' }}
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600">
                                @if ($item->punya_nilai)
                                    Rp {{ number_format($item->nilai_perolehan ?? 0, 0, ',', '.') }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold {{ $item->punya_nilai && $item->nilai_buku <= 0 ? 'text-rose-600' : 'text-slate-700' }}">
                                @if ($item->punya_nilai)
                                    Rp {{ number_format($item->nilai_buku ?? 0, 0, ',', '.') }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-slate-500">
                                {{ $item->tanggal_pengadaan ? \Carbon\Carbon::parse($item->tanggal_pengadaan)->format('d M Y') : '-' }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $masaBadge }}">{{ $masaText }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $sensusBadge }}">
                                    {{ $item->hasil_sensus ?? 'Belum Disensus' }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button"
                                            onclick="openModalSensus(this)"
                                            data-action="{{ route('monitoring-aset.sensus.update', $item->id_aset) }}"
                                            data-id-aset="{{ $item->id_aset }}"
                                            data-nama-barang="{{ $item->nama_barang }}"
                                            data-nomor-kartu="{{ $item->nomor_kartu ?? '-' }}{{ $item->is_kendaraan && !empty($item->nomor_plat) ? ' (' . $item->nomor_plat . ')' : '' }}"
                                            data-pemegang="{{ $item->pemegang ?: '-' }}"
                                            data-lokasi="{{ $item->lokasi ?: 'Lokasi Belum Ditentukan' }}"
                                            data-tanggal-pengadaan="{{ $item->tanggal_pengadaan ? \Carbon\Carbon::parse($item->tanggal_pengadaan)->format('d M Y') : '-' }}"
                                            data-periode="{{ $periodeDipilih ?? $item->periode_sensus }}"
                                            data-hasil-sensus="{{ $item->sudah_disensus ? $item->hasil_sensus : '' }}"
                                            data-tanggal-sensus="{{ $item->tanggal_sensus ?? date('Y-m-d') }}"
                                            data-catatan="{{ $item->catatan_sensus ?? '' }}"
                                            data-sudah-disensus="{{ !empty($item->sudah_disensus) ? '1' : '0' }}"
                                            class="inline-flex items-center gap-1 rounded-xl border {{ !empty($item->sudah_disensus) ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100' }} px-2.5 py-1 text-[11px] font-bold transition">
                                        {{ !empty($item->sudah_disensus) ? 'Edit Sensus' : 'Tambah Sensus' }}
                                    </button>
                                    <a href="{{ route('monitoring-aset.sensus.riwayat', $item->id_aset) }}"
                                       class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 transition hover:bg-slate-50"
                                       title="Lihat Riwayat Sensus Seluruh Periode">
                                        Riwayat
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400">Data aset tidak ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $asetList->links() }}
        </div>
    </x-card>
</div>
@endsection

@push('modals')
    {{-- MODAL SENSUS ASET (LANDSCAPE / MELEBAR KE SAMPING) --}}
    <div id="sensus-modal"
         class="fixed inset-0 hidden items-center justify-center p-3 sm:p-5 md:p-6 transition-opacity duration-200"
         style="z-index: 99999;"
         role="dialog"
         aria-modal="true">
        {{-- Fullscreen dark backdrop covering sidebar, header, and entire viewport --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" data-sensus-close></div>

        {{-- Modal Dialog Container --}}
        <div id="sensus-modal-container"
             class="relative w-[calc(100vw-32px)] max-w-[850px] rounded-3xl bg-white shadow-2xl flex flex-col max-h-[90vh] overflow-hidden my-auto border border-slate-100 z-10"
             style="max-width: 850px; width: calc(100vw - 32px); max-height: 90vh;">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 shrink-0 bg-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900" id="sensus-modal-title">Tambah Hasil Sensus</h3>
                        <p class="text-[11px] text-slate-400">Sensus berkala fisik 3 tahunan aset dinas perkebunan.</p>
                    </div>
                </div>
                <button type="button" data-sensus-close class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Form Wrapper --}}
            <form id="sensus-modal-form" method="POST" action="" autocomplete="off" class="flex flex-col flex-1 overflow-hidden m-0">
                @csrf
                <input type="hidden" name="redirect_to" value="monitoring">

                {{-- Modal Body (Scrollable inside if screen height is restricted) --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 sm:px-6 sm:py-5">
                    {{-- Grid 2 Kolom Landscape Desktop (Kiri 35%, Kanan 65%) --}}
                    <div id="sensus-modal-grid"
                         class="sensus-two-col-grid grid grid-cols-1 md:grid-cols-[0.35fr_0.65fr] gap-4 sm:gap-5 items-stretch">

                        {{-- Kolom Kiri: Informasi Aset (35%) --}}
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/90 p-4 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-slate-200/70 pb-2.5 mb-3">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Informasi Aset</span>
                                    </div>
                                    <span id="sensus-m-badge" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-slate-200 text-slate-700">-</span>
                                </div>

                                <div class="space-y-3.5">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Nama Barang</span>
                                        <p id="sensus-m-nama" class="text-xs sm:text-sm font-bold text-slate-900 leading-snug break-words mt-0.5">-</p>
                                    </div>

                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Kode / Nomor KIB</span>
                                        <p id="sensus-m-kode" class="font-mono text-[11px] sm:text-xs font-semibold text-slate-700 break-all mt-0.5">-</p>
                                    </div>

                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Pemegang Saat Ini</span>
                                        <p id="sensus-m-pemegang" class="text-xs font-medium text-slate-700 break-words mt-0.5">-</p>
                                    </div>

                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Lokasi / Ruangan</span>
                                        <p id="sensus-m-lokasi" class="text-xs font-medium text-slate-700 break-words mt-0.5">-</p>
                                    </div>

                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Tanggal Pengadaan</span>
                                        <p id="sensus-m-tgl-pengadaan" class="text-xs font-medium text-slate-700 mt-0.5">-</p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2.5 border-t border-slate-200/70 mt-3">
                                <p class="text-[10px] text-slate-400 leading-tight">Data aset diambil otomatis dari sistem inventaris.</p>
                            </div>
                        </div>

                        {{-- Kolom Kanan: Formulir Hasil Sensus (65%) --}}
                        <div class="flex flex-col justify-between space-y-3.5">
                            {{-- 1. Judul Formulir --}}
                            <div class="border-b border-slate-100 pb-2">
                                <h4 class="text-sm font-bold text-slate-900">Formulir Hasil Sensus</h4>
                                <p class="text-[11px] text-slate-400 mt-0.5">Catat kondisi fisik aset hasil sensus berkala 3 tahunan.</p>
                            </div>

                            {{-- 2 & 3. Periode Sensus & Tanggal Pelaksanaan Sensus --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="sensus-m-periode" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                        Periode Sensus <span class="text-rose-500">*</span>
                                    </label>
                                    <select id="sensus-m-periode" name="periode" required
                                            class="block w-full h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs text-slate-900 shadow-sm focus:border-disbun-600 focus:outline-none focus:ring-1 focus:ring-disbun-400">
                                        @foreach ($daftarPeriode as $p)
                                            <option value="{{ $p }}" {{ $p === $periodeDipilih ? 'selected' : '' }}>
                                                Periode {{ $p }} {{ $p === $periodeAktif ? '(Aktif)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="sensus-m-tgl-sensus" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                        Tanggal Pelaksanaan Sensus <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="date" id="sensus-m-tgl-sensus" name="tanggal_sensus" required
                                           value="{{ date('Y-m-d') }}"
                                           class="block w-full h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs text-slate-900 shadow-sm focus:border-disbun-600 focus:outline-none focus:ring-1 focus:ring-disbun-400">
                                </div>
                            </div>

                            {{-- 4. Pilihan Hasil Sensus (3 Kartu Kondisi Horizontal) --}}
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                    Pilihan Hasil Sensus <span class="text-rose-500">*</span>
                                </label>
                                <div class="grid grid-cols-3 gap-2 sm:gap-2.5">
                                    {{-- Baik --}}
                                    <label id="lbl-sensus-baik" class="sensus-radio-card relative flex flex-col items-center justify-center cursor-pointer rounded-xl border border-slate-200 bg-white p-2 text-center transition hover:bg-slate-50 select-none min-h-[66px]">
                                        <input type="radio" name="hasil_sensus" value="Baik" required class="sr-only" onchange="updateSensusRadioStyle('Baik')">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 mb-1 shrink-0"></span>
                                        <span class="text-xs font-bold text-slate-800">Baik</span>
                                        <span class="text-[10px] text-slate-400 leading-tight mt-0.5">Berfungsi normal</span>
                                    </label>

                                    {{-- Rusak Ringan --}}
                                    <label id="lbl-sensus-rusak-ringan" class="sensus-radio-card relative flex flex-col items-center justify-center cursor-pointer rounded-xl border border-slate-200 bg-white p-2 text-center transition hover:bg-slate-50 select-none min-h-[66px]">
                                        <input type="radio" name="hasil_sensus" value="Rusak Ringan" required class="sr-only" onchange="updateSensusRadioStyle('Rusak Ringan')">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 mb-1 shrink-0"></span>
                                        <span class="text-xs font-bold text-slate-800">Rusak Ringan</span>
                                        <span class="text-[10px] text-slate-400 leading-tight mt-0.5">Perbaikan minor</span>
                                    </label>

                                    {{-- Rusak Berat --}}
                                    <label id="lbl-sensus-rusak-berat" class="sensus-radio-card relative flex flex-col items-center justify-center cursor-pointer rounded-xl border border-slate-200 bg-white p-2 text-center transition hover:bg-slate-50 select-none min-h-[66px]">
                                        <input type="radio" name="hasil_sensus" value="Rusak Berat" required class="sr-only" onchange="updateSensusRadioStyle('Rusak Berat')">
                                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 mb-1 shrink-0"></span>
                                        <span class="text-xs font-bold text-slate-800">Rusak Berat</span>
                                        <span class="text-[10px] text-slate-400 leading-tight mt-0.5">Tidak layak pakai</span>
                                    </label>
                                </div>
                            </div>

                            {{-- 5. Catatan Temuan Fisik (opsional) --}}
                            <div>
                                <label for="sensus-m-catatan" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                    Catatan Temuan Fisik (Opsional)
                                </label>
                                <textarea id="sensus-m-catatan" name="catatan" rows="2"
                                          class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 shadow-sm focus:border-disbun-600 focus:outline-none focus:ring-1 focus:ring-disbun-400 placeholder:text-slate-400"
                                          placeholder="Tuliskan nomor seri, kelengkapan komponen, atau temuan fisik lainnya..."></textarea>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Modal Footer: Tombol Batal dan Simpan sejajar di sebelah kanan --}}
                <div class="flex items-center justify-end gap-3 border-t border-slate-200/80 bg-slate-50/80 px-6 py-3.5 shrink-0">
                    <button type="button" data-sensus-close
                            class="inline-flex items-center justify-center h-9 px-4 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-1.5 h-9 px-5 rounded-xl bg-disbun-700 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span id="sensus-m-submit-text">Simpan Hasil Sensus</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL PILIHAN EXPORT DATA (SATU PINTU MASUK) --}}
    <div id="export-modal"
         class="fixed inset-0 hidden items-center justify-center p-3 sm:p-5 md:p-6 transition-opacity duration-200"
         style="z-index: 99999;"
         role="dialog"
         aria-modal="true">
        {{-- Fullscreen dark backdrop --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" data-export-close></div>

        {{-- Modal Dialog Container --}}
        <div id="export-modal-container"
             class="relative w-[calc(100vw-32px)] max-w-lg rounded-3xl bg-white shadow-2xl flex flex-col max-h-[90vh] overflow-hidden my-auto border border-slate-100 z-10">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 shrink-0 bg-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Export Data Monitoring Aset</h3>
                        <p class="text-xs text-slate-400">Pilih jenis laporan untuk diunduh dalam format Excel (.xlsx)</p>
                    </div>
                </div>
                <button type="button" data-export-close class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Form Export --}}
            <form action="{{ route('monitoring-aset.export') }}" method="GET" class="m-0 flex flex-col flex-1 overflow-hidden">
                {{-- Teruskan seluruh filter aktif saat ini --}}
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="tahun" value="{{ request('tahun') }}">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <input type="hidden" name="jenis_aset" value="{{ request('jenis_aset') }}">
                <input type="hidden" name="penempatan" value="{{ request('penempatan') }}">
                <input type="hidden" name="periode" value="{{ $periodeDipilih }}">

                <div class="p-6 overflow-y-auto space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                            Pilih Format & Jenis Rekapitulasi:
                        </label>
                        <div class="space-y-2.5">
                            {{-- 1. Rekap Seluruh Aset --}}
                            <label class="export-radio-card flex items-start gap-3 p-3.5 rounded-2xl border-2 border-emerald-500 bg-emerald-50/50 cursor-pointer transition hover:bg-emerald-50 select-none">
                                <input type="radio" name="jenis_laporan" value="seluruh" checked class="mt-1 text-disbun-700 focus:ring-disbun-600">
                                <div class="flex-1">
                                    <span class="block text-xs font-bold text-slate-900">1. Rekap Seluruh Aset</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        Memuat seluruh aset dinas yang aktif (berpemegang, melekat ruangan, maupun tanpa penempatan) dalam 1 tabel utuh.
                                    </span>
                                </div>
                            </label>

                            {{-- 2. Rekap Aset per SKPD Pemegang --}}
                            <label class="export-radio-card flex items-start gap-3 p-3.5 rounded-2xl border-2 border-slate-200 bg-white cursor-pointer transition hover:bg-slate-50 select-none">
                                <input type="radio" name="jenis_laporan" value="per_skpd" class="mt-1 text-disbun-700 focus:ring-disbun-600">
                                <div class="flex-1">
                                    <span class="block text-xs font-bold text-slate-900">2. Rekap Aset per SKPD Pemegang</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        Hanya memuat aset berpemegang perorangan tunggal, dikelompokkan berdasarkan SKPD/unit kerja pegawai pemegang.
                                    </span>
                                </div>
                            </label>

                            {{-- 3. Rekap Aset per Pemegang --}}
                            <label class="export-radio-card flex items-start gap-3 p-3.5 rounded-2xl border-2 border-slate-200 bg-white cursor-pointer transition hover:bg-slate-50 select-none">
                                <input type="radio" name="jenis_laporan" value="per_pemegang" class="mt-1 text-disbun-700 focus:ring-disbun-600">
                                <div class="flex-1">
                                    <span class="block text-xs font-bold text-slate-900">3. Rekap Aset per Pemegang</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        Hanya memuat aset berpemegang perorangan tunggal, dikelompokkan berdasarkan nama pegawai pemegang aset.
                                    </span>
                                </div>
                            </label>

                            {{-- 4. Rekap Aset per Ruangan --}}
                            <label class="export-radio-card flex items-start gap-3 p-3.5 rounded-2xl border-2 border-slate-200 bg-white cursor-pointer transition hover:bg-slate-50 select-none">
                                <input type="radio" name="jenis_laporan" value="per_ruangan" class="mt-1 text-disbun-700 focus:ring-disbun-600">
                                <div class="flex-1">
                                    <span class="block text-xs font-bold text-slate-900">4. Rekap Aset per Ruangan</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        Hanya memuat aset yang memiliki penempatan ruangan aktif, dikelompokkan berdasarkan nama ruangan fisik.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Informasi Filter Aktif --}}
                    <div class="rounded-xl border border-slate-200/80 bg-slate-50 p-3 text-[11px] text-slate-600 flex items-center justify-between">
                        <span class="text-slate-400">Periode Sensus Terpilih:</span>
                        <span class="font-bold text-emerald-700 font-mono">{{ $periodeDipilih }}</span>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50/70 px-6 py-3.5 shrink-0">
                    <button type="button" data-export-close class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span>Unduh Excel</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Fallback Scoped Styles to guarantee 35%:65% desktop two-column layout --}}
    <style>
        @media (min-width: 768px) {
            .sensus-two-col-grid {
                display: grid !important;
                grid-template-columns: 0.35fr 0.65fr !important;
            }
        }
    </style>
@endpush

@push('scripts')
<script>
    const sensusModal = document.getElementById('sensus-modal');
    const sensusForm = document.getElementById('sensus-modal-form');
    let previousScrollY = 0;

    function updateSensusRadioStyle(val) {
        const lblBaik = document.getElementById('lbl-sensus-baik');
        const lblRingan = document.getElementById('lbl-sensus-rusak-ringan');
        const lblBerat = document.getElementById('lbl-sensus-rusak-berat');

        [lblBaik, lblRingan, lblBerat].forEach(el => {
            if (el) {
                el.classList.remove('border-emerald-500', 'bg-emerald-50/70', 'ring-2', 'ring-emerald-500/20', 'text-emerald-900',
                                     'border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20', 'text-amber-900',
                                     'border-rose-500', 'bg-rose-50/70', 'ring-2', 'ring-rose-500/20', 'text-rose-900');
                el.classList.add('border-slate-200', 'bg-white');
            }
        });

        if (val === 'Baik' && lblBaik) {
            lblBaik.classList.remove('border-slate-200', 'bg-white');
            lblBaik.classList.add('border-emerald-500', 'bg-emerald-50/70', 'ring-2', 'ring-emerald-500/20', 'text-emerald-900');
        } else if (val === 'Rusak Ringan' && lblRingan) {
            lblRingan.classList.remove('border-slate-200', 'bg-white');
            lblRingan.classList.add('border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20', 'text-amber-900');
        } else if (val === 'Rusak Berat' && lblBerat) {
            lblBerat.classList.remove('border-slate-200', 'bg-white');
            lblBerat.classList.add('border-rose-500', 'bg-rose-50/70', 'ring-2', 'ring-rose-500/20', 'text-rose-900');
        }
    }

    function openModalSensus(btn) {
        if (!sensusModal || !sensusForm) return;

        previousScrollY = window.scrollY || window.pageYOffset || 0;

        const action = btn.dataset.action;
        const nama = btn.dataset.namaBarang ?? '-';
        const kode = btn.dataset.nomorKartu ?? '-';
        const pemegang = btn.dataset.pemegang ?? '-';
        const lokasi = btn.dataset.lokasi ?? '-';
        const tglPengadaan = btn.dataset.tanggalPengadaan ?? '-';
        const periode = btn.dataset.periode ?? '';
        const hasil = btn.dataset.hasilSensus ?? '';
        const tglSensus = btn.dataset.tanggalSensus || '{{ date('Y-m-d') }}';
        const catatan = btn.dataset.catatan ?? '';
        const isEdit = btn.dataset.sudahDisensus === '1';

        sensusForm.action = action;

        document.getElementById('sensus-modal-title').textContent = isEdit ? 'Edit Hasil Sensus' : 'Tambah Hasil Sensus';
        document.getElementById('sensus-m-submit-text').textContent = isEdit ? 'Perbarui Sensus' : 'Simpan Hasil Sensus';
        document.getElementById('sensus-m-badge').textContent = isEdit ? 'Sudah Disensus' : 'Belum Disensus';
        document.getElementById('sensus-m-badge').className = isEdit
            ? 'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800'
            : 'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800';

        document.getElementById('sensus-m-nama').textContent = nama || '-';
        document.getElementById('sensus-m-kode').textContent = kode || '-';
        document.getElementById('sensus-m-pemegang').textContent = pemegang || '-';
        document.getElementById('sensus-m-lokasi').textContent = lokasi || '-';
        document.getElementById('sensus-m-tgl-pengadaan').textContent = tglPengadaan || '-';

        const periodeSelect = document.getElementById('sensus-m-periode');
        if (periodeSelect && periode) {
            periodeSelect.value = periode;
        }

        const tglInput = document.getElementById('sensus-m-tgl-sensus');
        if (tglInput) {
            tglInput.value = tglSensus;
        }

        const catatanInput = document.getElementById('sensus-m-catatan');
        if (catatanInput) {
            catatanInput.value = catatan;
        }

        const radios = sensusForm.querySelectorAll('input[name="hasil_sensus"]');
        radios.forEach(r => {
            r.checked = (r.value === hasil);
        });
        updateSensusRadioStyle(hasil);

        sensusModal.classList.remove('hidden');
        sensusModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModalSensus() {
        if (!sensusModal) return;
        sensusModal.classList.add('hidden');
        sensusModal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        window.scrollTo(0, previousScrollY);
    }

    if (sensusModal) {
        sensusModal.querySelectorAll('[data-sensus-close]').forEach(el => el.addEventListener('click', closeModalSensus));
    }

    {{-- Script Modal Export Data --}}
    const exportModal = document.getElementById('export-modal');

    function openExportModal() {
        if (!exportModal) return;
        exportModal.classList.remove('hidden');
        exportModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeExportModal() {
        if (!exportModal) return;
        exportModal.classList.add('hidden');
        exportModal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    if (exportModal) {
        exportModal.querySelectorAll('[data-export-close]').forEach(el => el.addEventListener('click', closeExportModal));

        const exportRadios = exportModal.querySelectorAll('input[name="jenis_laporan"]');
        exportRadios.forEach(r => {
            r.addEventListener('change', function() {
                exportRadios.forEach(radio => {
                    const card = radio.closest('.export-radio-card');
                    if (radio.checked) {
                        card.classList.remove('border-slate-200', 'bg-white');
                        card.classList.add('border-emerald-500', 'bg-emerald-50/50');
                    } else {
                        card.classList.remove('border-emerald-500', 'bg-emerald-50/50');
                        card.classList.add('border-slate-200', 'bg-white');
                    }
                });
            });
        });

        const exportForm = exportModal.querySelector('form');
        if (exportForm) {
            exportForm.addEventListener('submit', function() {
                setTimeout(closeExportModal, 600);
            });
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (exportModal && !exportModal.classList.contains('hidden')) {
                closeExportModal();
            } else if (sensusModal && !sensusModal.classList.contains('hidden')) {
                closeModalSensus();
            }
        }
    });
</script>
@endpush
