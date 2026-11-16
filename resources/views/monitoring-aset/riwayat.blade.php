@extends('layouts.app')

@section('title', 'Riwayat Sensus - ' . ($aset->kendaraan?->namaTampil() ?? $aset->barang?->nama_barang ?? 'Aset'))

@section('page-title', 'Riwayat Sensus Aset')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs text-slate-500">
            <a href="{{ route('monitoring-aset.index') }}" class="hover:text-disbun-700 transition">Monitoring Aset</a>
            <span>/</span>
            <span class="font-semibold text-slate-700">Riwayat Sensus</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('monitoring-aset.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Kembali
            </a>
        </div>
    </div>

    {{-- Ringkasan Aset --}}
    <x-card>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $aset->is_kendaraan ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-700' }}">
                    {{ $aset->is_kendaraan ? 'Kendaraan' : 'Barang' }}
                </span>
                <h2 class="mt-1 text-lg font-bold text-slate-900">
                    {{ $aset->kendaraan?->namaTampil() ?? $aset->barang?->nama_barang ?? 'Aset' }}
                </h2>
                <p class="font-mono text-xs text-slate-400">
                    No. Kartu: {{ $aset->nomor_kartu_barang ?? '-' }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-2 text-right">
                <span class="text-[11px] font-medium text-slate-400 block">Periode Aktif Saat Ini</span>
                <span class="text-sm font-bold text-emerald-700 font-mono">{{ $periodeAktif }}</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 text-xs md:grid-cols-4">
            <div>
                <span class="text-slate-400 block font-medium">Lokasi Fisik</span>
                <span class="mt-0.5 font-semibold text-slate-800 block">{{ $aset->penempatanAktif?->ruangan?->nama_ruangan ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block font-medium">Pemegang</span>
                <span class="mt-0.5 font-semibold text-slate-800 block">{{ $aset->pemegangSaatIni?->pegawai?->nama_pegawai ?? 'Tanpa Pemegang' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block font-medium">Tgl Pengadaan</span>
                <span class="mt-0.5 font-semibold text-slate-800 block">{{ $aset->tanggal_pengadaan ? $aset->tanggal_pengadaan->format('d M Y') : '-' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block font-medium">Total Sensus Tercatat</span>
                <span class="mt-0.5 font-semibold text-slate-800 block">{{ $riwayatSensus->count() }} Kali</span>
            </div>
        </div>
    </x-card>

    {{-- Tabel Riwayat Seluruh Periode --}}
    <x-card>
        <div class="border-b border-slate-100 pb-4">
            <h3 class="text-base font-bold text-slate-900">Daftar Riwayat Sensus per Periode</h3>
            <p class="text-xs text-slate-500 mt-0.5">Seluruh hasil sensus siklus 3 tahunan terdahulu tetap dipertahankan dan tidak ditimpa.</p>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-3">Periode</th>
                        <th class="py-3 px-3">Hasil Sensus</th>
                        <th class="py-3 px-3">Tanggal Pelaksanaan</th>
                        <th class="py-3 px-3">Petugas Pencatat</th>
                        <th class="py-3 px-3">Catatan</th>
                        <th class="py-3 px-3">Waktu Rekam</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($riwayatSensus as $r)
                        @php
                            $badge = match ($r->hasil_sensus) {
                                'Baik' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'Rusak Ringan' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'Rusak Berat' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-600 border-slate-200',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="py-3 px-3 font-semibold text-slate-800">
                                {{ $r->periode }}
                                @if ($r->periode === $periodeAktif)
                                    <span class="ml-1 text-[10px] text-emerald-600 font-bold">(Aktif)</span>
                                @endif
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $badge }}">
                                    {{ $r->hasil_sensus }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                {{ $r->tanggal_sensus ? $r->tanggal_sensus->format('d M Y') : '-' }}
                            </td>
                            <td class="py-3 px-3 text-slate-700 font-medium">
                                {{ $r->namaPetugas() }}
                            </td>
                            <td class="py-3 px-3 text-slate-600 max-w-sm">
                                {{ $r->catatan ?: '—' }}
                            </td>
                            <td class="py-3 px-3 text-slate-400 text-[11px]">
                                {{ $r->created_at ? $r->created_at->format('d M Y H:i') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">Belum ada catatan sensus untuk aset ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

</div>
@endsection

