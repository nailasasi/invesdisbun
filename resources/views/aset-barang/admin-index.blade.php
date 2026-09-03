@extends('layouts.app')

@section('title', 'Aset Barang')

@section('content')
    <x-page-header title="Aset Barang" subtitle="Seluruh aset beserta pemegangnya">
        <x-slot name="actions">
            <x-button type="button" id="btn-tambah" icon="M12 4v16m8-8H4">
                Tambah Aset
            </x-button>
        </x-slot>
    </x-page-header>

    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <form method="GET" action="{{ route('aset-barang.index') }}" class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nama barang, kartu, merk, atau pemegang..."
                    autocomplete="off"
                    class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition"
                >
            </div>

            <div class="flex flex-wrap gap-3">
                <select name="penempatan" class="block rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition lg:w-48">
                    <option value="">Semua Barang</option>
                    <option value="pemegang" @selected(request('penempatan') === 'pemegang')>Dengan Pemegang</option>
                    <option value="tanpa_pemegang" @selected(request('penempatan') === 'tanpa_pemegang')>Tanpa Pemegang</option>
                </select>
                <select name="pemegang" class="block rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition lg:w-56">
                    <option value="">Semua Pemegang</option>
                    @foreach ($pemegangOptions as $p)
                        <option value="{{ $p->id_pegawai }}" @selected(request('pemegang') == $p->id_pegawai)>{{ $p->nama_pegawai }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                    Cari
                </button>
                @if (request('search') || request('penempatan') || request('pemegang'))
                    <a href="{{ route('aset-barang.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kartu Barang</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Merk</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kondisi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($asetList as $i => $item)
                        @php
                            $aset = $item;
                            $pemegang = $aset->pemegangSaatIni?->pegawai;
                            $kondisiColor = match ($aset->kondisi) {
                                'Baik' => 'bg-emerald-100 text-emerald-700',
                                'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                                'Rusak Berat' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $asetList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                                <a href="{{ route('aset-barang.aset.detail', $aset->id_aset) }}" class="text-slate-900 transition hover:text-emerald-600 hover:underline">{{ $aset->barang?->nama_barang ?? '-' }}</a>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nomor_kartu_barang ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->merk ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->status_aset ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($pemegang)
                                    <a href="{{ route('aset-barang.show', $pemegang->id_pegawai) }}" class="font-medium text-emerald-600 transition hover:text-emerald-700 hover:underline">
                                        {{ $pemegang->nama_pegawai }}
                                    </a>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('aset-barang.aset.detail', $aset->id_aset) }}" title="Lihat Detail" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Detail
                                    </a>
                                    <button type="button" data-mutasi-modal="{{ $aset->id_aset }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-600 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                        Mutasi
                                    </button>
                                    <button type="button" data-edit-modal="{{ $aset->id_aset }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        Edit
                                    </button>
                                    <button type="button" data-delete-target="{{ $aset->id_aset }}" data-delete-name="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang }}" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada data aset.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $asetList->links() }}
        </div>
    </x-card>

    {{-- Modal Tambah / Edit Aset --}}
    <div id="aset-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-modal-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 id="modal-title" class="text-lg font-semibold text-slate-900">Tambah Aset</h3>
                    <p id="modal-subtitle" class="mt-0.5 text-sm text-slate-500">Lengkapi data aset dan pilih pemegangnya.</p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="aset-form" method="POST" action="{{ route('aset-barang.store.flat') }}" autocomplete="off">
                @csrf
                <input type="hidden" id="field-id" name="id" value="">
                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div id="placement-fields" class="contents">
                        <div class="space-y-1.5">
                            <label for="field-pegawai" class="block text-sm font-medium text-slate-700">Pemegang</label>
                            <select id="field-pegawai" name="id_pegawai" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <option value="" selected>-- Pilih Pegawai --</option>
                                @foreach ($allPegawai as $p)
                                    <option value="{{ $p->id_pegawai }}" data-ruangan="{{ $p->id_ruangan ?? '' }}">{{ $p->nama_pegawai }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_pegawai"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-ruangan" class="block text-sm font-medium text-slate-700">Ruangan</label>
                            <select id="field-ruangan" name="id_ruangan" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <option value="" selected>-- Pilih Ruangan --</option>
                                @foreach ($ruanganOptions as $r)
                                    <option value="{{ $r->id_ruangan }}">{{ $r->nama_ruangan }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_ruangan"></p>
                        </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-barang" class="block text-sm font-medium text-slate-700">Nama Barang <span class="text-red-500">*</span></label>
                            <input type="text" id="field-barang" name="nama_barang" required maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Laptop">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_barang"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-kartu" class="block text-sm font-medium text-slate-700">Nomor Kartu Barang <span class="text-red-500">*</span></label>
                            <input type="text" id="field-kartu" name="nomor_kartu_barang" maxlength="50" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: KIB-001">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_kartu_barang"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-merk" class="block text-sm font-medium text-slate-700">Merk</label>
                            <input type="text" id="field-merk" name="merk" maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Canon">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="merk"></p>
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
                            <input type="text" id="field-status" name="status_aset" required maxlength="50" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Aktif">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="status_aset"></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                        Simpan
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    {{-- Modal Mutasi Aset --}}
    <div id="mutasi-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-mutasi-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Mutasi Aset</h3>
                    <p class="mt-0.5 text-sm text-slate-500" id="mutasi-aset-info">Pilih jenis mutasi untuk aset ini.</p>
                </div>
                <button type="button" data-mutasi-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="mutasi-form" method="POST" autocomplete="off">
                @csrf
                <input type="hidden" id="mutasi-aset-id" name="id_aset" value="">
                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-6">
                    <div class="space-y-1.5">
                        <label for="mutasi-aset-name" class="block text-sm font-medium text-slate-700">Aset</label>
                        <div id="mutasi-aset-name" class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700"></div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">Jenis Mutasi <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" id="tipe-pegawai" data-tipe="pegawai" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition">
                                Ganti Pemegang
                            </button>
                            <button type="button" id="tipe-ruangan" data-tipe="ruangan" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition">
                                Pindah Ruangan
                            </button>
                        </div>
                        <input type="hidden" id="mutasi-tipe" name="tipe" value="">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tipe"></p>
                    </div>

                    <div id="mutasi-field-pegawai" class="space-y-1.5 hidden">
                        <label for="mutasi-pegawai" class="block text-sm font-medium text-slate-700">Pemegang Baru <span class="text-red-500">*</span></label>
                        <select id="mutasi-pegawai" name="id_pegawai" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="" selected>-- Pilih Pemegang Baru --</option>
                            @foreach ($allPegawai as $p)
                                <option value="{{ $p->id_pegawai }}">{{ $p->nama_pegawai }}</option>
                            @endforeach
                        </select>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_pegawai"></p>
                        <p class="text-xs text-slate-400">Aset akan otomatis mengikuti ruangan kerja pemegang baru.</p>
                    </div>

                    <div id="mutasi-field-ruangan" class="space-y-1.5 hidden">
                        <label for="mutasi-ruangan" class="block text-sm font-medium text-slate-700">Ruangan Tujuan <span class="text-red-500">*</span></label>
                        <select id="mutasi-ruangan" name="id_ruangan" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="" selected>-- Pilih Ruangan Tujuan --</option>
                            @foreach ($ruanganOptions as $r)
                                <option value="{{ $r->id_ruangan }}">{{ $r->nama_ruangan }}</option>
                            @endforeach
                        </select>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_ruangan"></p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="mutasi-keterangan" class="block text-sm font-medium text-slate-700">Keterangan</label>
                        <textarea id="mutasi-keterangan" name="keterangan" rows="2" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: serah terima antar pegawai"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-mutasi-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-mutasi-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                        Simpan Mutasi
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div id="delete-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-delete-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900">Hapus Aset</h3>
                    <p class="mt-1 text-sm text-slate-500">Apakah Anda yakin ingin menghapus <span id="delete-name" class="font-medium text-slate-700"></span>? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" data-delete-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="button" id="btn-delete-confirm" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">Hapus</button>
            </div>
        </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const storeUrl = @json(route('aset-barang.store.flat'));
        const updateUrl = @json(route('aset-barang.aset.update', ['aset' => '__ID__']));
        const mutasiUrl = @json(route('aset-barang.aset.mutasi', ['aset' => '__ID__']));

        const modal = document.getElementById('aset-modal');
        const deleteModal = document.getElementById('delete-modal');
        const mutasiModal = document.getElementById('mutasi-modal');
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

        // Tombol Tambah
        const btnTambah = document.getElementById('btn-tambah');
        const placementFields = document.getElementById('placement-fields');
        if (btnTambah) {
            btnTambah.addEventListener('click', () => {
                form.reset();
                document.getElementById('field-id').value = '';
                form.action = storeUrl;
                document.getElementById('modal-title').textContent = 'Tambah Aset';
                document.getElementById('modal-subtitle').textContent = 'Lengkapi data aset dan pilih pemegangnya.';
                document.getElementById('btn-submit').textContent = 'Simpan';
                form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                if (placementFields) placementFields.style.display = '';
                fieldRuangan.disabled = false;
                openModal();
            });
        }

        document.querySelectorAll('[data-edit-modal]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.editModal;
                form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                try {
                    const res = await fetch(updateUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('Gagal mengambil data');
                    const data = await res.json();
                    document.getElementById('field-id').value = data.id_aset;
                    if (placementFields) placementFields.style.display = 'none';
                    document.getElementById('field-barang').value = data.nama_barang ?? '';
                    document.getElementById('field-kartu').value = data.nomor_kartu_barang ?? '';
                    document.getElementById('field-merk').value = data.merk ?? '';
                    document.getElementById('field-pengadaan').value = data.tanggal_pengadaan ?? '';
                    document.getElementById('field-perolehan').value = data.tanggal_perolehan ?? '';
                    document.getElementById('field-habis-pakai').value = data.tanggal_habis_pakai ?? '';
                    document.getElementById('field-nilai').value = data.nilai_perolehan ?? '';
                    document.getElementById('field-kondisi').value = data.kondisi ?? '';
                    document.getElementById('field-status').value = data.status_aset ?? '';
                    form.action = updateUrl.replace('__ID__', id);
                    document.getElementById('modal-title').textContent = 'Edit Aset';
                    document.getElementById('modal-subtitle').textContent = 'Perbarui data aset. Ganti pemegang / pindah ruangan via tombol Mutasi.';
                    document.getElementById('btn-submit').textContent = 'Perbarui';
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
                    const data = await res.json();
                    window.location.reload();
                    if (data.warning) {
                        setTimeout(() => alert(data.warning), 100);
                    }
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

        modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));

        // Saat pilih pegawai -> ruangan otomatis terisi dari ruangan kerja pegawai.
        // Aset ber-pemegang: ruangan tidak bisa diedit (diatur via User Management).
        // Aset tanpa pemegang: dropdown ruangan aktif berisi semua ruangan.
        const fieldPegawai = document.getElementById('field-pegawai');
        const fieldRuangan = document.getElementById('field-ruangan');
        function setRuanganState() {
            const opt = fieldPegawai.selectedOptions[0];
            if (opt && opt.value) {
                fieldRuangan.disabled = true;
                fieldRuangan.value = (opt.dataset.ruangan ?? '');
                if (fieldRuangan.options[0]) fieldRuangan.options[0].textContent = 'Mengikuti ruangan pegawai';
            } else {
                fieldRuangan.disabled = false;
                fieldRuangan.value = '';
                if (fieldRuangan.options[0]) fieldRuangan.options[0].textContent = '-- Pilih Ruangan --';
            }
        }
        fieldPegawai.addEventListener('change', setRuanganState);

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
                btnConfirm.textContent = 'Menghapus...';
                btnConfirm.disabled = true;
                try {
                    const res = await fetch(updateUrl.replace('__ID__', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        alert('Gagal menghapus data.');
                        btnConfirm.textContent = 'Hapus';
                        btnConfirm.disabled = false;
                    }
                } catch (err) {
                    alert('Koneksi bermasalah.');
                    btnConfirm.textContent = 'Hapus';
                    btnConfirm.disabled = false;
                }
            });
        }

        // ---- Modal Mutasi ----
        const mutasiForm = document.getElementById('mutasi-form');
        const mutasiTipeInput = document.getElementById('mutasi-tipe');
        const mutasiFieldPegawai = document.getElementById('mutasi-field-pegawai');
        const mutasiFieldRuangan = document.getElementById('mutasi-field-ruangan');
        const mutasiPegawaiSel = document.getElementById('mutasi-pegawai');
        const mutasiRuanganSel = document.getElementById('mutasi-ruangan');
        const btnTipePegawai = document.getElementById('tipe-pegawai');
        const btnTipeRuangan = document.getElementById('tipe-ruangan');

        function setMutasiError(field, msg) {
            const err = mutasiForm.querySelector('[data-error-for="' + field + '"]');
            if (err) { err.textContent = msg; err.classList.remove('hidden'); }
        }

        function setMutasiMode(tipe) {
            mutasiTipeInput.value = tipe;
            const isPegawai = tipe === 'pegawai';
            btnTipePegawai.classList.toggle('bg-indigo-600', isPegawai);
            btnTipePegawai.classList.toggle('text-white', isPegawai);
            btnTipePegawai.classList.toggle('border-indigo-600', isPegawai);
            btnTipeRuangan.classList.toggle('bg-indigo-600', !isPegawai);
            btnTipeRuangan.classList.toggle('text-white', !isPegawai);
            btnTipeRuangan.classList.toggle('border-indigo-600', !isPegawai);
            mutasiFieldPegawai.classList.toggle('hidden', !isPegawai);
            mutasiFieldRuangan.classList.toggle('hidden', isPegawai);
        }
        if (btnTipePegawai) btnTipePegawai.addEventListener('click', () => setMutasiMode('pegawai'));
        if (btnTipeRuangan) btnTipeRuangan.addEventListener('click', () => setMutasiMode('ruangan'));

        function openMutasiModal(id, name, hasPemegang) {
            if (!mutasiModal) return;
            mutasiForm.reset();
            mutasiForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            document.getElementById('mutasi-aset-id').value = id;
            document.getElementById('mutasi-aset-name').textContent = name;
            setMutasiMode(hasPemegang ? 'pegawai' : 'ruangan');
            mutasiModal.classList.remove('hidden');
            mutasiModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        function closeMutasiModal() {
            if (!mutasiModal) return;
            mutasiModal.classList.add('hidden');
            mutasiModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
        if (mutasiModal) {
            mutasiModal.querySelectorAll('[data-mutasi-close]').forEach(el => el.addEventListener('click', closeMutasiModal));
        }

        document.querySelectorAll('[data-mutasi-modal]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.mutasiModal;
                try {
                    const res = await fetch(updateUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('Gagal mengambil data');
                    const data = await res.json();
                    const label = (data.nama_barang || data.nomor_kartu_barang || 'Aset') + (data.nomor_kartu_barang ? ' (' + data.nomor_kartu_barang + ')' : '');
                    openMutasiModal(id, label, !!data.id_pegawai);
                } catch (e) {
                    alert(e.message);
                }
            });
        });

        if (mutasiForm) {
            mutasiForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-mutasi-submit');
                const original = submit.textContent;
                submit.textContent = 'Menyimpan...';
                submit.disabled = true;
                mutasiForm.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
                const body = new FormData(mutasiForm);
                body.delete('id_aset');
                try {
                    const res = await fetch(mutasiUrl.replace('__ID__', document.getElementById('mutasi-aset-id').value), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                    if (res.status === 422) {
                        const data = await res.json();
                        (data.errors.tipe || []).forEach(m => setMutasiError('tipe', m));
                        (data.errors.id_pegawai || []).forEach(m => setMutasiError('id_pegawai', m));
                        (data.errors.id_ruangan || []).forEach(m => setMutasiError('id_ruangan', m));
                        submit.textContent = original;
                        submit.disabled = false;
                        return;
                    }
                    if (res.ok) {
                        const data = await res.json();
                        window.location.reload();
                        if (data.warning) {
                            setTimeout(() => alert(data.warning), 100);
                        }
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
        }
    </script>
    @endpush
@endsection
