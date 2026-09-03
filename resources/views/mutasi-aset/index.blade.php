@extends('layouts.app')

@section('title', 'Riwayat Mutasi Aset')

@section('content')
    <x-page-header title="Riwayat Mutasi Aset" subtitle="Arsip pergantian pemegang / mutasi seluruh aset barang">
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" action="{{ route('mutasi-aset.index') }}" class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nama barang, pemegang baru, atau keterangan..."
                    autocomplete="off"
                    class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition"
                >
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                Cari
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Mutasi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis Mutasi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Lama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Baru</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Keterangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Diinput Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($mutasiList as $i => $mutasi)
                        @php $detail = $mutasi->details->first(); @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $mutasiList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $mutasi->tanggal_mutasi?->format('d M Y') ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                                @if ($detail?->aset)
                                    <a href="{{ route('aset-barang.aset.detail', $detail->aset->id_aset) }}" class="text-slate-900 transition hover:text-emerald-600 hover:underline">{{ $detail->aset->barang?->nama_barang ?? $detail->aset->nomor_kartu_barang }}</a>
                                @else
                                    <span>-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $mutasi->jenis_mutasi ?? '-' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $detail?->pegawaiLama?->nama_pegawai ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-emerald-700">{{ $detail?->pegawaiBaru?->nama_pegawai ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $mutasi->keterangan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $mutasi->userPenginput?->pegawai?->nama_pegawai ?? $mutasi->userPenginput?->username ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada riwayat mutasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $mutasiList->links() }}
        </div>
    </x-card>
@endsection
