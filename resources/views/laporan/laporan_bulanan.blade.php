@extends('layouts.app')

@section('title', 'Laporan Bulanan - INVENSBUN')
@section('page-title', 'Laporan Bulanan')
@section('breadcrumb')
    Dokumen &rsaquo; Laporan Bulanan
@endsection

@section('content')
<div class="space-y-6">

    <x-page-header title="Laporan Bulanan Aset" subtitle="Rekapitulasi aset per pegawai, per ruangan, dan mutasi bulanan">
        <x-slot name="actions">
            <form method="GET" action="{{ route('laporan.bulanan') }}" class="flex items-center gap-2">
                <input type="month" name="bulan" value="{{ $bulan }}"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <x-button type="submit" variant="primary" size="sm">Tampilkan</x-button>
            </form>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card>
            <div class="p-4">
                <p class="text-xs font-medium text-slate-400 uppercase">Total Aset</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ $totalAset }}</p>
            </div>
        </x-card>
        <x-card>
            <div class="p-4">
                <p class="text-xs font-medium text-slate-400 uppercase">Aset di Pegawai</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $totalAsetPegawai }}</p>
            </div>
        </x-card>
        <x-card>
            <div class="p-4">
                <p class="text-xs font-medium text-slate-400 uppercase">Aset di Ruangan</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $totalAsetRuangan }}</p>
            </div>
        </x-card>
        <x-card>
            <div class="p-4">
                <p class="text-xs font-medium text-slate-400 uppercase">Mutasi Bulan Ini</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $totalMutasi }}</p>
            </div>
        </x-card>
    </div>

    {{-- Aset per Pegawai --}}
    <x-card :padding="false">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-800">Aset per Pegawai</h2>
            <p class="text-xs text-slate-400 mt-0.5">Daftar aset yang sedang dipegang oleh masing-masing pegawai</p>
        </div>
        <div class="overflow-x-auto">
            @if ($asetPerPegawai->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data pemegang aset.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/60">
                            <th class="px-5 py-3 text-left font-medium text-slate-500 w-10">#</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Nama Pegawai</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">NIP</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">SKPD</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Nama Aset</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Kode Barang</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Kategori</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Kondisi</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Sejak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $no = 1; @endphp
                        @foreach ($asetPerPegawai as $namaPegawai => $items)
                            @foreach ($items as $item)
                                <tr class="border-b border-slate-50 hover:bg-slate-50/50">
                                    <td class="px-5 py-3 text-slate-400">{{ $no++ }}</td>
                                    <td class="px-5 py-3 font-medium text-slate-700">{{ $namaPegawai }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->pegawai->nip ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->pegawai->skpd->nama_skpd ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $item->aset->barang->nama_barang ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->aset->barang->kode_barang ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->aset->barang->kategori->nama_kategori ?? '-' }}</td>
                                    <td class="px-5 py-3">
                                        @php
                                            $kondisi = $item->aset->kondisi ?? '-';
                                            $color = match($kondisi) {
                                                'Baik' => 'bg-emerald-50 text-emerald-700',
                                                'Rusak Ringan' => 'bg-amber-50 text-amber-700',
                                                'Rusak Berat' => 'bg-red-50 text-red-700',
                                                default => 'bg-slate-50 text-slate-500',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">{{ $kondisi }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->tanggal_mulai?->format('d/m/Y') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </x-card>

    {{-- Aset per Ruangan --}}
    <x-card :padding="false">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-800">Aset per Ruangan</h2>
            <p class="text-xs text-slate-400 mt-0.5">Daftar aset yang ditempatkan di masing-masing ruangan</p>
        </div>
        <div class="overflow-x-auto">
            @if ($asetPerRuangan->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data penempatan aset.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/60">
                            <th class="px-5 py-3 text-left font-medium text-slate-500 w-10">#</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Nama Ruangan</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Lantai</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">SKPD</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Nama Aset</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Kode Barang</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Kategori</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Kondisi</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Sejak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $no = 1; @endphp
                        @foreach ($asetPerRuangan as $namaRuangan => $items)
                            @foreach ($items as $item)
                                <tr class="border-b border-slate-50 hover:bg-slate-50/50">
                                    <td class="px-5 py-3 text-slate-400">{{ $no++ }}</td>
                                    <td class="px-5 py-3 font-medium text-slate-700">{{ $namaRuangan }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->ruangan?->lantai ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->ruangan?->skpd?->nama_skpd ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $item->aset->barang->nama_barang ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->aset->barang->kode_barang ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->aset->barang->kategori->nama_kategori ?? '-' }}</td>
                                    <td class="px-5 py-3">
                                        @php
                                            $kondisi = $item->aset->kondisi ?? '-';
                                            $color = match($kondisi) {
                                                'Baik' => 'bg-emerald-50 text-emerald-700',
                                                'Rusak Ringan' => 'bg-amber-50 text-amber-700',
                                                'Rusak Berat' => 'bg-red-50 text-red-700',
                                                default => 'bg-slate-50 text-slate-500',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">{{ $kondisi }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-500">{{ $item->tanggal_mulai?->format('d/m/Y') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </x-card>

    {{-- Mutasi Bulan Ini --}}
    <x-card :padding="false">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-800">Mutasi Aset Bulan Ini</h2>
            <p class="text-xs text-slate-400 mt-0.5">Riwayat pergerakan aset selama bulan yang dipilih</p>
        </div>
        <div class="overflow-x-auto">
            @if ($mutasiBulanIni->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-slate-400">Tidak ada mutasi aset pada bulan ini.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/60">
                            <th class="px-5 py-3 text-left font-medium text-slate-500 w-10">#</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Tanggal</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Jenis Mutasi</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Nama Aset</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Pegawai Asal</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Pegawai Tujuan</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Ruangan Asal</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Ruangan Tujuan</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Status</th>
                            <th class="px-5 py-3 text-left font-medium text-slate-500">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $no = 1; @endphp
                        @foreach ($mutasiBulanIni as $mutasi)
                            @foreach ($mutasi->details as $detail)
                                <tr class="border-b border-slate-50 hover:bg-slate-50/50">
                                    <td class="px-5 py-3 text-slate-400">{{ $no++ }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $mutasi->tanggal_mutasi?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $mutasi->jenis_mutasi ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $detail->aset->barang->nama_barang ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $detail->pegawaiLama->nama_pegawai ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $detail->pegawaiBaru->nama_pegawai ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $detail->ruanganLama->nama_ruangan ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-500">{{ $detail->ruanganBaru->nama_ruangan ?? '-' }}</td>
                                    <td class="px-5 py-3">
                                        @php
                                            $status = $mutasi->status_mutasi ?? '-';
                                            $statusColor = match($status) {
                                                'Disetujui' => 'bg-emerald-50 text-emerald-700',
                                                'Ditolak' => 'bg-red-50 text-red-700',
                                                'Menunggu' => 'bg-amber-50 text-amber-700',
                                                default => 'bg-slate-50 text-slate-500',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-500 max-w-[200px] truncate">{{ $mutasi->keterangan ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </x-card>

</div>
@endsection
