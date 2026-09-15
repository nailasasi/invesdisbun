@extends('layouts.app')

@section('title', 'Detail Kendaraan')

@section('content')
    {{-- Navigasi Kembali Minimalis di Atas Judul --}}
    <div class="mb-3">
        <a href="{{ route('kendaraan.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-900">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Kendaraan</span>
        </a>
    </div>

    {{-- Header Judul --}}
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Detail Kendaraan</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $kendaraan->aset?->barang?->nama_barang ?? 'Kendaraan' }} — {{ $kendaraan->aset?->nomor_kartu_barang ?? '-' }}</p>
        </div>
    </div>

    @php
        $isAdmin = $isAdminAset;
        $kondisiColor = match ($kendaraan->aset?->kondisi) {
            'Baik' => 'bg-emerald-100 text-emerald-700',
            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
            'Rusak Berat' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $platAktif = $kendaraan->platAktif;
        $pajakAktif = $kendaraan->pajakAktif;
        $statusBadge = match ($kendaraan->aset?->status_aset) {
            'aktif' => 'bg-emerald-100 text-emerald-700',
            'diusulkan_hapus' => 'bg-amber-100 text-amber-700',
            'dihapuskan' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    {{-- Info Kendaraan --}}
    <x-card>
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-900">Informasi Kendaraan</h3>
            @if ($isAdmin)
                <button type="button" data-edit-kendaraan="{{ $kendaraan->id_kendaraan }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </button>
            @endif
        </div>
        @if ($kendaraan->foto)
            <div class="mb-5">
                <img src="{{ asset('storage/' . $kendaraan->foto) }}" alt="Foto kendaraan" class="h-48 w-full rounded-xl object-cover sm:w-72">
            </div>
        @endif
        <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nama Kendaraan</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->barang?->nama_barang ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Jenis Kendaraan</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->jenis_kendaraan ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Kartu Barang</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->nomor_kartu_barang ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Merk</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->merk ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tipe</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->tipe ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Rangka</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->nomor_rangka ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nomor Mesin</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->nomor_mesin ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Plat Aktif</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $platAktif->nomor_plat ?? '-' }}
                    @if ($platAktif?->tanggal_berlaku)
                        <span class="block text-xs font-normal text-slate-400">Masa aktif: {{ $platAktif->tanggal_berlaku->format('d M Y') }}</span>
                    @endif
                    @if ($platAktif?->ganti_plat)
                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-medium text-indigo-700">Ganti Plat</span>
                    @endif
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kondisi</dt>
                <dd class="text-sm">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $kendaraan->aset?->kondisi ?? '-' }}</span>
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status Aset</dt>
                <dd class="text-sm">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge }}">{{ $kendaraan->aset?->status_aset ?? '-' }}</span>
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Pemegang Kendaraan</dt>
                <dd class="text-sm">
                    @if ($kendaraan->pemegang)
                        <span class="font-medium text-slate-700">{{ $kendaraan->pemegang }}</span>
                    @else
                        <span class="text-slate-400">-</span>
                    @endif
                </dd>
            </div>
            <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Keterangan</dt>
                <dd class="text-sm text-slate-900">{{ $kendaraan->keterangan ?: '-' }}</dd>
            </div>
            @if ($isAdmin)
                <div class="space-y-1">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Nilai Perolehan</dt>
                    <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->nilai_perolehan ? 'Rp ' . number_format((float) $kendaraan->aset->nilai_perolehan, 0, ',', '.') : '-' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Pengadaan</dt>
                    <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->tanggal_pengadaan?->format('d M Y') ?? '-' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Tanggal Perolehan</dt>
                    <dd class="text-sm text-slate-900">{{ $kendaraan->aset?->tanggal_perolehan?->format('d M Y') ?? '-' }}</dd>
                </div>
            @endif
        </div>
    </x-card>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Riwayat Plat --}}
        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-semibold text-slate-900">Riwayat Plat Nomor</h3>
                @if ($isAdmin)
                    <button type="button" data-plat-modal class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700">
                        + Tambah Plat
                    </button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor Plat</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Masa Aktif</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($kendaraan->riwayatPlat as $index => $plat)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ $plat->nomor_plat }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $plat->tanggal_berlaku?->format('d M Y') ?? '-' }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    @if ($plat->status === 'Aktif')
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700">Aktif</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-500">Riwayat</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada riwayat plat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Pajak Kendaraan --}}
        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-semibold text-slate-900">Pajak Kendaraan</h3>
                @if ($isAdmin)
                    <button type="button" data-pajak-modal class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700">
                        + Tambah Pajak
                    </button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jatuh Tempo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">5 Tahunan</th>
                            @if ($isAdmin)
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nominal</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($kendaraan->pajak as $pajak)
                            @php
                                $expired = $pajak->tanggal_berakhir && $pajak->tanggal_berakhir->isPast();
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm text-slate-700">{{ $pajak->jenis_pajak }}
                                    @if ($pajak->status === 'Aktif')
                                        <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700">Aktif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($expired)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-700">{{ $pajak->tanggal_berakhir->format('d M Y') }}</span>
                                    @else
                                        <span class="text-slate-600">{{ $pajak->tanggal_berakhir?->format('d M Y') ?? '-' }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $pajak->pajak_5_tahunan ? 'Ya' : 'Tidak' }}</td>
                                @if ($isAdmin)
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $pajak->nominal ? 'Rp ' . number_format((float) $pajak->nominal, 0, ',', '.') : '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $pajak->total_pajak ? 'Rp ' . number_format((float) $pajak->total_pajak, 0, ',', '.') : '-' }}</td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <button type="button" data-delete-pajak="{{ $pajak->id_pajak }}" data-pajak-name="{{ $pajak->jenis_pajak }}" class="inline-flex items-center rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">Hapus</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 6 : 3 }}" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada data pajak.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Izin Pakai Kendaraan --}}
    <x-card :padding="false" class="mt-6">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 class="text-base font-semibold text-slate-900">Izin Pakai Kendaraan</h3>
            <button type="button" data-izin-modal class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700">
                + Ajukan Izin
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pengaju</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Berangkat</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kembali</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pengemudi</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        @if ($isAdmin)
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($kendaraan->izin as $izin)
                        @php
                            $statusColor = match ($izin->status_approval) {
                                'Disetujui' => 'bg-emerald-100 text-emerald-700',
                                'Ditolak' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $izin->pengaju?->nama_pegawai ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $izin->tanggal_berangkat?->format('d M Y') ?? '-' }}
                                @if ($izin->waktu_berangkat)
                                    <span class="text-xs text-slate-400">{{ date('H:i', strtotime($izin->waktu_berangkat)) }}</span>
                                @endif
                                <p class="text-xs text-slate-400">{{ $izin->tujuan ?? '' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $izin->tanggal_kembali?->format('d M Y') ?? '-' }}
                                @if ($izin->waktu_kembali)
                                    <span class="text-xs text-slate-400">{{ date('H:i', strtotime($izin->waktu_kembali)) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                @if ($izin->id_pegawai_pengemudi)
                                    {{ $izin->pengemudi?->nama_pegawai }}
                                @else
                                    {{ $izin->nama_pengemudi ?? '-' }}
                                @endif
                                @if ($izin->file_surat)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($izin->file_surat) }}" target="_blank" class="ml-1 text-xs font-medium text-emerald-600 hover:underline">[surat]</a>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">{{ $izin->status_approval ?? 'Menunggu' }}</span>
                            </td>
                            @if ($isAdmin)
                                <td class="px-6 py-4 text-right text-sm">
                                    @if ($izin->status_approval === 'Menunggu')
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" data-approve-izin="{{ $izin->id_izin }}" data-status="Disetujui" class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100">Setuju</button>
                                            <button type="button" data-approve-izin="{{ $izin->id_izin }}" data-status="Ditolak" class="inline-flex items-center rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">Tolak</button>
                                        </div>
                                    @else
                                        <button type="button" data-delete-izin="{{ $izin->id_izin }}" class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-200">Hapus</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada permohonan izin.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- MODAL PLAT --}}
    @if ($isAdmin)
    <div id="plat-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Tambah Riwayat Plat</h3>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form id="plat-form" method="POST" class="space-y-4 px-6 py-6">
                @csrf
                <div class="space-y-1.5">
                    <label for="plat-nomor" class="block text-sm font-medium text-slate-700">Nomor Plat <span class="text-red-500">*</span></label>
                    <input type="text" id="plat-nomor" name="nomor_plat" maxlength="20" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: B 1234 ABC">
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_plat"></p>
                </div>
                <div class="space-y-1.5">
                    <label for="plat-tanggal" class="block text-sm font-medium text-slate-700">Masa Aktif Nomor Polisi</label>
                    <input type="date" id="plat-tanggal" name="tanggal_berlaku" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                </div>
                <div class="space-y-1.5">
                    <label for="plat-ganti" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" id="plat-ganti" name="ganti_plat" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Ganti Plat
                    </label>
                </div>
                <div class="space-y-1.5">
                    <label for="plat-status" class="block text-sm font-medium text-slate-700">Status <span class="text-red-500">*</span></label>
                    <select id="plat-status" name="status" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <option value="Aktif">Aktif</option>
                        <option value="Tidak Aktif">Tidak Aktif</option>
                    </select>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL PAJAK --}}
    <div id="pajak-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Tambah Pajak</h3>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form id="pajak-form" method="POST" class="space-y-4 px-6 py-6">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="pajak-jenis" class="block text-sm font-medium text-slate-700">Jenis <span class="text-red-500">*</span></label>
                        <select id="pajak-jenis" name="jenis_pajak" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            @foreach ($jenisPajakList as $jp)
                                <option value="{{ $jp }}">{{ $jp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="pajak-tahun" class="block text-sm font-medium text-slate-700">Tahun</label>
                        <input type="number" id="pajak-tahun" name="tahun" min="2000" max="2100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" value="{{ date('Y') }}">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="pajak-bayar" class="block text-sm font-medium text-slate-700">Tanggal Bayar</label>
                        <input type="date" id="pajak-bayar" name="tanggal_bayar" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                    <div class="space-y-1.5">
                        <label for="pajak-berakhir" class="block text-sm font-medium text-slate-700">Jatuh Tempo (PAJAK BULAN)</label>
                        <input type="date" id="pajak-berakhir" name="tanggal_berakhir" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="pajak-nominal" class="block text-sm font-medium text-slate-700">Nominal Pajak (Rp)</label>
                        <input type="number" id="pajak-nominal" name="nominal" min="0" step="0.01" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 1500000">
                    </div>
                    <div class="space-y-1.5">
                        <label for="pajak-total" class="block text-sm font-medium text-slate-700">Total Pajak (Rp)</label>
                        <input type="number" id="pajak-total" name="total_pajak" min="0" step="0.01" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 2100000">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label for="pajak-5thn" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" id="pajak-5thn" name="pajak_5_tahunan" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Pajak 5 Tahunan
                    </label>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- MODAL IZIN --}}
    <div id="izin-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Ajukan Izin Pakai Kendaraan</h3>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form id="izin-form" method="POST" enctype="multipart/form-data" class="space-y-4 px-6 py-6">
                @csrf
                @if ($isAdmin)
                    <div class="space-y-1.5">
                        <label for="izin-pengaju" class="block text-sm font-medium text-slate-700">Pengaju</label>
                        <select id="izin-pengaju" name="id_pegawai_pengaju" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="">-- Pilih Pengaju --</option>
                            @foreach ($pengajuOptions as $p)
                                <option value="{{ $p->id_pegawai }}">{{ $p->nama_pegawai }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="izin-tgl-berangkat" class="block text-sm font-medium text-slate-700">Tanggal Berangkat</label>
                        <input type="date" id="izin-tgl-berangkat" name="tanggal_berangkat" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                    <div class="space-y-1.5">
                        <label for="izin-waktu-berangkat" class="block text-sm font-medium text-slate-700">Jam Berangkat</label>
                        <input type="time" id="izin-waktu-berangkat" name="waktu_berangkat" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                    <div class="space-y-1.5">
                        <label for="izin-tgl-kembali" class="block text-sm font-medium text-slate-700">Tanggal Kembali</label>
                        <input type="date" id="izin-tgl-kembali" name="tanggal_kembali" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                    <div class="space-y-1.5">
                        <label for="izin-waktu-kembali" class="block text-sm font-medium text-slate-700">Jam Kembali</label>
                        <input type="time" id="izin-waktu-kembali" name="waktu_kembali" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label for="izin-tujuan" class="block text-sm font-medium text-slate-700">Tujuan Perjalanan</label>
                    <textarea id="izin-tujuan" name="tujuan" rows="2" maxlength="500" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Dinas ke Dinas Perkebunan Provinsi"></textarea>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="izin-jenis-pengemudi" class="block text-sm font-medium text-slate-700">Jenis Pengemudi</label>
                        <select id="izin-jenis-pengemudi" name="jenis_pengemudi" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="">-- Pilih --</option>
                            <option value="Pegawai">Pegawai</option>
                            <option value="Supir">Supir</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="izin-pengemudi" class="block text-sm font-medium text-slate-700">Pengemudi</label>
                        <select id="izin-pengemudi" name="id_pegawai_pengemudi" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="">-- Pilih Pegawai --</option>
                            @foreach ($pengemudiOptions as $p)
                                <option value="{{ $p->id_pegawai }}">{{ $p->nama_pegawai }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label for="izin-file" class="block text-sm font-medium text-slate-700">File Surat (opsional)</label>
                    <input type="file" id="izin-file" name="file_surat" accept=".pdf,.jpg,.jpeg,.png" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">Ajukan</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const kendaraanId = {{ $kendaraan->id_kendaraan }};
        const createDataUrl = @json(route('kendaraan.create'));
        const editUrl = @json(route('kendaraan.edit', ['kendaraan' => '__ID__']));
        const updateUrl = @json(route('kendaraan.update', ['kendaraan' => '__ID__']));
        const platStoreUrl = @json(route('kendaraan.plat.store', ['kendaraan' => '__ID__']));
        const pajakStoreUrl = @json(route('kendaraan.pajak.store', ['kendaraan' => '__ID__']));
        const pajakDeleteBase = @json(route('kendaraan.pajak.destroy', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));
        const izinStoreUrl = @json(route('kendaraan.izin.store', ['kendaraan' => '__ID__']));
        const izinApproveBase = @json(route('kendaraan.izin.approve', ['kendaraan' => '__ID__', 'izin' => '__IZIN__']));
        const izinDeleteBase = @json(route('kendaraan.izin.destroy', ['kendaraan' => '__ID__', 'izin' => '__IZIN__']));

        let loadedDropdowns = false;

        function openModal(el) { el.classList.remove('hidden'); el.classList.add('flex'); document.body.classList.add('overflow-hidden'); }
        function closeModal(el) { el.classList.add('hidden'); el.classList.remove('flex'); document.body.classList.remove('overflow-hidden'); }
        function wireClose(modal) {
            modal.querySelectorAll('[data-modal-close]').forEach(x => x.addEventListener('click', () => closeModal(modal)));
        }

        async function ensureDropdowns() {
            if (loadedDropdowns) return;
            const res = await fetch(createDataUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const data = await res.json();
            const jenis = document.getElementById('field-jenis');
            if (jenis) {
                jenis.innerHTML = '<option value="">-- Pilih Jenis --</option>';
                data.jenisList.forEach(v => {
                    const el = document.createElement('option'); el.value = v; el.textContent = v;
                    jenis.appendChild(el);
                });
            }
            const kondisi = document.getElementById('field-kondisi');
            if (kondisi) {
                kondisi.innerHTML = '<option value="">-- Pilih Kondisi --</option>';
                data.kondisiList.forEach(v => {
                    const el = document.createElement('option'); el.value = v; el.textContent = v;
                    kondisi.appendChild(el);
                });
            }
            loadedDropdowns = true;
        }

        @if ($isAdmin)
        // Edit kendaraan dari halaman detail
        document.querySelectorAll('[data-edit-kendaraan]').forEach(btn => {
            btn.addEventListener('click', async () => {
                await ensureDropdowns();
                const d = await (await fetch(editUrl.replace('__ID__', kendaraanId), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json();
                document.getElementById('field-id').value = d.id_kendaraan;
                document.getElementById('field-id-aset').value = d.id_aset;
                document.getElementById('field-nama').value = d.nama_kendaraan ?? '';
                document.getElementById('field-kartu').value = d.nomor_kartu_barang ?? '';
                document.getElementById('field-jenis').value = d.jenis_kendaraan ?? '';
                document.getElementById('field-pemegang').value = d.pemegang ?? '';
                document.getElementById('field-keterangan').value = d.keterangan ?? '';
                document.getElementById('field-rangka').value = d.nomor_rangka ?? '';
                document.getElementById('field-mesin').value = d.nomor_mesin ?? '';
                document.getElementById('field-merk').value = d.merk ?? '';
                document.getElementById('field-tipe').value = d.tipe ?? '';
                document.getElementById('field-plat').value = d.plat_nomor ?? '';
                document.getElementById('field-tgl-plat').value = d.plat_tanggal ?? '';
                document.getElementById('field-nilai').value = d.nilai_perolehan ?? '';
                document.getElementById('field-kondisi').value = d.kondisi ?? '';
                document.getElementById('field-status').value = d.status_aset ?? '';
                document.getElementById('field-tgl-pengadaan').value = d.tanggal_pengadaan ?? '';
                document.getElementById('field-tgl-perolehan').value = d.tanggal_perolehan ?? '';
                document.getElementById('field-tgl-habis').value = d.tanggal_habis_pakai ?? '';
                document.getElementById('field-pajak-tgl').value = d.pajak_tanggal_berakhir ?? '';
                document.getElementById('field-pajak-5thn').checked = !!d.pajak_max_tahunan;
                document.getElementById('field-pajak-nominal').value = d.pajak_nominal ?? '';
                document.getElementById('field-pajak-total').value = d.pajak_total ?? '';
                document.getElementById('modal-title').textContent = 'Edit Kendaraan';
                const kForm = document.getElementById('kendaraan-form');
                kForm.action = updateUrl.replace('__ID__', kendaraanId);
                const kModal = document.getElementById('kendaraan-modal');
                kModal.querySelectorAll('[data-modal-close]').forEach(x => x.addEventListener('click', () => {
                    kModal.style.visibility = 'hidden';
                    document.body.classList.remove('overflow-hidden');
                }));
                kModal.style.visibility = 'visible';
                document.body.classList.add('overflow-hidden');
            });
        });

        // Submit edit kendaraan (dari halaman detail)
        document.getElementById('kendaraan-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formEl = e.target;
            const submit = document.getElementById('btn-submit');
            const original = submit.textContent;
            submit.textContent = 'Menyimpan...';
            submit.disabled = true;
            const body = new FormData(formEl);
            try {
                const res = await fetch(formEl.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                if (res.status === 422) { alert('Mohon lengkapi data yang wajib diisi.'); submit.textContent = original; submit.disabled = false; return; }
                if (res.ok) { window.location.reload(); } else { alert('Terjadi kesalahan. Coba lagi.'); submit.textContent = original; submit.disabled = false; }
            } catch (err) { alert('Koneksi bermasalah.'); submit.textContent = original; submit.disabled = false; }
        });

        // Plat
        const platModal = document.getElementById('plat-modal');
        wireClose(platModal);
        document.querySelectorAll('[data-plat-modal]').forEach(b => b.addEventListener('click', () => openModal(platModal)));
        document.getElementById('plat-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const res = await fetch(platStoreUrl.replace('__ID__', kendaraanId), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: fd });
            if (res.ok || res.status === 422) { window.location.reload(); }
        });

        // Pajak
        const pajakModal = document.getElementById('pajak-modal');
        wireClose(pajakModal);
        document.querySelectorAll('[data-pajak-modal]').forEach(b => b.addEventListener('click', () => openModal(pajakModal)));
        document.getElementById('pajak-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const res = await fetch(pajakStoreUrl.replace('__ID__', kendaraanId), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: fd });
            if (res.ok || res.status === 422) { window.location.reload(); }
        });
        document.querySelectorAll('[data-delete-pajak]').forEach(b => {
            b.addEventListener('click', async () => {
                const pajakId = b.dataset.deletePajak;
                if (!confirm('Hapus pajak ' + b.dataset.pajakName + '?')) return;
                const res = await fetch(pajakDeleteBase.replace('__ID__', kendaraanId).replace('__PAJAK__', pajakId), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                if (res.ok) window.location.reload();
            });
        });

        // Approve izin
        document.querySelectorAll('[data-approve-izin]').forEach(b => {
            b.addEventListener('click', async () => {
                const izinId = b.dataset.approveIzin;
                const status = b.dataset.status;
                const body = new FormData();
                body.append('status_approval', status);
                const res = await fetch(izinApproveBase.replace('__ID__', kendaraanId).replace('__IZIN__', izinId), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                if (res.ok) window.location.reload();
            });
        });
        document.querySelectorAll('[data-delete-izin]').forEach(b => {
            b.addEventListener('click', async () => {
                const izinId = b.dataset.deleteIzin;
                if (!confirm('Hapus permohonan izin ini?')) return;
                const res = await fetch(izinDeleteBase.replace('__ID__', kendaraanId).replace('__IZIN__', izinId), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                if (res.ok) window.location.reload();
            });
        });
        @endif

        // Izin form (semua role)
        const izinModal = document.getElementById('izin-modal');
        wireClose(izinModal);
        document.querySelectorAll('[data-izin-modal]').forEach(b => b.addEventListener('click', () => openModal(izinModal)));
        document.getElementById('izin-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const res = await fetch(izinStoreUrl.replace('__ID__', kendaraanId), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: fd });
            if (res.status === 422) {
                alert('Mohon lengkapi data yang wajib diisi.');
                return;
            }
            if (res.ok) window.location.reload();
        });
    </script>
    @endpush

    @if ($isAdmin)
        @push('modals')
            @include('kendaraan._edit-modal')
        @endpush
    @endif
@endsection
