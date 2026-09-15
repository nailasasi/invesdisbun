@extends('layouts.app')

@section('title', 'Detail Aset')

@section('content')
    @php
        \Carbon\Carbon::setLocale('id');
        $kondisiColor = match ($aset->kondisi) {
            'Baik' => 'bg-emerald-100 text-emerald-700',
            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
            'Rusak Berat' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $statusAktif = ($aset->status_aset ?? 'aktif') === 'aktif';
        $pemegang = $aset->pemegangSaatIni?->pegawai;
        $ruangan = $aset->penempatanAktif?->ruangan;
        $words = preg_split('/\s+/', trim((string) ($pemegang->nama_pegawai ?? '?')));
        $inisial = strtoupper(mb_substr($words[0] ?? '?', 0, 1) . mb_substr($words[1] ?? ($words[0] ?? ''), 0, 1));
    @endphp

    {{-- Navigasi Kembali Minimalis di Atas Judul --}}
    <div class="mb-3">
        <a href="{{ route('aset-barang.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-900">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Aset Barang</span>
        </a>
    </div>

    {{-- Header --}}
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Detail Aset</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full {{ $statusAktif ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    {{ $aset->status_aset ?? 'aktif' }}
                </span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <p class="text-sm font-medium text-slate-900">{{ $aset->barang?->nama_barang ?? 'Barang' }}</p>
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-mono text-xs font-bold text-slate-700">
                    {{ $aset->nomor_kartu_barang ?? 'Kartu: -' }}
                    <button type="button" id="btn-copy-kartu" class="text-slate-400 transition hover:text-emerald-600" title="Salin Nomor Kartu Barang">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('aset-barang.aset.qr', $aset->id_aset) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 3h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1zm11 0h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V4a1 1 0 011-1zM5 15h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4a1 1 0 011-1z"/></svg>
                Cetak Label QR
            </a>
        </div>
    </div>

    {{-- Kartu Informasi Barang --}}
    <x-card>
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Informasi Barang</h3>
                <p class="mt-0.5 text-xs text-slate-400">Detail data inventaris tercatat pada kartu barang.</p>
            </div>
            @if ($isAdminAset)
                <button type="button" data-edit-modal="{{ $aset->id_aset }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </button>
            @endif
        </div>
        <dl class="grid grid-cols-1 gap-x-8 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama Barang</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->barang?->nama_barang ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kategori</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->barang?->kategori?->nama_kategori ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nomor Kartu Barang</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->nomor_kartu_barang ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Merk</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->merk ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kondisi</dt>
                <dd class="text-sm">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status Aset</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->status_aset ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nilai Perolehan</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tanggal Pengadaan</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->tanggal_pengadaan?->format('d M Y') ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tanggal Perolehan</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->tanggal_perolehan?->format('d M Y') ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tanggal Habis Pakai</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $aset->tanggal_habis_pakai?->format('d M Y') ?? '-' }}</dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pemegang Saat Ini</dt>
                <dd class="text-sm">
                    @if ($pemegang)
                        <a href="{{ route('aset-barang.show', $pemegang->id_pegawai) }}" class="inline-flex items-center gap-2 rounded-full bg-emerald-100 py-1 pl-1 pr-3 font-medium text-emerald-700 transition hover:bg-emerald-200">               
                            {{ $pemegang->nama_pegawai }}
                        </a>
                    @else
                        <span class="text-slate-400">Tanpa pemegang</span>
                    @endif
                </dd>
            </div>
            <div class="space-y-1">
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ruangan Saat Ini</dt>
                <dd class="text-sm font-medium text-slate-900">
                    @if ($ruangan)
                        <span class="font-semibold text-slate-900">{{ $ruangan->nama_ruangan }}</span>
                        @if ($ruangan->skpd)
                            <span class="mt-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ $ruangan->skpd->nama_skpd }}</span>
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
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Riwayat Mutasi</h3>
                <p class="mt-0.5 text-xs text-slate-400">Jejak perpindahan pemegang & ruangan tercatat otomatis.</p>
            </div>
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
                        <th scope="col" class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">BAST</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($riwayatMutasi as $detail)
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $detail->mutasi?->tanggal_mutasi?->format('d M Y') ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-600 ring-1 ring-inset ring-emerald-200">{{ $detail->mutasi?->jenis_mutasi ?? '-' }}</span>
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
                            <td class="max-w-[220px] truncate px-6 py-4 text-sm text-slate-600" title="{{ $detail->mutasi?->keterangan ?? '' }}">{{ $detail->mutasi?->keterangan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $detail->mutasi?->userPenginput?->pegawai?->nama_pegawai ?? $detail->mutasi?->userPenginput?->username ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-center">
                                @if (($detail->mutasi?->jenis_mutasi ?? '') == 'Ganti Pemegang')
                                    <a href="{{ route('mutasi-aset.bast.download', $detail->id_mutasi) }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 transition hover:bg-sky-100"
                                       title="Unduh Berkas BAST">
                                        <svg class="h-3.5 w-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span>Unduh BAST</span>
                                    </a>
                                @else
                                    <span class="text-xs italic text-slate-300" title="Pemindahan ruangan tercatat di KIR Ruangan">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada riwayat mutasi untuk barang ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-1 border-t border-slate-100 px-6 py-3 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $riwayatMutasi->count() }} {{ $riwayatMutasi->count() === 1 ? 'riwayat mutasi' : 'riwayat mutasi' }}</span>
            <span class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Terakhir disinkronkan {{ now()->translatedFormat('d M Y, H:i') }}
            </span>
        </div>
    </x-card>

    {{-- Modal Edit Aset --}}
    <div id="aset-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Edit Aset</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Perbarui data aset. Ganti pemegang / pindah ruangan via halaman Aset Barang.</p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="aset-form" method="POST" autocomplete="off">
                @csrf
                <input type="hidden" id="field-id" name="id" value="">
                <div class="max-h-[90vh] space-y-5 overflow-y-auto px-6 py-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="field-barang" class="block text-sm font-medium text-slate-700">Nama Barang <span class="text-red-500">*</span></label>
                            <input type="text" id="field-barang" name="nama_barang" required maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Laptop">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_barang"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-kartu" class="block text-sm font-medium text-slate-700">Nomor Kartu Barang <span class="text-red-500">*</span></label>
                            <input type="text" id="field-kartu" name="nomor_kartu_barang" maxlength="255" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: KIB-001">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_kartu_barang"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-merk" class="block text-sm font-medium text-slate-700">Merk</label>
                            <input type="text" id="field-merk" name="merk" maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Canon">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="merk"></p>
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
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                        Perbarui
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const showUrl = @json(route('aset-barang.aset.show', ['aset' => '__ID__']));
        const updateUrl = @json(route('aset-barang.aset.update', ['aset' => '__ID__']));

        {{-- Salin Nomor Kartu Barang --}}
        document.getElementById('btn-copy-kartu').addEventListener('click', async (e) => {
            const text = @json($aset->nomor_kartu_barang ?? '');
            try {
                await navigator.clipboard.writeText(text);
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
            const btn = e.currentTarget;
            const old = btn.innerHTML;
            btn.innerHTML = '<svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            setTimeout(() => { btn.innerHTML = old; }, 1500);
        });

        {{-- Modal Edit Aset --}}
        const modal = document.getElementById('aset-modal');
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
        modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));

        document.querySelectorAll('[data-edit-modal]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.editModal;
                form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                try {
                    const res = await fetch(showUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('Gagal mengambil data');
                    const data = await res.json();
                    document.getElementById('field-id').value = data.id_aset;
                    document.getElementById('field-barang').value = data.nama_barang ?? '';
                    document.getElementById('field-kartu').value = data.nomor_kartu_barang ?? '';
                    document.getElementById('field-merk').value = data.merk ?? '';
                    document.getElementById('field-nilai').value = data.nilai_perolehan ?? '';
                    document.getElementById('field-kondisi').value = data.kondisi ?? '';
                    document.getElementById('field-status').value = data.status_aset ?? '';
                    document.getElementById('field-pengadaan').value = data.tanggal_pengadaan ?? '';
                    document.getElementById('field-perolehan').value = data.tanggal_perolehan ?? '';
                    document.getElementById('field-habis-pakai').value = data.tanggal_habis_pakai ?? '';
                    form.action = updateUrl.replace('__ID__', id);
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
                    window.location.reload();
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
    </script>
    @endpush
@endsection