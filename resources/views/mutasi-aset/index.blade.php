@extends('layouts.app')

@section('title', 'Riwayat Mutasi Aset')

@section('page-title', 'Mutasi Aset')

@section('content')
    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        {{-- TABS KATEGORI ASET --}}
        <div class="flex items-center gap-1 border-b border-disbun-card-border px-6 pt-4 pb-3">
            <a href="{{ route('mutasi-aset.index', array_filter(['kategori' => 'barang', 'search' => request('search'), 'jenis_mutasi' => request('jenis_mutasi'), 'tanggal_mulai' => request('tanggal_mulai'), 'tanggal_akhir' => request('tanggal_akhir')])) }}"
               class="rounded-2xl px-4 py-2 text-sm font-bold transition {{ $kategori === 'barang' ? 'bg-disbun-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                Aset Barang
            </a>
            @if (in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT'], true))
                <a href="{{ route('mutasi-aset.index', array_filter(['kategori' => 'kendaraan', 'search' => request('search'), 'jenis_mutasi' => request('jenis_mutasi'), 'tanggal_mulai' => request('tanggal_mulai'), 'tanggal_akhir' => request('tanggal_akhir')])) }}"
                   class="rounded-2xl px-4 py-2 text-sm font-bold transition {{ $kategori === 'kendaraan' ? 'bg-disbun-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                    Kendaraan
                </a>
            @endif
            <span class="ml-auto hidden text-xs text-slate-400 sm:block">{{ $kategori === 'kendaraan' ? 'Riwayat mutasi kendaraan dinas' : 'Riwayat mutasi seluruh aset barang' }}</span>
        </div>

        <x-action-bar>
            <form method="GET" action="{{ route('mutasi-aset.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
                <input type="hidden" name="kategori" value="{{ $kategori }}">

                {{-- 1. Input Pencarian Teks --}}
                <div class="relative flex-1 min-w-[220px] sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="{{ $kategori === 'kendaraan' ? 'Cari kendaraan, pemegang, jenis...' : 'Cari barang, NKB, pegawai, ruangan, keterangan...' }}"
                        autocomplete="off"
                        class="block w-full rounded-xl border border-slate-300 bg-white py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition"
                    >
                </div>

                {{-- 2. Dropdown Filter Jenis Mutasi --}}
                <div class="w-full sm:w-auto">
                    <select name="jenis_mutasi" aria-label="Filter jenis mutasi"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400">
                        <option value="">Semua Jenis Mutasi</option>
                        <option value="Pindah Ruangan" @selected(request('jenis_mutasi') === 'Pindah Ruangan')>Pindah Ruangan</option>
                        <option value="Ganti Pemegang" @selected(request('jenis_mutasi') === 'Ganti Pemegang')>Ganti Pemegang</option>
                    </select>
                </div>

                {{-- 3. Filter Rentang Tanggal Mutasi --}}
                <div class="flex items-center gap-1.5 w-full sm:w-auto">
                    <div class="relative flex-1 sm:w-36">
                        <input
                            type="date"
                            name="tanggal_mulai"
                            value="{{ request('tanggal_mulai') }}"
                            aria-label="Tanggal mulai"
                            title="Tanggal Mulai Mutasi"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm transition focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400"
                        >
                    </div>
                    <span class="text-xs text-slate-400 font-medium">s/d</span>
                    <div class="relative flex-1 sm:w-36">
                        <input
                            type="date"
                            name="tanggal_akhir"
                            value="{{ request('tanggal_akhir') }}"
                            aria-label="Tanggal akhir"
                            title="Tanggal Akhir Mutasi"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm transition focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400"
                        >
                    </div>
                </div>

                {{-- 4. Tombol Aksi (Cari & Reset) --}}
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800 focus:outline-none">
                        Cari
                    </button>

                    @if (request('search') || request('jenis_mutasi') || request('tanggal_mulai') || request('tanggal_akhir'))
                        <a href="{{ route('mutasi-aset.index', $kategori === 'kendaraan' ? ['kategori' => 'kendaraan'] : []) }}"
                           class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </x-action-bar>

        @if ($kategori === 'kendaraan')
            {{-- TABEL MUTASI KENDARAAN --}}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Mutasi</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kendaraan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis Mutasi</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Lama</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Baru</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor Surat</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Diinput Oleh</th>
                            <th scope="col" class="w-32 px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Dokumen BAST</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($mutasiList as $i => $mutasi)
                            @php
                                $detail = $mutasi->details->first();
                                $knd = $detail?->aset?->kendaraan;
                                $platAktif = $knd?->platAktif;
                                $pLamaTeks = $detail?->pegawaiLama?->nama_pegawai
                                    ?? (($mutasi->keterangan && preg_match('/Serah terima dari ([^\.]+)/', $mutasi->keterangan, $m1)) ? $m1[1] : '-');
                                $nomorSurat = '';
                                if ($mutasi->keterangan && preg_match('/Nomor Surat:\s*([^\.]+)/', $mutasi->keterangan, $m2)) {
                                    $nomorSurat = trim($m2[1]);
                                }
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $mutasiList->firstItem() + $i }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $mutasi->tanggal_mutasi?->format('d M Y') ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                                    @if ($knd)
                                        @if (auth()->user()?->hasRole('Admin Aset'))
                                            <a href="{{ route('kendaraan.show', $knd->id_kendaraan) }}" class="text-slate-900 transition hover:text-disbun-700 hover:underline">{{ $knd->namaTampil() }}</a>
                                        @else
                                            <span class="text-slate-900 font-medium">{{ $knd->namaTampil() }}</span>
                                        @endif
                                        @if ($platAktif?->nomor_plat)
                                            <span class="block text-xs font-normal text-slate-400">{{ $platAktif->nomor_plat }}</span>
                                        @endif
                                    @else
                                        <span>-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $mutasi->jenis_mutasi ?? '-' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pLamaTeks }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-emerald-700">{{ $detail?->pegawaiBaru?->nama_pegawai ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $nomorSurat ?: '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $mutasi->userPenginput?->pegawai?->nama_pegawai ?? $mutasi->userPenginput?->username ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    @if ($detail && $mutasi->jenis_mutasi == 'Ganti Pemegang')
                                        @if ($mutasi->bisa_unduh_bast ?? true)
                                            <a href="{{ route('kendaraan.bast.download', $mutasi->id_mutasi) }}"
                                               class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 transition hover:bg-sky-100"
                                               title="Unduh Berkas BAST">
                                                <svg class="h-3.5 w-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <span>Unduh BAST</span>
                                            </a>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-400"
                                                  title="Dokumen BAST memuat aset di luar cakupan kewenangan UPT Anda">
                                                Akses Terbatas
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs italic text-slate-300" title="Pemindahan ruangan tercatat di KIR Ruangan">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada riwayat mutasi kendaraan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            {{-- TABEL MUTASI ASET BARANG --}}
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="w-12 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                            <th scope="col" class="w-32 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Mutasi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Aset Barang</th>
                            <th scope="col" class="w-36 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis Mutasi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Penempatan Lama</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Penempatan Baru</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Keterangan</th>
                            <th scope="col" class="w-24 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th scope="col" class="w-32 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Dokumen BAST</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($mutasiList as $i => $mutasi)
                            @php
                                $visibleDetails = $mutasi->visible_details ?? $mutasi->details;
                                $detail = $visibleDetails->first();
                                $totalAset = $visibleDetails->count();
                                $extraAset = max(0, $totalAset - 1);

                                $namaLokasiSingkat = function ($lokasi) {
                                    if (! $lokasi) return null;
                                    if ($lokasi->jenis_lokasi === 'Dinas') return 'Dinas';
                                    if (stripos($lokasi->nama_lokasi, 'P2BTP') !== false) return 'P2BTP';
                                    if (stripos($lokasi->nama_lokasi, 'PSBP') !== false) return 'PSBP';
                                    return preg_replace('/^(Kantor\s+(UPT\s+)?)|\s+Provinsi.*$/i', '', $lokasi->nama_lokasi) ?: $lokasi->nama_lokasi;
                                };

                                $isPindahRuangan = strcasecmp(trim($mutasi->jenis_mutasi ?? ''), 'Pindah Ruangan') === 0;

                                $lokasiLama = $detail?->ruanganLama?->lokasi ? $namaLokasiSingkat($detail->ruanganLama->lokasi) : null;
                                $lokasiBaru = $detail?->ruanganBaru?->lokasi ? $namaLokasiSingkat($detail->ruanganBaru->lokasi) : null;

                                // Siapkan payload modal multi-detail strictly dari $visibleDetails (Anti-Leakage)
                                $modalData = null;
                                if ($extraAset > 0) {
                                    $modalData = [
                                        'id' => $mutasi->id_mutasi,
                                        'tanggal' => $mutasi->tanggal_mutasi?->format('d M Y') ?? '-',
                                        'jenis' => $mutasi->jenis_mutasi ?? '-',
                                        'is_pindah_ruangan' => $isPindahRuangan,
                                        'keterangan' => $mutasi->keterangan ?? '-',
                                        'items' => $visibleDetails->map(function ($d, $idx) use ($namaLokasiSingkat, $isPindahRuangan) {
                                            $lokLamaItem = $d->ruanganLama?->lokasi ? $namaLokasiSingkat($d->ruanganLama->lokasi) : null;
                                            $lokBaruItem = $d->ruanganBaru?->lokasi ? $namaLokasiSingkat($d->ruanganBaru->lokasi) : null;
                                            return [
                                                'no' => $idx + 1,
                                                'nama_barang' => $d->aset?->barang?->nama_barang ?? ($d->inventaris?->nama_barang ?? ('Aset #' . $d->id_aset)),
                                                'nkb' => $d->aset?->nomor_kartu_barang ?? ($d->inventaris ? $d->inventaris->kode_inventaris : '-'),
                                                'merk' => $d->aset?->merk ?: ($d->inventaris?->merk ?: '-'),
                                                'lama_pegawai' => $d->pegawaiLama?->nama_pegawai ?? '-',
                                                'lama_ruangan' => $d->ruanganLama?->nama_ruangan ?? 'Belum Ditempatkan',
                                                'lama_lokasi' => $lokLamaItem,
                                                'baru_pegawai' => $d->pegawaiBaru?->nama_pegawai ?? '-',
                                                'baru_ruangan' => $d->ruanganBaru?->nama_ruangan ?? '-',
                                                'baru_lokasi' => $lokBaruItem,
                                            ];
                                        })->values()->all(),
                                    ];
                                }
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">{{ $mutasiList->firstItem() + $i }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                    <span class="font-medium text-slate-800">{{ $mutasi->tanggal_mutasi?->format('d M Y') ?? '-' }}</span>
                                    @if ($mutasi->userPenginput)
                                        <span class="block text-xs text-slate-400">Oleh: {{ $mutasi->userPenginput->pegawai?->nama_pegawai ?? $mutasi->userPenginput->username }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($detail?->aset)
                                        <a href="{{ route('aset-barang.aset.detail', $detail->aset->id_aset) }}" class="font-semibold text-slate-900 transition hover:text-disbun-700 hover:underline">
                                            {{ $detail->aset->barang?->nama_barang ?? ('Aset #' . $detail->aset->id_aset) }}
                                        </a>
                                        <span class="block text-xs font-normal text-slate-400">
                                            NKB: {{ $detail->aset->nomor_kartu_barang ?? '-' }}
                                        </span>
                                        @if ($extraAset > 0 && $modalData)
                                            <button type="button"
                                                    onclick="bukaModalMultiAset({{ json_encode($modalData) }})"
                                                    class="mt-1 inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200 transition hover:bg-emerald-100 hover:border-emerald-300">
                                                <svg class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                <span>+{{ $extraAset }} aset lainnya</span>
                                            </button>
                                        @endif
                                    @elseif ($detail?->inventaris)
                                        <span class="font-semibold text-slate-900">{{ $detail->inventaris->nama_barang }}</span>
                                        <span class="block text-xs font-normal text-slate-400">
                                            Kode: {{ $detail->inventaris->kode_inventaris }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200 mt-1">
                                            Inventaris Lokal
                                        </span>
                                        @if ($extraAset > 0 && $modalData)
                                            <button type="button"
                                                    onclick="bukaModalMultiAset({{ json_encode($modalData) }})"
                                                    class="mt-1 inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200 transition hover:bg-emerald-100 hover:border-emerald-300">
                                                <svg class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                <span>+{{ $extraAset }} aset lainnya</span>
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $mutasi->jenis_mutasi ?? '-' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($isPindahRuangan)
                                        @if ($detail?->ruanganLama)
                                            <span class="block font-medium text-slate-800">{{ $detail->ruanganLama->nama_ruangan }}</span>
                                            @if ($lokasiLama)
                                                <span class="block text-xs text-slate-400">{{ $lokasiLama }}</span>
                                            @endif
                                        @else
                                            <span class="block text-xs italic text-slate-400">Belum Ditempatkan</span>
                                        @endif
                                    @else
                                        <span class="block font-semibold text-slate-800">{{ $detail?->pegawaiLama?->nama_pegawai ?? '-' }}</span>
                                        @if ($detail?->ruanganLama)
                                            <span class="block text-xs text-slate-600">{{ $detail->ruanganLama->nama_ruangan }}</span>
                                            @if ($lokasiLama)
                                                <span class="block text-[11px] text-slate-400">{{ $lokasiLama }}</span>
                                            @endif
                                        @else
                                            <span class="block text-xs italic text-slate-400">Belum Ditempatkan</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($isPindahRuangan)
                                        @if ($detail?->ruanganBaru)
                                            <span class="block font-medium text-slate-800">{{ $detail->ruanganBaru->nama_ruangan }}</span>
                                            @if ($lokasiBaru)
                                                <span class="block text-xs text-slate-400">{{ $lokasiBaru }}</span>
                                            @endif
                                        @else
                                            <span class="block text-xs italic text-slate-400">-</span>
                                        @endif
                                    @else
                                        <span class="block font-semibold text-emerald-700">{{ $detail?->pegawaiBaru?->nama_pegawai ?? '-' }}</span>
                                        @if ($detail?->ruanganBaru)
                                            <span class="block text-xs text-slate-600">{{ $detail->ruanganBaru->nama_ruangan }}</span>
                                            @if ($lokasiBaru)
                                                <span class="block text-[11px] text-slate-400">{{ $lokasiBaru }}</span>
                                            @endif
                                        @else
                                            <span class="block text-xs italic text-slate-400">-</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    <div class="max-w-[180px] truncate" title="{{ $mutasi->keterangan }}">
                                        {{ $mutasi->keterangan ?: '-' }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    @if (strtolower(trim($mutasi->status_mutasi ?? '')) === 'selesai')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                            Selesai
                                        </span>
                                    @elseif ($mutasi->status_mutasi)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                            {{ ucfirst($mutasi->status_mutasi) }}
                                        </span>
                                    @else
                                        <span class="text-xs italic text-slate-300">-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    @if ($mutasi->jenis_mutasi == 'Ganti Pemegang')
                                        @if ($mutasi->bisa_unduh_bast ?? true)
                                            <a href="{{ route('mutasi-aset.bast.download', $mutasi->id_mutasi) }}"
                                               class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 transition hover:bg-sky-100"
                                               title="Unduh Berkas BAST">
                                                <svg class="h-3.5 w-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <span>Unduh BAST</span>
                                            </a>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-400"
                                                  title="Dokumen BAST memuat aset di luar cakupan kewenangan UPT Anda">
                                                Akses Terbatas
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs italic text-slate-300" title="Pemindahan ruangan tercatat di KIR Ruangan">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada riwayat mutasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <div class="px-6 py-4">
            {{ $mutasiList->links() }}
        </div>
    </x-card>

    {{-- MODAL DAFTAR DETAIL ASET MULTI-DETAIL --}}
    <div id="modal-multi-detail" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" onclick="tutupModalMultiAset()"></div>
        <div class="relative w-full max-w-4xl rounded-2xl bg-white shadow-2xl overflow-hidden my-8">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4 bg-slate-50">
                <div>
                    <h3 class="text-base font-bold text-slate-900" id="modal-md-title">Daftar Aset dalam Transaksi Mutasi</h3>
                    <p class="text-xs text-slate-500" id="modal-md-sub">-</p>
                </div>
                <button type="button" onclick="tutupModalMultiAset()" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="max-h-96 overflow-y-auto rounded-xl border border-slate-200">
                    <table class="w-full divide-y divide-slate-200 text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="w-10 px-4 py-3">#</th>
                                <th class="px-4 py-3">Nama Barang</th>
                                <th class="w-28 px-4 py-3">NKB / Kode</th>
                                <th class="w-24 px-4 py-3">Merek</th>
                                <th class="px-4 py-3">Penempatan Lama</th>
                                <th class="px-4 py-3">Penempatan Baru</th>
                            </tr>
                        </thead>
                        <tbody id="modal-md-body" class="divide-y divide-slate-100 bg-white">
                            {{-- Diisi secara dinamis via JavaScript --}}
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" onclick="tutupModalMultiAset()" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function bukaModalMultiAset(data) {
        const modal = document.getElementById('modal-multi-detail');
        if (!modal) return;

        document.getElementById('modal-md-title').textContent = 'Daftar Aset Mutasi #' + data.id + ' (' + data.jenis + ')';
        document.getElementById('modal-md-sub').textContent = 'Tanggal: ' + data.tanggal + (data.keterangan ? ' • Ket: ' + data.keterangan : '');

        const tbody = document.getElementById('modal-md-body');
        tbody.innerHTML = '';

        (data.items || []).forEach(function(item) {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50 transition';

            let lamaHtml = '';
            let baruHtml = '';

            if (data.is_pindah_ruangan) {
                // Pindah Ruangan: hanya ruangan dan lokasi, tanpa pemegang
                lamaHtml = '<span class="block font-medium text-slate-800">' + (item.lama_ruangan || 'Belum Ditempatkan') + '</span>'
                    + (item.lama_lokasi ? '<span class="block text-slate-400 text-[11px]">' + item.lama_lokasi + '</span>' : '');
                baruHtml = '<span class="block font-medium text-slate-800">' + (item.baru_ruangan || '-') + '</span>'
                    + (item.baru_lokasi ? '<span class="block text-slate-400 text-[11px]">' + item.baru_lokasi + '</span>' : '');
            } else {
                // Ganti Pemegang: pegawai (utama), ruangan (pendukung), lokasi (subteks)
                lamaHtml = '<span class="block font-semibold text-slate-800">' + (item.lama_pegawai || '-') + '</span>'
                    + '<span class="block text-slate-600 text-xs">' + (item.lama_ruangan || 'Belum Ditempatkan') + '</span>'
                    + (item.lama_lokasi ? '<span class="block text-slate-400 text-[11px]">' + item.lama_lokasi + '</span>' : '');
                baruHtml = '<span class="block font-semibold text-emerald-700">' + (item.baru_pegawai || '-') + '</span>'
                    + '<span class="block text-slate-600 text-xs">' + (item.baru_ruangan || '-') + '</span>'
                    + (item.baru_lokasi ? '<span class="block text-slate-400 text-[11px]">' + item.baru_lokasi + '</span>' : '');
            }

            tr.innerHTML = '<td class="px-4 py-2.5 text-slate-500">' + item.no + '</td>'
                + '<td class="px-4 py-2.5 font-semibold text-slate-900">' + item.nama_barang + '</td>'
                + '<td class="px-4 py-2.5 text-slate-600 font-mono text-[11px]">' + item.nkb + '</td>'
                + '<td class="px-4 py-2.5 text-slate-600">' + item.merk + '</td>'
                + '<td class="px-4 py-2.5">' + lamaHtml + '</td>'
                + '<td class="px-4 py-2.5">' + baruHtml + '</td>';
            tbody.appendChild(tr);
        });

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function tutupModalMultiAset() {
        const modal = document.getElementById('modal-multi-detail');
        if (!modal) return;

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }
</script>
@endpush
