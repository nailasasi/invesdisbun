@extends('layouts.app')

@section('title', 'Detail Aset Barang')

@section('content')
    {{-- Navigasi Kembali --}}
    <div class="mb-4">
        <a href="{{ route('aset-barang.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-emerald-600">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Aset Barang
        </a>
    </div>

    {{-- Header Halaman & Action Buttons --}}
    <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Aset {{ $pegawai->nama_pegawai }}</h2>
            <p class="mt-1 text-sm text-slate-500">Daftar aset inventaris yang dipegang dan menjadi tanggung jawab pegawai.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            {{-- Grup SPPBI (Pill Container) --}}
            <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white p-1 shadow-xs">
                <a href="{{ route('aset-barang.sppbi.print', $pegawai->id_pegawai) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100" title="Cetak Format Standar">
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Cetak SPPBI
                </a>

                <span class="h-4 w-px bg-slate-200"></span>

                <a href="{{ route('aset-barang.sppbi.download.word', $pegawai->id_pegawai) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50" title="Download Word (.docx)">
                    <svg class="h-3.5 w-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Unduh Word
                </a>
            </div>

            {{-- Cetak Semua Label --}}
            <a href="{{ route('aset-barang.label.download', $pegawai->id_pegawai) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-1.5 text-xs font-bold text-amber-700 shadow-2xs transition hover:bg-amber-100" title="Unduh semua label aset pegawai dalam format Excel">
                <svg class="h-3.5 w-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                Cetak Semua Label
            </a>

            @if ($isAdminAset)
                {{-- Tombol Kelola SPPBI --}}
                <button type="button" id="btn-sppbi" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50/70 px-3.5 py-2 text-xs font-bold text-emerald-700 shadow-xs transition hover:bg-emerald-100">
                    <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Atur SPPBI
                </button>

                {{-- Tombol Utama Tambah Aset --}}
                <button type="button" id="btn-tambah" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah Aset
                </button>
            @endif
        </div>
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- Kartu Profil Pegawai --}}
    @php \Carbon\Carbon::setLocale('id'); @endphp
    <div class="mb-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
        <div class="flex flex-row items-center justify-between gap-6">
            {{-- Sisi Kiri: Avatar & Identitas --}}
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center">
                @php
                    $words = preg_split('/\s+/', trim((string) $pegawai->nama_pegawai));
                    $initOne = mb_substr($words[0] ?? '?', 0, 1);
                    $initTwo = isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0] ?? '', 1, 1);
                    $inisial = strtoupper($initOne . $initTwo);
                @endphp
                {{-- Avatar Kotak --}}
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-xl font-extrabold tracking-wide text-white shadow-md">
                    {{ $inisial }}
                </div>

                <div class="min-w-0">
                    <h3 class="break-words text-xl font-bold text-slate-900">{{ $pegawai->nama_pegawai }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $pegawai->jabatan ?? 'Staf' }} &bull; {{ $pegawai->skpd?->nama_skpd ?? 'Dinas Perkebunan' }}
                    </p>
                    <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-400">
                        <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Update SPPBI:</span>
                        <span class="font-semibold text-slate-600">{{ $pegawai->sppbiAktif?->tanggal_surat?->translatedFormat('d M Y') ?? 'Belum diatur' }}</span>
                    </p>
                </div>
            </div>

            {{-- Sisi Kanan: Kartu Statistik Ringkas (NIP & Total Aset) --}}
            <div class="inline-flex items-center gap-5 rounded-2xl border border-slate-100 bg-slate-50/60 px-6 py-4 shrink-0">
                <div class="min-w-0">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">NIP</span>
                    <span class="mt-1 block break-all font-mono text-sm font-bold text-slate-700">{{ $pegawai->nip ?? '-' }}</span>
                </div>

                <span class="h-8 w-px bg-slate-200"></span>

                <div class="min-w-0">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Aset</span>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold leading-none text-emerald-600">{{ $asetList->count() }}</span>
                        <span class="text-[10px] font-semibold text-slate-400">Unit Barang</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daftar aset --}}
    <div class="mt-6">
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Aset Barang Dipegang</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kartu Barang</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Merk</th>
                            @if ($isAdminAset)
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            @endif
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kondisi</th>
                            @if ($isAdminAset)
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($asetList as $i => $item)
                            @php $aset = $item->aset; @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $aset->barang->nama_barang ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nomor_kartu_barang ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->merk ?? '-' }}</td>
                                @if ($isAdminAset)
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->status_aset ?? '-' }}</td>
                                @endif
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @php
                                        $kondisiColor = match ($aset->kondisi) {
                                            'Baik' => 'bg-emerald-100 text-emerald-700',
                                            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                                            'Rusak Berat' => 'bg-red-100 text-red-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                                </td>
                                @if ($isAdminAset)
                                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                                        <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                            <a href="{{ route('aset-barang.cetak.label.single', $aset->id_aset) }}" target="_blank" title="Cetak Label" class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.01"/></svg>
                                            </a>
                                            <button type="button" data-delete-target="{{ $aset->id_aset }}" data-delete-name="{{ $aset->barang->nama_barang ?? $aset->nomor_kartu_barang }}" title="Hapus" class="flex h-7 w-7 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdminAset ? 8 : 5 }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada aset yang dipegang pegawai ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    @if ($isAdminAset)
        {{-- Modal Tambah / Edit Aset --}}
        <div id="aset-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="fixed inset-0" data-modal-close></div>
            <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h3 id="modal-title" class="text-lg font-semibold text-slate-900">Tambah Aset</h3>
                            <p id="modal-subtitle" class="mt-0.5 text-sm text-slate-500">Aset baru akan dipegang oleh {{ $pegawai->nama_pegawai }}.</p>
                        </div>
                        <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form id="aset-form" method="POST" action="{{ route('aset-barang.store', $pegawai->id_pegawai) }}" autocomplete="off">
                        @csrf
                        <input type="hidden" id="field-id" name="id" value="">
                        <div class="max-h-[90vh] space-y-5 overflow-y-auto px-6 py-6">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label for="field-pegawai" class="block text-sm font-medium text-slate-700">Pemegang <span class="text-red-500">*</span></label>
                                    <select id="field-pegawai" name="id_pegawai" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                        @foreach ($allPegawai as $p)
                                            <option value="{{ $p->id_pegawai }}" @selected($p->id_pegawai === $pegawai->id_pegawai)>{{ $p->nama_pegawai }}</option>
                                        @endforeach
                                    </select>
                                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_pegawai"></p>
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
                                    <input type="text" id="field-status" name="status_aset" required maxlength="50" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: aktif">
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

        {{-- Modal Konfirmasi Hapus --}}
        <div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="fixed inset-0" data-delete-close></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-slate-900">Usulkan Penghapusan Aset</h3>
                            <p class="mt-1 text-sm text-slate-500"><span id="delete-name" class="font-medium text-slate-700"></span> akan masuk antrean usulan penghapusan dan hilang dari daftar aset aktif. Pembatalan dapat dilakukan di halaman Penghapusan.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" data-delete-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                        <button type="button" id="btn-delete-confirm" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">Usulkan Hapus</button>
                    </div>
                </div>
        </div>

        {{-- Modal SPPBI --}}
        <div id="modal-sppbi" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div id="close-sppbi-backdrop" class="fixed inset-0"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Atur SPPBI</h3>
                            <p class="mt-0.5 text-sm text-slate-500">Nomor surat, tanggal, dan dokumen SPPBI {{ $pegawai->nama_pegawai }}.</p>
                        </div>
                        <button type="button" id="btn-close-sppbi" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form id="form-sppbi" enctype="multipart/form-data" autocomplete="off">
                        <div class="max-h-[90vh] space-y-5 overflow-y-auto px-6 py-6">
                            <div class="space-y-1.5">
                                <label for="field-sppbi-nomor" class="block text-sm font-medium text-slate-700">Nomor Surat <span class="text-red-500">*</span></label>
                                <input type="text" id="field-sppbi-nomor" name="nomor_surat" required maxlength="100" value="{{ $pegawai->sppbiAktif?->nomor_surat }}" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 005/SPPBI/2026">
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-sppbi-tanggal" class="block text-sm font-medium text-slate-700">Tanggal Surat <span class="text-red-500">*</span></label>
                                <input type="date" id="field-sppbi-tanggal" name="tanggal_surat" required value="{{ $pegawai->sppbiAktif?->tanggal_surat?->format('Y-m-d') ?? date('Y-m-d') }}" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-sppbi-file" class="block text-sm font-medium text-slate-700">Dokumen Bertanda Tangan (PDF/JPG/PNG, opsional)</label>
                                <input type="file" id="field-sppbi-file" name="file_dokumen" accept=".pdf,.jpg,.jpeg,.png" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                @if ($pegawai->sppbiAktif?->file_path)
                                    <div class="mt-2 flex items-center gap-2 text-xs text-emerald-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Berkas tersimpan:</span>
                                        <a href="{{ asset('storage/' . $pegawai->sppbiAktif->file_path) }}" target="_blank" class="font-bold underline hover:text-emerald-800">Lihat Berkas Terunggah</a>
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-sppbi-catatan" class="block text-sm font-medium text-slate-700">Catatan</label>
                                <textarea id="field-sppbi-catatan" name="catatan" rows="3" maxlength="500" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="Catatan opsional">{{ $pegawai->sppbiAktif?->catatan }}</textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                            <button type="button" id="btn-close-sppbi-2" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                            <button type="submit" id="btn-submit-sppbi" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                                Simpan SPPBI
                            </button>
                        </div>
                    </form>
                </div>
        </div>
    @endif

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const isAdminAset = @json($isAdminAset);

        const storeUrl = @json(route('aset-barang.store', $pegawai->id_pegawai));
        const updateUrl = @json(route('aset-barang.aset.update', ['aset' => '__ID__']));
        const sppbiUpdateUrl = @json(route('aset-barang.sppbi.update', $pegawai->id_pegawai));

        const modal = document.getElementById('aset-modal');
        const deleteModal = document.getElementById('delete-modal');
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

        if (form) {
            const btnTambah = document.getElementById('btn-tambah');
            if (btnTambah) {
                btnTambah.addEventListener('click', () => {
                    form.reset();
                    document.getElementById('field-id').value = '';
                    document.getElementById('field-pegawai').value = @json($pegawai->id_pegawai);
                    form.action = storeUrl;
                    document.getElementById('modal-title').textContent = 'Tambah Aset';
                    document.getElementById('modal-subtitle').textContent = 'Aset baru akan dipegang oleh ' + @json($pegawai->nama_pegawai) + '.';
                    document.getElementById('btn-submit').textContent = 'Simpan';
                    form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
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
                        document.getElementById('field-pegawai').value = data.id_pegawai ?? '';
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
                        document.getElementById('modal-subtitle').textContent = 'Perbarui data aset.';
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
        }

        const btnSppbi = document.getElementById('btn-sppbi');
        const modalSppbi = document.getElementById('modal-sppbi');
        const formSppbi = document.getElementById('form-sppbi');

        if (btnSppbi && modalSppbi) {
            btnSppbi.addEventListener('click', () => {
                modalSppbi.classList.remove('hidden');
                modalSppbi.classList.add('flex');
            });

            const closeSppbi = () => {
                modalSppbi.classList.add('hidden');
                modalSppbi.classList.remove('flex');
            };

            document.getElementById('btn-close-sppbi').addEventListener('click', closeSppbi);
            document.getElementById('close-sppbi-backdrop').addEventListener('click', closeSppbi);
            document.getElementById('btn-close-sppbi-2').addEventListener('click', closeSppbi);

            formSppbi.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btnSubmit = document.getElementById('btn-submit-sppbi');
                btnSubmit.disabled = true;
                btnSubmit.textContent = 'Menyimpan...';

                const formData = new FormData(formSppbi);
                try {
                    const res = await fetch(sppbiUpdateUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await res.json();
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Gagal memperbarui SPPBI');
                        btnSubmit.disabled = false;
                        btnSubmit.textContent = 'Simpan SPPBI';
                    }
                } catch (err) {
                    alert('Terjadi kesalahan jaringan.');
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = 'Simpan SPPBI';
                }
            });
        }

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
                btnConfirm.textContent = 'Memproses...';
                btnConfirm.disabled = true;
                try {
                    const res = await fetch(updateUrl.replace('__ID__', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        alert('Gagal mengajukan usulan penghapusan.');
                        btnConfirm.textContent = 'Usulkan Hapus';
                        btnConfirm.disabled = false;
                    }
                } catch (err) {
                    alert('Koneksi bermasalah.');
                    btnConfirm.textContent = 'Usulkan Hapus';
                    btnConfirm.disabled = false;
                }
            });
        }
    </script>
    @endpush
@endsection