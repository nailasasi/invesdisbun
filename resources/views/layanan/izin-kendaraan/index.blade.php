@extends('layouts.app')

@section('title', 'Surat Izin Kendaraan Dinas')

@section('page-title', 'Surat Izin / Tugas Kendaraan Dinas')

@section('breadcrumb', 'Layanan › Izin Kendaraan Dinas')

@section('content')
<div class="space-y-6">

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- ================================================================== --}}
    {{-- KARTU 1 · INFORMASI & PANDUAN (full-width, paling atas)          --}}
    {{-- ================================================================== --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/70 px-6 py-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a1 1 0 01-1 1h-2a1 1 0 01-1-1v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900">Informasi &amp; Panduan Pengajuan</h2>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">Alur Resmi</span>
            </div>
            <p class="flex items-center gap-2 text-xs text-slate-500">
                <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Pastikan data diisi sesuai SPT (Surat Perintah Tugas)
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 px-6 pb-6 pt-4 md:grid-cols-3">
            <div class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <p class="flex items-center gap-2 text-sm font-bold text-slate-800">1 · Status Menunggu</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Pengajuan tersimpan dengan status <span class="font-semibold text-amber-700">"Menunggu"</span> verifikasi.</p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5"/></svg>
                </span>
                <div>
                    <p class="flex items-center gap-2 text-sm font-bold text-slate-800">2 · Word (.docx)</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Setelah disetujui Admin Aset, dokumen <span class="font-semibold text-emerald-700">Word (.docx)</span> dapat diunduh.</p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <div>
                    <p class="flex items-center gap-2 text-sm font-bold text-slate-800">3 · Cek Jadwal</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Pastikan tanggal berangkat tidak bentrok dengan pemakaian kendaraan lain.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- KARTU 2 · FORMULIR PENGAJUAN                                    --}}
    {{-- ================================================================== --}}
    <div class="rounded-3xl border border-slate-200/80 bg-white shadow-soft">
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="text-lg font-bold text-slate-900">Formulir Pengajuan Izin Kendaraan Dinas</h2>
            <p class="mt-0.5 text-sm text-slate-500">Ajukan pemakaian kendaraan dinas untuk keperluan tugas / perjalanan dinas.</p>
        </div>

        <form method="POST" action="{{ route('layanan.izin-kendaraan.store') }}" class="p-6">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-select
                        name="id_kendaraan"
                        label="Pilih Kendaraan Dinas"
                        :options="$kendaraanOptions"
                        placeholder="-- Pilih kendaraan --"
                        required
                        help="Hanya kendaraan dinas berstatus aktif yang ditampilkan."
                    />
                </div>

                <x-input name="nama_pengemudi" label="Nama Pengemudi" placeholder="Nama pengemudi kendaraan" required />

                <x-select
                    name="id_pengurus_barang"
                    label="Pengurus Barang (Penyerah)"
                    :options="$pengurusOptions"
                    placeholder="-- Pilih pengurus barang --"
                    required
                />

                <div class="sm:col-span-2">
                    <x-input name="tujuan" label="Tujuan Perjalanan Dinas" placeholder="contoh: Dinas ke Dinas Perkebunan Provinsi" required />
                </div>

                <x-select
                    name="durasi"
                    label="Waktu / Durasi"
                    :options="[
                        'Setengah Hari' => 'Setengah Hari',
                        '1 Hari' => '1 Hari',
                        '2 Hari' => '2 Hari',
                        '3 Hari' => '3 Hari',
                        '1 Minggu' => '1 Minggu',
                    ]"
                    placeholder="-- Pilih durasi --"
                    required
                />

                <x-input name="tanggal_berangkat" type="date" label="Tanggal Berangkat" required />
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="reset"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-2.5 text-sm font-bold text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    Batal
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-b from-emerald-500 to-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 transition hover:brightness-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    Simpan &amp; Ajukan Surat
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                </button>
            </div>
        </form>
    </div>

    {{-- ================================================================== --}}
    {{-- BAGIAN BAWAH · RIWAYAT & STATUS PENGAJUAN                       --}}
    {{-- ================================================================== --}}
    <div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Riwayat &amp; Status Pengajuan Izin Kendaraan</h2>
                <p class="text-xs text-slate-400">{{ $isAdminAset ? 'Seluruh pengajuan dari semua pegawai.' : 'Menampilkan pengajuan milik Anda.' }}</p>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200/80 bg-white shadow-soft">
            <div class="overflow-x-auto p-2">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                            <th class="px-4 py-3.5 font-semibold">No</th>
                            @if ($isAdminAset)
                                <th class="px-4 py-3.5 font-semibold">Pengaju</th>
                            @endif
                            <th class="px-4 py-3.5 font-semibold">Kendaraan</th>
                            <th class="px-4 py-3.5 font-semibold">Tanggal Pakai</th>
                            <th class="px-4 py-3.5 font-semibold">Tujuan</th>
                            <th class="px-4 py-3.5 font-semibold">Status</th>
                            <th class="px-4 py-3.5 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($izinList as $izin)
                            @php
                                $badge = match ($izin->status_approval) {
                                    'Disetujui' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'Selesai' => 'bg-teal-50 text-teal-700 ring-teal-200',
                                    'Ditolak' => 'bg-red-50 text-red-700 ring-red-200',
                                    default => 'bg-amber-50 text-amber-700 ring-amber-200',
                                };
                                $namaKendaraan = $izin->kendaraan?->aset?->barang?->nama_barang ?: 'Kendaraan';
                                $plat = $izin->kendaraan?->platAktif?->nomor_plat ?: '-';
                                $pengemudiSesuaiPengaju = $izin->nama_pengemudi
                                    && mb_strtolower(trim((string) $izin->nama_pengemudi)) === mb_strtolower(trim((string) $izin->pengaju?->nama_pegawai));
                                $bisaKembalikan = $izin->status_approval === 'Disetujui'
                                    && ($isAdminAset || $izin->id_pegawai_pengaju === auth()->user()?->pegawai?->id_pegawai);
                                $bisaLihatBukti = $isAdminAset && $izin->status_approval === 'Selesai';
                            @endphp
                            <tr class="transition hover:bg-slate-50/60">
                                <td class="px-4 py-4 text-slate-500">{{ $loop->iteration }}</td>
                                @if ($isAdminAset)
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-800">{{ $izin->pengaju?->nama_pegawai ?? '-' }}</p>
                                        <p class="text-xs text-slate-400">{{ $izin->pengaju?->jabatan ?? '-' }}</p>
                                    </td>
                                @endif
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800">{{ $namaKendaraan }}</p>
                                    <p class="text-xs text-slate-400">{{ $plat }}</p>

                                    <div class="mt-2 border-t border-dashed border-slate-100 pt-2">
                                        @if ($pengemudiSesuaiPengaju)
                                            <p class="inline-flex items-center gap-1 text-xs text-slate-400">
                                                <svg class="h-3.5 w-3.5 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                <span class="font-medium text-slate-500">Pengemudi:</span>
                                                <span class="font-medium text-slate-500">Sesuai Pengaju</span>
                                            </p>
                                        @elseif ($izin->nama_pengemudi)
                                            <p class="flex items-center gap-1.5 text-xs">
                                                <span class="font-medium text-slate-500">Pengemudi:</span>
                                                <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-1.5 py-0.5 font-semibold text-sky-700 ring-1 ring-inset ring-sky-200">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                    {{ $izin->nama_pengemudi }}
                                                </span>
                                            </p>
                                        @else
                                            <p class="flex items-center gap-1.5 text-xs">
                                                <span class="font-medium text-slate-500">Pengemudi:</span>
                                                <span class="text-slate-400">-</span>
                                            </p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-slate-700">{{ $izin->tanggal_berangkat?->format('d M Y') ?? '-' }}</p>
                                    @if ($izin->durasi)
                                        <p class="text-xs text-slate-400">{{ $izin->durasi }}</p>
                                    @endif
                                    @if ($izin->status_approval === 'Selesai' && $izin->waktu_pengembalian)
                                        <p class="mt-0.5 text-xs font-medium text-teal-600">
                                            Dikembalikan {{ $izin->waktu_pengembalian->format('d M Y, H:i') }}
                                        </p>
                                    @endif
                                </td>
                                <td class="max-w-[220px] truncate px-4 py-4 text-slate-600" title="{{ $izin->tujuan }}">{{ $izin->tujuan }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $badge }}">
                                        {{ $izin->status_approval ?? 'Menunggu' }}
                                    </span>
                                    @if ($izin->status_approval === 'Ditolak' && $izin->alasan_penolakan)
                                        <div class="mt-2 flex max-w-[220px] items-start gap-1.5 rounded-lg bg-red-50/70 px-2.5 py-1.5 text-[11px] leading-snug text-red-600"
                                             title="{{ $izin->alasan_penolakan }}">
                                            <svg class="mt-0.5 h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                            <span class="break-words font-medium">
                                                {{ $izin->alasan_penolakan }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($izin->status_approval === 'Disetujui')
                                            <a href="{{ route('layanan.izin-kendaraan.download', $izin->id_izin) }}"
                                               class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                               title="Unduh surat izin (.docx)">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                                                Unduh Surat
                                            </a>
                                        @endif

                                        @if ($bisaKembalikan)
                                            <button type="button"
                                                    data-open-modal="modalSelesai-{{ $izin->id_izin }}"
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700 transition hover:bg-orange-100"
                                                    title="Konfirmasi pengembalian kendaraan">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                                Kembalikan Kendaraan
                                            </button>
                                        @endif

                                        @if ($bisaLihatBukti)
                                            <button type="button"
                                                    data-open-modal="modalBukti-{{ $izin->id_izin }}"
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-700 transition hover:bg-teal-100"
                                                    title="Lihat detail & bukti pengembalian">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                Lihat Bukti
                                            </button>
                                        @endif

                                        @if ($isAdminAset && ($izin->status_approval ?? 'Menunggu') === 'Menunggu')
                                            <form method="POST" action="{{ route('layanan.izin-kendaraan.status', $izin->id_izin) }}" class="inline-flex">
                                                @csrf
                                                <input type="hidden" name="status_approval" value="Disetujui">
                                                <button type="submit"
                                                    class="inline-flex items-center rounded-xl bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                                    title="Setujui">
                                                    Setujui
                                                </button>
                                            </form>
<button type="button"
                                                                    data-open-modal="modalTolak-{{ $izin->id_izin }}"
                                                                    class="inline-flex items-center rounded-xl bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100"
                                                                    title="Tolak pengajuan (isi alasan)">
                                                                Tolak
                                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdminAset ? 7 : 6 }}" class="px-4 py-12 text-center text-sm text-slate-400">
                                    Belum ada pengajuan izin kendaraan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('modals')
        @foreach ($izinList as $izin)
            @php
                $bisaKembalikan = $izin->status_approval === 'Disetujui'
                    && ($isAdminAset || $izin->id_pegawai_pengaju === auth()->user()?->pegawai?->id_pegawai);
                $bisaLihatBukti = $isAdminAset && $izin->status_approval === 'Selesai';
                $bisaTolak = $isAdminAset && ($izin->status_approval ?? 'Menunggu') === 'Menunggu';
            @endphp

            @if ($bisaKembalikan)
                <div id="modalSelesai-{{ $izin->id_izin }}"
                     class="fixed inset-0 z-50 hidden items-center justify-center p-4"
                     data-modal>
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-close></div>

                    <div class="relative w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-orange-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                </span>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Konfirmasi Pengembalian Kendaraan</h3>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ $izin->kendaraan?->aset?->barang?->nama_barang ?: 'Kendaraan' }}
                                        @if ($izin->kendaraan?->platAktif?->nomor_plat)
                                            · {{ $izin->kendaraan->platAktif->nomor_plat }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <button type="button" data-close class="text-slate-400 transition hover:text-slate-600" title="Tutup">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('layanan.izin-kendaraan.selesai', $izin->id_izin) }}"
                              enctype="multipart/form-data" class="mt-5 space-y-4">
                            @csrf

                            <div>
                                <label for="catatan-{{ $izin->id_izin }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Catatan Kondisi Kendaraan
                                </label>
                                <textarea id="catatan-{{ $izin->id_izin }}" name="catatan_kondisi" rows="3"
                                          placeholder="Contoh: Kondisi baik, bersih, bensin aman"
                                          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"></textarea>
                            </div>

                            <div>
                                <label for="foto-{{ $izin->id_izin }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Foto Bukti Kondisi <span class="font-normal text-slate-400">(opsional)</span>
                                </label>
                                <input type="file" id="foto-{{ $izin->id_izin }}" name="foto_pengembalian" accept="image/jpeg,image/png"
                                       class="block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:bg-slate-100">
                            </div>

                            <div>
                                <label for="waktu-{{ $izin->id_izin }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Waktu Pengembalian <span class="font-normal text-slate-400">(opsional)</span>
                                </label>
                                <input type="datetime-local" id="waktu-{{ $izin->id_izin }}" name="waktu_pengembalian"
                                       value="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button type="button" data-close
                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    Batal
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-b from-orange-500 to-orange-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-orange-600/30 transition hover:brightness-105">
                                    Konfirmasi Selesai
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if ($bisaLihatBukti)
                <div id="modalBukti-{{ $izin->id_izin }}"
                     class="fixed inset-0 z-50 hidden items-center justify-center p-4"
                     data-modal>
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-close></div>

                    <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </span>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Detail Pengembalian</h3>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ $izin->kendaraan?->aset?->barang?->nama_barang ?: 'Kendaraan' }}
                                        @if ($izin->kendaraan?->platAktif?->nomor_plat)
                                            · {{ $izin->kendaraan->platAktif->nomor_plat }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <button type="button" data-close class="text-slate-400 transition hover:text-slate-600" title="Tutup">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-xs text-slate-400">Pemohon / Penyerah</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ $izin->pengaju?->nama_pegawai ?? '-' }}</p>
                                <p class="text-xs text-slate-400">{{ $izin->pengaju?->jabatan ?? '' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-xs text-slate-400">Pengemudi</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ $izin->nama_pengemudi ?: '-' }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-xs text-slate-400">Waktu Pengembalian Riil</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-800">
                                    {{ $izin->waktu_pengembalian?->format('d M Y, H:i') ?? '-' }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-xs text-slate-400">Status</p>
                                <p class="mt-1">
                                    <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-700 ring-1 ring-inset ring-teal-200">
                                        Selesai
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="mb-1.5 text-sm font-semibold text-slate-700">Catatan Kondisi Kendaraan</p>
                            @if ($izin->catatan_pengembalian)
                                <p class="rounded-xl border border-slate-100 bg-slate-50/60 px-3.5 py-2.5 text-sm leading-relaxed text-slate-600">
                                    {{ $izin->catatan_pengembalian }}
                                </p>
                            @else
                                <p class="rounded-xl border border-dashed border-slate-200 px-3.5 py-2.5 text-xs text-slate-400">
                                    Tidak ada catatan kondisi yang diinput pemohon.
                                </p>
                            @endif
                        </div>

                        <div class="mt-4">
                            <p class="mb-1.5 text-sm font-semibold text-slate-700">Foto Bukti Fisik</p>
                            @if ($izin->foto_pengembalian && \Illuminate\Support\Facades\Storage::disk('public')->exists($izin->foto_pengembalian))
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($izin->foto_pengembalian) }}"
                                     alt="Foto bukti pengembalian"
                                     class="w-full rounded-xl object-cover ring-1 ring-inset ring-slate-200">
                            @else
                                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-xs text-slate-400">
                                    Tidak ada foto bukti fisik yang di-upload.
                                </div>
                            @endif
                        </div>

                        <div class="mt-5 flex items-center justify-end">
                            <button type="button" data-close
                                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($bisaTolak)
                <div id="modalTolak-{{ $izin->id_izin }}"
                     class="fixed inset-0 z-50 hidden items-center justify-center p-4"
                     data-modal>
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-close></div>

                    <div class="relative w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.07 14.02a2 2 0 001.71 3h16.14a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                </span>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Tolak Pengajuan</h3>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ $izin->kendaraan?->aset?->barang?->nama_barang ?: 'Kendaraan' }}
                                        @if ($izin->kendaraan?->platAktif?->nomor_plat)
                                            · {{ $izin->kendaraan->platAktif->nomor_plat }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <button type="button" data-close class="text-slate-400 transition hover:text-slate-600" title="Tutup">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('layanan.izin-kendaraan.status', $izin->id_izin) }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="status_approval" value="Ditolak">

                            <div>
                                <label for="alasan-{{ $izin->id_izin }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Alasan Penolakan
                                </label>
                                <textarea id="alasan-{{ $izin->id_izin }}" name="alasan_penolakan" rows="4" required
                                          placeholder="Contoh: Kendaraan sedang diperbaiki / tanggal sudah terpakai..."
                                          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20"></textarea>
                                <p class="mt-1 text-xs text-slate-400">Alasan ini akan tampil pada riwayat pengajuan agar pemohon dapat membacanya kembali.</p>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button type="button" data-close
                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    Batal
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-b from-red-500 to-red-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-red-600/30 transition hover:brightness-105">
                                    Kirim Penolakan
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.07 14.02a2 2 0 001.71 3h16.14a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach
    @endpush

    @push('scripts')
        <script>
            document.querySelectorAll('[data-open-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var modal = document.getElementById(button.dataset.openModal);
                    if (!modal) return;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            document.querySelectorAll('[data-modal]').forEach(function (modal) {
                function close() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                modal.querySelectorAll('[data-close]').forEach(function (el) {
                    el.addEventListener('click', close);
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    document.querySelectorAll('[data-modal]').forEach(function (modal) {
                        if (!modal.classList.contains('hidden')) {
                            modal.classList.add('hidden');
                            modal.classList.remove('flex');
                        }
                    });
                }
            });
        </script>
    @endpush
</div>
@endsection