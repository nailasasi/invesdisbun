@extends('layouts.app')

@section('title', 'Detail Aset')

@section('page-title', 'Detail Aset Barang')

@section('content')
    @php
        \Carbon\Carbon::setLocale('id');
        $kondisiColor = match ($aset->kondisi) {
            'Baik' => 'bg-disbun-100 text-disbun-800',
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

    {{-- Tombol Kembali --}}
    <div class="mb-4">
        <a href="{{ route('aset-barang.index') }}"
           class="inline-flex items-center gap-1.5 rounded-xl border border-disbun-card-border bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 focus-visible:ring-offset-2">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Aset Barang</span>
        </a>
    </div>

    {{-- Hero Card --}}
    <x-card class="mb-6">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div class="min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Detail Aset</h2>
                    <span class="inline-flex items-center gap-1.5 rounded-full {{ $statusAktif ? 'bg-disbun-50 text-disbun-800' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide border {{ $statusAktif ? 'border-disbun-200' : 'border-amber-200' }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ $aset->status_aset ?? 'aktif' }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-base font-semibold text-slate-900">{{ $aset->barang?->nama_barang ?? 'Barang' }}</p>
                    <span class="inline-flex items-center gap-2 rounded-full border border-disbun-card-border bg-slate-50 px-3 py-1 font-mono text-xs font-bold text-slate-700">
                        {{ $aset->nomor_kartu_barang ?? '-' }}
                        <button type="button" id="btn-copy-kartu" class="text-slate-400 transition hover:text-disbun-700" title="Salin Nomor Kartu Barang">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                @if ($canViewQr ?? false)
                    <a href="{{ route('aset-barang.aset.qr', $aset->id_aset) }}" target="_blank"
                       class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 focus-visible:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 3h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1zm11 0h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V4a1 1 0 011-1zM5 15h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4a1 1 0 011-1z"/></svg>
                        Cetak Label QR
                    </a>
                @endif
                {{-- Edit Aset Master dipindah ke kolom AKSI pada tabel Aset Barang,
                     supaya hanya ada satu akses Edit Aset (tanpa duplikat). --}}
            </div>
        </div>
    </x-card>

    {{-- Card Utama: Informasi Spesifikasi Barang --}}
    <x-card class="mt-6">
        <div class="border-b border-slate-100 pb-4">
            <h3 class="text-base font-semibold text-slate-900">Informasi Spesifikasi Barang</h3>
            <p class="mt-0.5 text-xs text-slate-400">Detail data inventaris tercatat pada kartu barang.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 pt-5 sm:grid-cols-2 lg:grid-cols-4">
            {{-- 1. Kategori --}}
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500">Kategori</p>
                    <p class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $aset->barang?->kategori?->nama_kategori ?? '-' }}</p>
                </div>
            </div>

            {{-- 2. Nomor Kartu Barang --}}
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-3.75a6 6 0 0115.75-2.25z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500">Nomor Kartu Barang</p>
                    <p class="mt-0.5 break-all font-mono text-sm font-semibold text-slate-900">{{ $aset->nomor_kartu_barang ?? '-' }}</p>
                </div>
            </div>

            {{-- 3. Merk --}}
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500">Merk</p>
                    <p class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $aset->merk ?? '-' }}</p>
                </div>
            </div>

            {{-- 4. Kondisi --}}
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500">Kondisi</p>
                    <div class="mt-1">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- 5. Nilai Perolehan --}}
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500">Nilai Perolehan</p>
                    <p class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</p>
                </div>
            </div>

            {{-- 6. SKPD --}}
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500">SKPD</p>
                    <p class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $aset->skpd?->nama_skpd ?? '-' }}</p>
                    @if ($aset->skpd?->jenis_skpd)
                        <span class="mt-1 inline-flex items-center rounded-full border border-disbun-100 bg-disbun-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-disbun-800">{{ $aset->skpd->jenis_skpd }}</span>
                    @endif
                </div>
            </div>

            {{-- 7. Lokasi Fisik --}}
            <div class="flex items-start gap-3 sm:col-span-2 lg:col-span-2">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500">Lokasi Fisik</p>
                    <p class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $aset->lokasi?->nama_lokasi ?? '-' }}</p>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Card Utama: Penempatan & Masa Aset --}}
    <x-card class="mt-6">
        {{-- Header Card --}}
        <div class="border-b border-slate-100 pb-3.5">
            <h3 class="text-base font-semibold text-slate-900">Penempatan &amp; Masa Aset</h3>
            <p class="mt-0.5 text-xs text-slate-400">Informasi pegawai penanggung jawab, ruangan kerja, dan masa pakai aset.</p>
        </div>

        <div class="pt-4">
            {{-- Baris Pertama: PIC / Pemegang Saat Ini & Ruangan Lokasi --}}
            <div class="grid grid-cols-1 gap-y-4 md:grid-cols-2 md:gap-x-10 lg:gap-x-14">
                {{-- Kolom Kiri: PIC / Pemegang Saat Ini --}}
                <div class="flex min-w-0 items-start gap-3.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-disbun-700 text-xs font-bold text-white shadow-xs">
                        {{ $inisial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-500">PIC / Pemegang Saat Ini</p>
                        @if ($pemegang)
                            <div class="mt-0.5">
                                <a href="{{ route('aset-barang.show', $pemegang->id_pegawai) }}"
                                   class="group inline-flex items-center gap-1.5 text-sm font-semibold text-disbun-800 transition hover:text-disbun-900 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600 rounded">
                                    <span class="break-words leading-snug">{{ $pemegang->nama_pegawai }}</span>
                                    <svg class="h-3.5 w-3.5 shrink-0 text-disbun-600 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                </a>
                            </div>
                            <p class="mt-0.5 break-words text-xs text-slate-500 leading-relaxed">
                                {{ $pemegang->jabatan ?? 'Staf' }}
                            </p>
                            @if ($pemegang->nip)
                                <p class="mt-0.5 font-mono text-xs text-slate-400">
                                    NIP: {{ $pemegang->nip }}
                                </p>
                            @endif
                        @else
                            <p class="mt-0.5 text-sm font-semibold text-slate-400">Tanpa pemegang</p>
                        @endif
                    </div>
                </div>

                {{-- Kolom Kanan: Ruangan Lokasi --}}
                <div class="flex min-w-0 items-start gap-3.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-500">Ruangan Lokasi</p>
                        @if ($ruangan)
                            <p class="mt-0.5 break-words text-sm font-semibold text-slate-900 leading-snug">{{ $ruangan->nama_ruangan }}</p>
                            @if ($ruangan->skpd || $ruangan->lantai)
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @if ($ruangan->skpd)
                                        <span class="inline-flex items-center rounded-full border border-disbun-100 bg-disbun-50 px-2.5 py-0.5 text-xs font-medium text-disbun-800">
                                            {{ $ruangan->skpd->nama_skpd }}
                                        </span>
                                    @endif
                                    @if ($ruangan->lantai)
                                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                                            Lantai {{ $ruangan->lantai }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        @else
                            <p class="mt-0.5 text-sm font-semibold text-slate-400">Belum ditempatkan</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Divider Horizontal Antar Baris --}}
            <div class="my-4 border-t border-slate-100"></div>

            {{-- Baris Kedua: Tanggal Pengadaan & Tanggal Habis Pakai --}}
            <div class="grid grid-cols-1 gap-y-3 md:grid-cols-2 md:gap-x-10 lg:gap-x-14">
                {{-- Kolom Kiri: Tanggal Pengadaan --}}
                <div class="flex min-w-0 items-start gap-3.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-500">Tanggal Pengadaan</p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{ $aset->tanggal_pengadaan?->format('d M Y') ?? '-' }}
                        </p>
                    </div>
                </div>

                {{-- Kolom Kanan: Tanggal Habis Pakai --}}
                <div class="flex min-w-0 items-start gap-3.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-500">Tanggal Habis Pakai</p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{ $aset->tanggal_habis_pakai?->format('d M Y') ?? '-' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Riwayat Mutasi (Section) --}}
    @include('aset-barang.partials.riwayat-mutasi')

    @push('scripts')
    <script>
        {{-- Edit Aset Master dipindah ke kolom AKSI tabel Aset Barang. --}}
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
    </script>
    @endpush

@endsection
