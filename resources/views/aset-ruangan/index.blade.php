@extends('layouts.app')

@section('title', 'Aset Ruangan')

@section('page-title', 'Aset Ruangan')

@section('content')
    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('aset-ruangan.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:flex-nowrap">
                <div class="relative w-full min-w-0 sm:max-w-xs lg:flex-1">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Cari nama ruangan..."
                        autocomplete="off"
                        class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition"
                    >
                </div>

                <select name="lokasi" aria-label="Filter lokasi" class="block w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400 sm:flex-1 lg:w-auto lg:max-w-[14rem]">
                    <option value="">Semua Lokasi</option>
                    @foreach ($lokasiOptions as $lokasi)
                        <option value="{{ $lokasi->id_lokasi }}" @selected((string) request('lokasi') === (string) $lokasi->id_lokasi)>{{ $lokasi->nama_lokasi }}</option>
                    @endforeach
                </select>

                <button type="submit" class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800 sm:w-auto">
                    Cari
                </button>
            </form>

            @if ($bolehKelolaRuangan)
            <x-slot name="actions">
                <x-button type="button" id="btn-tambah" icon="M12 4v16m8-8H4">
                    Tambah Ruangan
                </x-button>
            </x-slot>
            @endif
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Ruangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lantai</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Total Aset</th>
                        <th scope="col" class="px-6 py-3 text-right whitespace-nowrap text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($ruanganList as $i => $ruangan)
                        @php
                            $editJson = json_encode([
                                'id' => $ruangan->id_ruangan,
                                'nama_ruangan' => $ruangan->nama_ruangan,
                                'lantai' => $ruangan->lantai,
                                'id_lokasi' => $ruangan->id_lokasi,
                                'id_skpd' => $ruangan->id_skpd,
                            ]);
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $ruanganList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $ruangan->nama_ruangan }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $ruangan->lantai ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($ruangan->status === 'Nonaktif')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Nonaktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-disbun-50 px-2.5 py-0.5 text-xs font-semibold text-disbun-800">Aktif</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-disbun-100 px-2.5 py-0.5 text-xs font-semibold text-disbun-800">
                                    {{ $ruangan->jumlah_aset + $ruangan->jumlah_inventaris }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-right text-sm">
                                <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                    <a href="{{ route('aset-ruangan.show', $ruangan->id_ruangan) }}" title="Lihat Inventaris Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-white text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if ($bolehKelolaRuangan)
                                        <button type="button" data-edit-modal="{{ $editJson }}" title="Edit Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition hover:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('aset-ruangan.toggle-status', $ruangan->id_ruangan) }}" onsubmit="return confirm('Ubah status ruangan ini?')">
                                            @csrf
                                            @if ($ruangan->status === 'Nonaktif')
                                                <button type="submit" title="Aktifkan Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700 transition hover:bg-disbun-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </button>
                                            @else
                                                <button type="submit" title="Nonaktifkan Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-amber-50 hover:text-amber-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                                                </button>
                                            @endif
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada ruangan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $ruanganList->links() }}
        </div>
    </x-card>

    @include('aset-ruangan.partials.modal-ruangan')
@endsection
