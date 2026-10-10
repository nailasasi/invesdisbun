@extends('layouts.app')

@section('title', 'Detail Kendaraan')

@section('page-title', 'Detail Kendaraan')

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
            'Baik' => 'bg-disbun-100 text-disbun-800',
            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
            'Rusak Berat' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $platAktif = $kendaraan->platAktif;
        $pajakAktif = $kendaraan->pajakAktif;
        $statusBadge = match ($kendaraan->aset?->status_aset) {
            'aktif' => 'bg-disbun-100 text-disbun-800',
            'diusulkan_hapus' => 'bg-amber-100 text-amber-700',
            'dihapuskan' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    {{-- Info Kendaraan --}}
    <x-card>
        <div class="mb-6 flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
                <h3 class="text-base font-bold text-slate-800">Informasi Kendaraan</h3>
                <p class="text-xs text-slate-400">Data teknis dan legalitas kendaraan dinas</p>
            </div>
            @if ($isAdmin)
                <div class="flex items-center gap-2">
                    <button type="button" data-edit-kendaraan="{{ $kendaraan->id_kendaraan }}"
                            class="inline-flex items-center gap-1.5 rounded-2xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-disbun-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Kendaraan</span>
                    </button>
                </div>
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

    {{-- RIWAYAT MUTASI KENDARAAN --}}
    <x-card :padding="false" class="mt-6">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Riwayat Mutasi Kendaraan</h3>
                <p class="text-xs text-slate-400">Pergantian pemegang kendaraan beserta dokumen SPPKD &amp; BAST</p>
            </div>
</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Lama</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang Baru</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor Surat</th>
                        @if ($isAdmin)
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi Dokumen</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($riwayatMutasi as $rd)
                        @php
                            $pLamaTeks = $rd->pegawaiLama?->nama_pegawai
                                ?? (($rd->mutasi?->keterangan && preg_match('/Serah terima dari ([^\.]+)/', $rd->mutasi->keterangan, $m1)) ? $m1[1] : '-');
                            $nomorSurat = '';
                            if ($rd->mutasi?->keterangan && preg_match('/Nomor Surat:\s*([^\.]+)/', $rd->mutasi->keterangan, $m2)) {
                                $nomorSurat = trim($m2[1]);
                            }
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">
                                {{ $rd->mutasi?->tanggal_mutasi ? \Carbon\Carbon::parse($rd->mutasi->tanggal_mutasi)->format('d M Y') : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $pLamaTeks }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $rd->pegawaiBaru?->nama_pegawai ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $nomorSurat ?: '-' }}</td>
                            @if ($isAdmin)
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <a href="{{ route('kendaraan.mutasi.sppkd.download', [$kendaraan->id_kendaraan, $rd->id_mutasi]) }}"
                                       class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                        SPPKD
                                    </a>
                                    <a href="{{ route('kendaraan.mutasi.bast.download', [$kendaraan->id_kendaraan, $rd->id_mutasi]) }}"
                                       class="ml-1 inline-flex items-center rounded-lg bg-disbun-50 px-2.5 py-1.5 text-xs font-medium text-disbun-800 transition hover:bg-disbun-100">
                                        BAST
                                    </a>
                                </td>
                            @endif
</tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada riwayat mutasi kendaraan.</td>
                        </tr>
                    @endforelse
                    </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Riwayat Plat --}}
        <x-card :padding="false" class="min-w-0 lg:col-span-1">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-semibold text-slate-900">Riwayat Plat Nomor</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor Plat</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($kendaraan->riwayatPlat as $index => $plat)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ $plat->nomor_plat }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    @if ($plat->status === 'Aktif')
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-disbun-50 text-disbun-800">Aktif</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-500">Riwayat</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada riwayat plat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Pajak Kendaraan --}}
        <x-card :padding="false" class="min-w-0 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-semibold text-slate-900">Pajak Kendaraan</h3>
                @if ($isAdmin)
                    <button type="button" data-pajak-modal class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-disbun-800">
                        Kelola Pajak Kendaraan
                    </button>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-6 py-3">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <span>Filter Tahun</span>
                    <select id="pajak-filter-tahun" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-normal text-slate-700 shadow-sm focus:outline-none focus:border-emerald-500">
                        <option value="">Semua Tahun</option>
                        @foreach ($kendaraan->pajak->pluck('tahun')->filter()->map(fn ($t) => (int) $t)->unique()->sortDesc() as $tahun)
                            <option value="{{ $tahun }}">{{ $tahun }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <span>Filter Jenis Pajak</span>
                    <select id="pajak-filter-jenis" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-normal text-slate-700 shadow-sm focus:outline-none focus:border-emerald-500">
                        <option value="">Semua Jenis</option>
                        <option value="tahunan">Tahunan</option>
                        <option value="5-tahunan">5 Tahunan</option>
                    </select>
                </label>
            </div>
            <div class="overflow-x-auto">
                <table id="pajak-kendaraan" class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jatuh Tempo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis Pajak</th>
                            @if ($isAdmin)
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nominal</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Bukti</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($kendaraan->pajak as $pajak)
                            @php
                                $expired = $pajak->tanggal_berakhir && $pajak->tanggal_berakhir->isPast();
                            @endphp
                            <tr data-tahun="{{ $pajak->tahun }}" data-jenis="{{ $pajak->pajak_5_tahunan ? '5-tahunan' : 'tahunan' }}" class="transition hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm text-slate-700">{{ $pajak->jenis_pajak }}
                                    @if ($pajak->status === 'Aktif')
                                        <span class="ml-1 inline-flex items-center rounded-full bg-disbun-100 px-2 py-0.5 text-[10px] font-medium text-disbun-800">Aktif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($expired)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-700">{{ $pajak->tanggal_berakhir->format('d M Y') }}</span>
                                    @else
                                        <span class="text-slate-600">{{ $pajak->tanggal_berakhir?->format('d M Y') ?? '-' }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($pajak->pajak_5_tahunan)
                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">5 Tahunan</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-disbun-50 px-2.5 py-0.5 text-xs font-medium text-disbun-700">Tahunan</span>
                                    @endif
                                </td>
                                @if ($isAdmin)
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $pajak->nominal ? 'Rp ' . number_format((float) $pajak->nominal, 0, ',', '.') : '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $pajak->total_pajak ? 'Rp ' . number_format((float) $pajak->total_pajak, 0, ',', '.') : '-' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        @if ($pajak->bukti_pajak_path)
                                            <button type="button" data-lihat-bukti="{{ $pajak->id_pajak }}" data-bukti-nama="{{ $pajak->bukti_pajak_nama }}" class="inline-flex items-center rounded-lg bg-disbun-50 px-2.5 py-1.5 text-xs font-medium text-disbun-700 transition hover:bg-disbun-100">Lihat Bukti</button>
                                        @else
                                            <span class="text-xs text-slate-400">Belum ada</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button type="button"
                                                data-edit-pajak="{{ $pajak->id_pajak }}"
                                                data-jenis="{{ $pajak->jenis_pajak }}"
data-tahun="{{ $pajak->tahun }}"
                                                data-is5tahunan="{{ $pajak->pajak_5_tahunan ? '1' : '0' }}"
                                                data-bayar="{{ $pajak->tanggal_bayar?->format('Y-m-d') }}"
                                                data-berakhir="{{ $pajak->tanggal_berakhir?->format('Y-m-d') }}"
                                                data-nominal="{{ $pajak->nominal }}"
                                                data-total="{{ $pajak->total_pajak }}"
                                                data-bukti="{{ $pajak->bukti_pajak_path ? '1' : '0' }}"
                                                data-bukti-nama="{{ $pajak->bukti_pajak_nama }}"
                                                class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">Edit</button>
                                            <button type="button" data-delete-pajak="{{ $pajak->id_pajak }}" data-pajak-name="{{ $pajak->jenis_pajak }}" class="inline-flex items-center rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">Hapus</button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 7 : 3 }}" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada data pajak.</td>
                            </tr>
                        @endforelse
                        <tr id="pajak-filter-empty" class="hidden">
                            <td colspan="{{ $isAdmin ? 7 : 3 }}" class="px-6 py-10 text-center text-sm text-slate-400">Tidak ada data pajak yang cocok dengan filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    @if ($isAdmin)
    {{-- MODAL PAJAK --}}
    <div id="pajak-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <h3 id="pajak-modal-title" class="text-lg font-semibold text-slate-900">Kelola Pajak Kendaraan</h3>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form id="pajak-form" method="POST" class="space-y-4 px-6 py-6">
                @csrf
                <input type="hidden" name="_method" id="pajak-method" value="">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="pajak-jenis" class="block text-sm font-medium text-slate-700">Jenis <span class="text-red-500">*</span></label>
                        <input type="text" id="pajak-jenis" name="jenis_pajak" required value="PKB" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="text-xs text-slate-500">Contoh: PKB, SWDKLLJ, Pajak Bumi.</p>
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
                        <label for="pajak-berakhir" class="block text-sm font-medium text-slate-700">Tanggal Jatuh Tempo <span class="text-red-500">*</span></label>
                        <input type="date" id="pajak-berakhir" name="tanggal_berakhir" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="text-xs text-slate-500">Masukkan tanggal jatuh tempo sesuai bukti pajak.</p>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_berakhir"></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="pajak-nominal" class="block text-sm font-medium text-slate-700">Nominal Pajak (Rp)</label>
                        <input type="number" id="pajak-nominal" name="nominal" min="0" step="0.01" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 1500000">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nominal"></p>
                    </div>
                    <div class="space-y-1.5">
                        <label for="pajak-total" class="block text-sm font-medium text-slate-700">Total Pajak (Rp)</label>
                        <input type="number" id="pajak-total" name="total_pajak" min="0" step="0.01" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 2100000">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="total_pajak"></p>
                    </div>
                </div>
                <div id="pajak-5thn-block" class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" id="pajak-5tahunan-ganti" name="pajak_5_tahunan" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            Pajak 5 Tahunan / Ganti Plat
                        </label>
                    </div>
                    <div id="plat-baru-wrap" class="space-y-1.5 hidden">
                        <label for="plat-baru" class="block text-sm font-medium text-slate-700">Nomor Plat Baru <span class="text-red-500">*</span></label>
                        <input type="text" id="plat-baru" name="nomor_plat_baru" maxlength="20" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: B 1234 ABC">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_plat_baru"></p>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label for="pajak-bukti" class="block text-sm font-medium text-slate-700">Bukti Pembayaran Pajak <span id="pajak-bukti-wajib" class="text-red-500">*</span></label>
                    <input type="file" id="pajak-bukti" name="bukti_pajak" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm file:mr-3 file:rounded-lg file:border-0 file:bg-disbun-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-disbun-700 hover:file:bg-disbun-100 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <p id="pajak-bukti-hint" class="text-xs text-slate-500">Wajib diunggah. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.</p>
                    <p id="pajak-bukti-existing" class="hidden text-xs font-medium text-slate-600"></p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="bukti_pajak"></p>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-disbun-800">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL PRATINJAU BUKTI PAJAK --}}
    <div id="bukti-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900">Pratinjau Bukti Pajak</h3>
                    <p id="bukti-nama" class="mt-0.5 truncate text-xs text-slate-500"></p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 px-5 py-4">
                <div id="bukti-image-wrap" class="hidden">
                    <img id="bukti-image" src="" alt="Pratinjau bukti pajak" class="mx-auto max-h-[70vh] w-auto max-w-full rounded-xl border border-slate-200 bg-white object-contain">
                </div>
                <div id="bukti-pdf-wrap" class="hidden">
                    <iframe id="bukti-pdf" src="" title="Pratinjau PDF bukti pajak" class="h-[70vh] w-full rounded-xl border border-slate-200 bg-white"></iframe>
                    <p class="mt-2 text-center text-xs text-slate-500">Jika pratinjau tidak tampil, gunakan tombol Download di bawah.</p>
                </div>
                <div id="bukti-fallback-wrap" class="hidden py-10 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    <p id="bukti-fallback-nama" class="mt-3 text-sm font-medium text-slate-700"></p>
                    <p class="mt-1 text-xs text-slate-500">Pratinjau tidak didukung untuk jenis berkas ini. Silakan unduh untuk membuka.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Tutup</button>
                <a id="bukti-download" href="#" class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-disbun-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                    Download
                </a>
            </div>
        </div>
    </div>
    @endif

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const kendaraanId = {{ $kendaraan->id_kendaraan }};
        const createDataUrl = @json(route('kendaraan.create'));
        const editUrl = @json(route('kendaraan.edit', ['kendaraan' => '__ID__']));
        const updateUrl = @json(route('kendaraan.update', ['kendaraan' => '__ID__']));
        const pajakStoreUrl = @json(route('kendaraan.pajak.store', ['kendaraan' => '__ID__']));
        const pajakUpdateBase = @json(route('kendaraan.pajak.update', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));
        const pajakDeleteBase = @json(route('kendaraan.pajak.destroy', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));
        const pajakBuktiBase = @json(route('kendaraan.pajak.bukti', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));
        const pajakPreviewBase = @json(route('kendaraan.pajak.preview', ['kendaraan' => '__ID__', 'pajak' => '__PAJAK__']));

        let loadedDropdowns = false;

        function openModal(el) { el.classList.remove('hidden'); el.classList.add('flex'); document.body.classList.add('overflow-hidden'); }
        function closeModal(el) { el.classList.add('hidden'); el.classList.remove('flex'); document.body.classList.remove('overflow-hidden'); }
        function wireClose(modal) {
            modal.querySelectorAll('[data-modal-close]').forEach(x => x.addEventListener('click', () => closeModal(modal)));
        }

        // Filter tabel Pajak Kendaraan (hanya tampilan; tidak mengubah data).
        const pajakFilterTahun = document.getElementById('pajak-filter-tahun');
        const pajakFilterJenis = document.getElementById('pajak-filter-jenis');
        const pajakRows = Array.from(document.querySelectorAll('#pajak-kendaraan tbody tr[data-tahun]'));
        const pajakFilterEmpty = document.getElementById('pajak-filter-empty');

        if (pajakFilterTahun && pajakFilterJenis) {
            const applyPajakFilter = () => {
                let visible = 0;
                pajakRows.forEach(row => {
                    const okTahun = !pajakFilterTahun.value || String(row.dataset.tahun) === pajakFilterTahun.value;
                    const okJenis = !pajakFilterJenis.value || row.dataset.jenis === pajakFilterJenis.value;
                    const show = okTahun && okJenis;
                    row.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                if (pajakFilterEmpty) {
                    pajakFilterEmpty.classList.toggle('hidden', !(pajakRows.length > 0 && visible === 0));
                }
            };
            pajakFilterTahun.addEventListener('change', applyPajakFilter);
            pajakFilterJenis.addEventListener('change', applyPajakFilter);
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
                const pemegangValue = document.getElementById('field-pemegang-value');
                if (pemegangValue) pemegangValue.textContent = d.pemegang || 'Belum ada pemegang';
                document.getElementById('field-keterangan').value = d.keterangan ?? '';
                document.getElementById('field-rangka').value = d.nomor_rangka ?? '';
                document.getElementById('field-mesin').value = d.nomor_mesin ?? '';
                document.getElementById('field-merk').value = d.merk ?? '';
                document.getElementById('field-tipe').value = d.tipe ?? '';
                document.getElementById('field-nilai').value = d.nilai_perolehan ?? '';
                document.getElementById('field-kondisi').value = d.kondisi ?? '';
                document.getElementById('field-status').value = d.status_aset ?? '';
                document.getElementById('field-tgl-pengadaan').value = d.tanggal_pengadaan ?? '';
                document.getElementById('field-tgl-perolehan').value = d.tanggal_perolehan ?? '';
                document.getElementById('field-tgl-habis').value = d.tanggal_habis_pakai ?? '';
                document.getElementById('field-plat').value = d.nomor_plat ?? '';
                document.getElementById('modal-title').textContent = 'Edit Kendaraan';
                const pAdd = document.getElementById('block-pemegang-add');
                const pEdit = document.getElementById('block-pemegang-edit');
                const pemegangInputEl = document.getElementById('field-pemegang-input');
                if (pemegangInputEl) pemegangInputEl.value = '';
                if (pAdd) pAdd.classList.add('hidden');
                if (pEdit) pEdit.classList.remove('hidden');
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

        // Pajak
        const pajakModal = document.getElementById('pajak-modal');
        wireClose(pajakModal);
        const pajakForm = document.getElementById('pajak-form');
        const pajakModalTitle = document.getElementById('pajak-modal-title');
        const pajakMethod = document.getElementById('pajak-method');
        const cb5Tahunan = document.getElementById('pajak-5tahunan-ganti');
        const platBaruWrap = document.getElementById('plat-baru-wrap');
        const platBaruInput = document.getElementById('plat-baru');
        const block5Tahunan = document.getElementById('pajak-5thn-block');
        const buktiInput = document.getElementById('pajak-bukti');
        const buktiWajib = document.getElementById('pajak-bukti-wajib');
        const buktiHint = document.getElementById('pajak-bukti-hint');
        const buktiExisting = document.getElementById('pajak-bukti-existing');

        function togglePlatBaru() {
            const on = cb5Tahunan.checked;
            platBaruWrap.classList.toggle('hidden', !on);
            platBaruInput.required = on;
            if (!on) platBaruInput.value = '';
        }
        cb5Tahunan.addEventListener('change', togglePlatBaru);

        function resetPajakForm() {
            pajakForm.reset();
            pajakMethod.value = '';
            pajakForm.action = pajakStoreUrl.replace('__ID__', kendaraanId);
            pajakModalTitle.textContent = 'Kelola Pajak Kendaraan';
            document.getElementById('pajak-jenis').value = 'PKB';
            document.getElementById('pajak-tahun').disabled = false;
            document.getElementById('pajak-berakhir').disabled = false;
            block5Tahunan.classList.remove('hidden');
            cb5Tahunan.disabled = false;
            buktiInput.required = true;
            buktiWajib.classList.remove('hidden');
            buktiHint.textContent = 'Wajib diunggah. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.';
            buktiExisting.classList.add('hidden');
            buktiExisting.textContent = '';
            togglePlatBaru();
        }

        document.querySelectorAll('[data-pajak-modal]').forEach(b => b.addEventListener('click', () => {
            resetPajakForm();
            openModal(pajakModal);
        }));

        document.querySelectorAll('[data-edit-pajak]').forEach(btn => {
            btn.addEventListener('click', () => {
                const d = btn.dataset;
                resetPajakForm();
                pajakModalTitle.textContent = 'Edit Pajak Kendaraan';
                pajakForm.action = pajakUpdateBase.replace('__ID__', kendaraanId).replace('__PAJAK__', d.editPajak);
                pajakMethod.value = 'PUT';

                document.getElementById('pajak-jenis').value = d.jenis ?? '';
                // Tahun awal dihitung dari tanggal jatuh tempo (TIDAK dari nilai
                // tersimpan): Tahunan = tahun(tanggal_berakhir) - 1, 5 Tahunan
                // = tahun(tanggal_berakhir) - 5. Fallback ke nilai tersimpan
                // hanya bila tanggal jatuh tempo kosong.
                const berakhirY = d.berakhir ? parseInt(d.berakhir.slice(0, 4), 10) : NaN;
                const tahunAwal = Number.isInteger(berakhirY) ? berakhirY - (d.is5tahunan === '1' ? 5 : 1) : (d.tahun ?? '');
                document.getElementById('pajak-tahun').value = tahunAwal ?? '';
                document.getElementById('pajak-berakhir').value = d.berakhir ?? '';
                document.getElementById('pajak-bayar').value = d.bayar ?? '';
                document.getElementById('pajak-nominal').value = d.nominal ?? '';
                document.getElementById('pajak-total').value = d.total ?? '';

                // Seluruh field pajak dapat dikoreksi di sini (jenis, tahun,
                // tanggal bayar/jatuh tempo, nominal, total, bukti). Blok
                // 5 Tahunan/Ganti Plat sengaja tidak ditampilkan saat edit:
                // perubahan ke/dari 5 tahunan diatur lewat Tambah atau Hapus.
                block5Tahunan.classList.add('hidden');

                buktiInput.required = false;
                buktiWajib.classList.add('hidden');
                buktiHint.textContent = 'Biarkan kosong bila tidak mengganti berkas. Format JPG, PNG, PDF, DOC/DOCX, atau XLS/XLSX. Maksimal 5 MB.';
                if (d.bukti === '1' && d.buktiNama) {
                    buktiExisting.textContent = 'Bukti saat ini: ' + d.buktiNama;
                    buktiExisting.classList.remove('hidden');
                }

                openModal(pajakModal);
            });
        });

        pajakForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const res = await fetch(pajakForm.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: fd });
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

        // Pratinjau bukti pajak (modal, bukan unduh langsung).
        const buktiModal = document.getElementById('bukti-modal');
        if (buktiModal) {
            wireClose(buktiModal);
            const buktiImageWrap = document.getElementById('bukti-image-wrap');
            const buktiImage = document.getElementById('bukti-image');
            const buktiPdfWrap = document.getElementById('bukti-pdf-wrap');
            const buktiPdf = document.getElementById('bukti-pdf');
            const buktiFallbackWrap = document.getElementById('bukti-fallback-wrap');
            const buktiFallbackNama = document.getElementById('bukti-fallback-nama');
            const buktiNamaEl = document.getElementById('bukti-nama');
            const buktiDownload = document.getElementById('bukti-download');

            function resetBuktiPreview() {
                buktiImage.removeAttribute('src');
                buktiPdf.removeAttribute('src');
                buktiImageWrap.classList.add('hidden');
                buktiPdfWrap.classList.add('hidden');
                buktiFallbackWrap.classList.add('hidden');
            }

            document.querySelectorAll('[data-lihat-bukti]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.lihatBukti;
                    const nama = btn.dataset.buktiNama || 'Bukti pajak';
                    const ext = (nama.split('.').pop() || '').toLowerCase();
                    const previewUrl = pajakPreviewBase.replace('__ID__', kendaraanId).replace('__PAJAK__', id);
                    const downloadUrl = pajakBuktiBase.replace('__ID__', kendaraanId).replace('__PAJAK__', id);

                    resetBuktiPreview();
                    buktiNamaEl.textContent = nama;
                    buktiDownload.setAttribute('href', downloadUrl);

                    if (['jpg', 'jpeg', 'png'].includes(ext)) {
                        buktiImage.src = previewUrl;
                        buktiImageWrap.classList.remove('hidden');
                    } else if (ext === 'pdf') {
                        buktiPdf.src = previewUrl;
                        buktiPdfWrap.classList.remove('hidden');
                    } else {
                        buktiFallbackNama.textContent = nama;
                        buktiFallbackWrap.classList.remove('hidden');
                    }

                    openModal(buktiModal);
                });
            });

            buktiModal.querySelectorAll('[data-modal-close]').forEach(el => {
                el.addEventListener('click', resetBuktiPreview);
            });
        }
        @endif
    </script>
    @endpush

    @if ($isAdmin)
        @push('modals')
            @include('kendaraan._edit-modal')
        @endpush
    @endif
@endsection
