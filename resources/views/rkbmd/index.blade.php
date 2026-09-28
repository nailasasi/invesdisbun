@extends('layouts.app')

@section('title', 'RKBMD')
@section('page-title', 'RKBMD')
@section('breadcrumb', 'Perencanaan - Rencana Kebutuhan Barang Milik Daerah')

@section('content')
    <div class="space-y-4">
        {{-- 1. HEADER (Compact & Rapi) --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-800">
                    Rencana Kebutuhan Barang Milik Daerah (RKBMD)
                </h1>
                <p class="mt-0.5 text-xs text-slate-400">
                    Usulan kebutuhan BMD per unit kerja sesuai Permendagri No. 19/2016
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Dropdown Kelola Excel --}}
                <div class="relative" id="excelMenuWrap">
                    <button type="button" onclick="toggleExcelMenu()"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Opsi Excel</span>
                        <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div id="excelMenu"
                         class="absolute right-0 z-20 mt-1.5 hidden w-56 rounded-2xl border border-slate-100 bg-white p-1.5 text-xs shadow-xl">
                        <a href="{{ route('rkbmd.template') }}"
                           class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Unduh Template</span>
                        </a>

                        <button type="button" data-open-modal="modalImportExcel" onclick="closeExcelMenu()"
                                class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span>Import Template Excel</span>
                        </button>

                        <div class="my-1 border-t border-slate-100"></div>

                        {{-- Export dibuka via modal parameter export --}}
                        <button type="button" data-open-modal="modalExportRKBMD" onclick="closeExcelMenu()"
                                class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2 font-semibold text-emerald-700 transition hover:bg-emerald-50/50">
                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Export Rekap Excel</span>
                        </button>
                    </div>
                </div>

                <button type="button" data-open-modal="modalUsulan"
                        class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Buat Usulan
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">
                <p class="font-semibold">Periksa kembali isian Anda:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

    {{-- 2. FILTER (2/3) + ARSIP (1/3) --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- CARD FILTER BIDANG --}}
        <form method="GET" action="{{ route('rkbmd.index') }}" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-2xs lg:col-span-2">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Filter Unit Kerja / Bidang</span>
                @if ($isAdmin)
                    <span class="text-[11px] text-slate-400 italic">Pilih satu atau lebih</span>
                @endif
            </div>

            @if ($isAdmin)
                <div class="mb-3 flex flex-wrap gap-1.5">
                    @foreach ($bidangOptions as $opt)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="bidang[]" value="{{ $opt->id_skpd }}"
                                   class="peer sr-only" @checked(in_array($opt->id_skpd, $activeBidang))>
                            <span class="inline-block rounded-xl border border-slate-200 bg-slate-50/60 px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:font-bold peer-checked:text-emerald-700">
                                {{ $opt->nama_skpd }}
                            </span>
                        </label>
                    @endforeach
                </div>
            @else
                <div class="mb-3">
                    <span class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $mySkpd?->nama_skpd ?? 'Unit tidak terdeteksi' }}
                    </span>
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        Usulan yang Anda buat otomatis tercatat atas unit kerja tersebut.
                    </p>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 border-t border-slate-50 pt-2.5">
                <div class="w-32">
                    <select id="filter-tahun" name="tahun" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="">Semua Tahun</option>
                        @foreach ($tahunList as $tahun)
                            <option value="{{ $tahun }}" @selected(request('tahun') == $tahun)>{{ $tahun }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <select id="filter-jenis" name="jenis" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="">Semua Usulan</option>
                        @foreach ($jenisList as $jenis)
                            <option value="{{ $jenis }}" @selected(request('jenis') === $jenis)>{{ $jenis }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ml-auto flex items-center gap-1.5">
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                    <a href="{{ route('rkbmd.index') }}"
                       class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        {{-- CARD ARSIP DOKUMEN SAH --}}
        <div class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-4 shadow-2xs">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-xs font-bold text-slate-800">Arsip Dokumen Sah RKBMD</h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">Dokumen pengesahan bertanda tangan Kepala Dinas</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $arsipList->count() }} berkas terarsip</p>
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
                @if ($isAdmin)
                    <button type="button" data-open-modal="modalArsipUpload"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/></svg>
                        Upload
                    </button>
                @endif
                <button type="button" data-open-modal="modalArsipList"
                        class="flex-1 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                    Lihat Arsip
                </button>
            </div>
        </div>
    </div>

    {{-- 3. DAFTAR USULAN TABLE (Kompak) --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-2xs">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800">Daftar Usulan Kebutuhan</h3>
                <p class="text-[11px] text-slate-400">
                    {{ $usulanList->total() }} usulan ditemukan
                    @if (request('tahun')) - TA {{ request('tahun') }} @endif
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
                        <th class="w-12 px-3 py-2.5 text-center">#</th>
                        <th class="px-3 py-2.5">Bidang / Unit</th>
                        <th class="px-3 py-2.5">Jenis</th>
                        <th class="px-3 py-2.5">Nama Barang &amp; Kode</th>
                        <th class="px-3 py-2.5 text-center">Jumlah/Satuan</th>
                        <th class="px-3 py-2.5">Keterangan</th>
                        <th class="px-3 py-2.5 text-center">Status</th>
                        <th class="w-24 px-3 py-2.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($usulanList as $i => $usulan)
                        @php
                            $isFinal = in_array($usulan->status_usulan, ['Disetujui Pengurus Barang', 'Ditolak']);
                            $bisaEdit = !$isFinal;
                            $bisaAjukan = $usulan->status_usulan === 'Draft'
                                && $usulan->id_pegawai === auth()->user()?->pegawai?->id_pegawai;
                            $badgeJenis = [
                                'Pengadaan' => 'bg-emerald-100 text-emerald-700',
                                'Pemeliharaan' => 'bg-amber-100 text-amber-700',
                                'Penghapusan' => 'bg-rose-100 text-rose-700',
                            ][$usulan->jenis_usulan] ?? 'bg-slate-100 text-slate-600';
                            $badgeStatus = [
                                'Draft' => 'bg-slate-100 text-slate-600',
                                'Diajukan' => 'bg-amber-100 text-amber-700',
                                'Disetujui Pengurus Barang' => 'bg-emerald-100 text-emerald-700',
                                'Ditolak' => 'bg-rose-100 text-rose-700',
                            ][$usulan->status_usulan] ?? 'bg-slate-100 text-slate-600';
                        @endphp
                        <tr class="align-top transition hover:bg-slate-50/80">
                            <td class="px-3 py-2.5 text-center font-mono text-[11px] text-slate-400">{{ $usulanList->firstItem() + $i }}</td>
                            <td class="px-3 py-2.5">
                                <p class="font-semibold text-slate-700">{{ $usulan->skpd?->nama_skpd ?? '-' }}</p>
                                <p class="text-[10px] text-slate-400">TA {{ $usulan->tahun_anggaran }}</p>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-block rounded-lg px-2 py-0.5 text-[10px] font-bold {{ $badgeJenis }}">{{ $usulan->jenis_usulan }}</span>
                            </td>
                            <td class="max-w-[240px] px-3 py-2.5">
                                <p class="truncate font-bold text-slate-800" title="{{ $usulan->nama_barang }}">{{ $usulan->nama_barang }}</p>
                                <p class="font-mono text-[10px] text-slate-400">{{ $usulan->kode_barang ?: '-' }}</p>
                                @if ($usulan->spesifikasi)
                                    <p class="mt-0.5 line-clamp-1 text-[10px] text-slate-500" title="{{ $usulan->spesifikasi }}">{{ $usulan->spesifikasi }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                <span class="font-bold text-slate-800">{{ $usulan->jumlah }}</span>
                                <span class="text-[10px] text-slate-400">{{ $usulan->satuan }}</span>
                            </td>
                            <td class="max-w-[220px] px-3 py-2.5">
                                <p class="line-clamp-2 text-slate-600" title="{{ $usulan->alasan_kebutuhan }}">{{ $usulan->alasan_kebutuhan }}</p>
                                @if ($usulan->catatan_pengurus)
                                    <p class="mt-1 rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-600">
                                        <span class="font-bold">Catatan:</span> {{ $usulan->catatan_pengurus }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $badgeStatus }}">{{ $usulan->status_usulan }}</span>
                                @if ($usulan->approved_at)
                                    <p class="mt-0.5 text-[10px] text-slate-400">{{ $usulan->approved_at->format('d M Y') }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($bisaAjukan)
                                        <form method="POST" action="{{ route('rkbmd.submit', $usulan->id_usulan) }}">
                                            @csrf
                                            <button type="submit" title="Ajukan ke Pengurus Barang"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-2 py-1 text-[11px] font-semibold text-white transition hover:bg-slate-800">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                                Ajukan
                                            </button>
                                        </form>
                                    @endif

                                    @if ($bisaEdit)
                                        <button type="button" data-open-modal="modalUsulan" data-edit-usulan
                                                data-action="{{ route('rkbmd.update', $usulan->id_usulan) }}"
                                                data-id-skpd="{{ $usulan->id_skpd }}"
                                                data-tahun="{{ $usulan->tahun_anggaran }}"
                                                data-jenis="{{ $usulan->jenis_usulan }}"
                                                data-program="{{ $usulan->program_kegiatan }}"
                                                data-kode="{{ $usulan->kode_barang }}"
                                                data-nama="{{ $usulan->nama_barang }}"
                                                data-spesifikasi="{{ $usulan->spesifikasi }}"
                                                data-jumlah="{{ $usulan->jumlah }}"
                                                data-satuan="{{ $usulan->satuan }}"
                                                data-kebutuhan-maks="{{ $usulan->kebutuhan_maksimum }}"
                                                data-kebutuhan-riil="{{ $usulan->kebutuhan_riil }}"
                                                data-opt="@json($usulan->barang_optimalisasi)"
                                                data-alasan="{{ $usulan->alasan_kebutuhan }}"
                                                title="Edit usulan"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Edit
                                        </button>
                                    @endif

                                    @if ($isAdmin && !$isFinal)
                                        <form method="POST" action="{{ route('rkbmd.decide', $usulan->id_usulan) }}"
                                              onsubmit="return confirm('Setujui usulan ini sebagai usulan resmi RKBMD?');">
                                            @csrf
                                            <input type="hidden" name="keputusan" value="setuju">
                                            <button type="submit" title="Setujui"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white transition hover:bg-emerald-700">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                Setujui
                                            </button>
                                        </form>
                                        <button type="button" data-open-modal="modalTolak-{{ $usulan->id_usulan }}" title="Tolak dengan catatan"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-2 py-1 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Tolak
                                        </button>
                                    @endif

                                    @if (!$isFinal || $isAdmin)
                                        <form method="POST" action="{{ route('rkbmd.destroy', $usulan->id_usulan) }}"
                                              onsubmit="return confirm('Hapus usulan RKBMD ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Hapus"
                                                    class="inline-flex items-center rounded-lg p-1 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7a.87.87 0 00-1 1v11a1 1 0 01-1 1H7a1 1 0 01-1-1V8a1 1 0 00-2 0v12a3 3 0 003 3h10a3 3 0 003-3V8a1 1 0 00-1-1zM4 6h16M10 3h4a1 1 0 011 1v2h-6V4a1 1 0 011-1z"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="mt-2 text-sm font-semibold text-slate-600">Belum ada usulan</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">Klik "Buat Usulan" untuk menyusun kebutuhan BMD unit Anda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usulanList->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $usulanList->links() }}
            </div>
        @endif
    </div>
    </div>

    @push('modals')
        @include('rkbmd.partials.modal-create')

        @if ($isAdmin)
            {{-- MODAL UPLOAD ARSIP --}}
            <div id="modalArsipUpload" class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-modal>
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-close></div>
                <div class="relative w-full max-w-lg rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/></svg>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Unggah Dokumen Sah RKBMD</h3>
                                <p class="mt-0.5 text-xs text-slate-400">PDF/scan SK pengesahan bertanda tangan Kadis</p>
                            </div>
                        </div>
                        <button type="button" data-close class="text-slate-400 transition hover:text-slate-600" title="Tutup">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('rkbmd.arsip.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="arsip-skpd" class="mb-1.5 block text-sm font-semibold text-slate-700">Bidang / Dinas <span class="text-red-500">*</span></label>
                            <select id="arsip-skpd" name="id_skpd" required
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                                <option value="" disabled selected>-- Pilih Unit Kerja --</option>
                                @foreach ($bidangOptions as $opt)
                                    <option value="{{ $opt->id_skpd }}">{{ $opt->nama_skpd }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="arsip-tahun" class="mb-1.5 block text-sm font-semibold text-slate-700">Tahun Anggaran <span class="text-red-500">*</span></label>
                                <input type="number" id="arsip-tahun" name="tahun_anggaran" min="2000" max="2100"
                                       value="{{ old('tahun_anggaran', now()->year + 1) }}" required
                                       class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            </div>
                            <div>
                                <label for="arsip-tanggal" class="mb-1.5 block text-sm font-semibold text-slate-700">Tanggal Pengesahan Kadis</label>
                                <input type="date" id="arsip-tanggal" name="tanggal_pengesahan" value="{{ old('tanggal_pengesahan', now()->toDateString()) }}"
                                       class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            </div>
                        </div>

                        <div>
                            <label for="arsip-nama" class="mb-1.5 block text-sm font-semibold text-slate-700">Nama Dokumen <span class="font-normal text-slate-400">(opsional)</span></label>
                            <input type="text" id="arsip-nama" name="nama_dokumen" maxlength="255"
                                   placeholder="Contoh: SK Pengesahan RKBMD 2027"
                                   value="{{ old('nama_dokumen') }}"
                                   class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        </div>

                        <div>
                            <label for="arsip-file" class="mb-1.5 block text-sm font-semibold text-slate-700">File Dokumen (PDF) <span class="text-red-500">*</span></label>
                            <input type="file" id="arsip-file" name="file_dokumen" accept="application/pdf" required
                                   class="block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:bg-slate-100">
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button type="button" data-close
                                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800">
                                Unggah Dokumen
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/></svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- MODAL LIHAT ARSIP --}}
        @include('rkbmd.partials.modal-list-arsip')

        {{-- MODAL IMPORT EXCEL --}}
        @include('rkbmd.partials.modal-import-excel')

        {{-- MODAL EXPORT REKAP --}}
        @include('rkbmd.partials.modal-export-rkbmd')

        {{-- MODAL TOLAK (per usulan, khusus admin) --}}
        @foreach ($usulanList as $usulan)
            @if (!$usulan->status_usulan || in_array($usulan->status_usulan, ['Draft', 'Diajukan']))
                <div id="modalTolak-{{ $usulan->id_usulan }}" class="fixed inset-0 z-50 hidden items-center justify-center p-4" data-modal>
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" data-close></div>
                    <div class="relative w-full max-w-md rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </span>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Tolak Usulan RKBMD</h3>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $usulan->nama_barang }}</p>
                                </div>
                            </div>
                            <button type="button" data-close class="text-slate-400 transition hover:text-slate-600" title="Tutup">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('rkbmd.decide', $usulan->id_usulan) }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="keputusan" value="tolak">
                            <div>
                                <label for="catatan-{{ $usulan->id_usulan }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Alasan Penolakan <span class="text-red-500">*</span>
                                </label>
                                <textarea id="catatan-{{ $usulan->id_usulan }}" name="catatan_pengurus" rows="4" maxlength="1000" required
                                          placeholder="Contoh: spesifikasi tidak sesuai kebutuhan riil unit, atau melebihi standar harga"
                                          class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400"></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-close
                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-700">
                                    Kirim Penolakan
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
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
            // Buka/tutup modal generik (data-open-modal, data-modal, data-close).
            document.querySelectorAll('[data-open-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const modal = document.getElementById(button.getAttribute('data-open-modal'));
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
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
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) close();
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    document.querySelectorAll('[data-modal]').forEach(function (modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    });
                }
            });

            // Dropdown "Opsi Excel".
            function toggleExcelMenu() {
                const menu = document.getElementById('excelMenu');
                if (menu) menu.classList.toggle('hidden');
            }

            function closeExcelMenu() {
                const menu = document.getElementById('excelMenu');
                if (menu) menu.classList.add('hidden');
            }

            document.addEventListener('click', function (event) {
                const wrap = document.getElementById('excelMenuWrap');
                const menu = document.getElementById('excelMenu');
                if (wrap && menu && !wrap.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });

            </script>
    @endpush
@endsection