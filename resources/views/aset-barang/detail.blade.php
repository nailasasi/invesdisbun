@extends('layouts.app')

@section('title', 'Detail Aset')

@section('content')
    <x-page-header title="Detail Aset" subtitle="{{ $aset->barang?->nama_barang ?? 'Barang' }} — {{ $aset->nomor_kartu_barang ?? '-' }}">
        <x-slot name="actions">
            <a href="{{ route('aset-barang.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                &larr; Kembali
            </a>
        </x-slot>
    </x-page-header>

    @php
        $kondisiColor = match ($aset->kondisi) {
            'Baik' => 'bg-emerald-100 text-emerald-700',
            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
            'Rusak Berat' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $pemegang = $aset->pemegangSaatIni?->pegawai;
        $ruangan = $aset->penempatanAktif?->ruangan;
    @endphp

    {{-- Informasi Lengkap --}}
    <x-card>
        <h3 class="mb-4 text-base font-semibold text-slate-900">Informasi Barang</h3>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nama Barang</dt>
                <dd class="text-sm text-slate-900">{{ $aset->barang?->nama_barang ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kategori</dt>
                <dd class="text-sm text-slate-900">{{ $aset->barang?->kategori?->nama_kategori ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Kartu Barang</dt>
                <dd class="text-sm text-slate-900">{{ $aset->nomor_kartu_barang ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Merk</dt>
                <dd class="text-sm text-slate-900">{{ $aset->merk ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kondisi</dt>
                <dd class="text-sm">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status Aset</dt>
                <dd class="text-sm text-slate-900">{{ $aset->status_aset ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nilai Perolehan</dt>
                <dd class="text-sm text-slate-900">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Pengadaan</dt>
                <dd class="text-sm text-slate-900">{{ $aset->tanggal_pengadaan?->format('d M Y') ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Perolehan</dt>
                <dd class="text-sm text-slate-900">{{ $aset->tanggal_perolehan?->format('d M Y') ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Habis Pakai</dt>
                <dd class="text-sm text-slate-900">{{ $aset->tanggal_habis_pakai?->format('d M Y') ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Pemegang Saat Ini</dt>
                <dd class="text-sm">
                    @if ($pemegang)
                        <a href="{{ route('aset-barang.show', $pemegang->id_pegawai) }}" class="font-medium text-emerald-600 hover:underline">{{ $pemegang->nama_pegawai }}</a>
                    @else
                        <span class="text-slate-400">Tanpa pemegang</span>
                    @endif
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Ruangan Saat Ini</dt>
                <dd class="text-sm">
                    @if ($ruangan)
                        {{ $ruangan->nama_ruangan }}
                        @if ($ruangan->skpd)
                            <span class="text-xs text-slate-400">({{ $ruangan->skpd->nama_skpd }})</span>
                        @endif
                    @else
                        <span class="text-slate-400">Belum ditempatkan</span>
                    @endif
                </dd>
            </div>
        </dl>
    </x-card>

    {{-- Riwayat Mutasi --}}
    <x-card :padding="false" class="mt-6">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-base font-semibold text-slate-900">Riwayat Mutasi Barang Ini</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ruangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Keterangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Diinput Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($riwayatMutasi as $detail)
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $detail->mutasi?->tanggal_mutasi?->format('d M Y') ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $detail->mutasi?->jenis_mutasi ?? '-' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                <span class="text-slate-400">{{ $detail->pegawaiLama?->nama_pegawai ?? '-' }}</span>
                                &rarr;
                                <span class="font-medium text-emerald-700">{{ $detail->pegawaiBaru?->nama_pegawai ?? '-' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                <span class="text-slate-400">{{ $detail->ruanganLama?->nama_ruangan ?? '-' }}</span>
                                &rarr;
                                <span class="font-medium text-emerald-700">{{ $detail->ruanganBaru?->nama_ruangan ?? '-' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $detail->mutasi?->keterangan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $detail->mutasi?->userPenginput?->pegawai?->nama_pegawai ?? $detail->mutasi?->userPenginput?->username ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada riwayat mutasi untuk barang ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
