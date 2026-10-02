@php
    $pemegangTanpaSppbiCount = $pegawaiTanpaSppbi->count();
    $berkasLengkap = max($totalPemegang - $pemegangTanpaSppbiCount, 0);
    $berkasPct = $totalPemegang > 0 ? round($berkasLengkap / $totalPemegang * 100) : 100;

    $kondisiTotal = $asetBaik + $asetRusakRingan + $asetRusakBerat;
    $pctBaik = $kondisiTotal > 0 ? round($asetBaik / $kondisiTotal * 100) : 0;
    $pctRingan = $kondisiTotal > 0 ? round($asetRusakRingan / $kondisiTotal * 100) : 0;
    $pctBerat = $kondisiTotal > 0 ? round($asetRusakBerat / $kondisiTotal * 100) : 0;

    $inisialPegawai = static function ($nama) {
        $kata = preg_split('/\s+/', trim((string) $nama));
        return mb_strtoupper(collect(array_slice($kata, 0, 2))->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    };
@endphp

{{-- HERO BENTO --}}
<div class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-12">
    {{-- Hero kiri: gradasi hijau tua --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0c2e1b] via-[#133e25] to-[#1c5531] p-6 text-white sm:p-8 lg:col-span-8">
        <div class="absolute -right-14 -top-14 h-56 w-56 rounded-full bg-disbun-amber-glow/20 blur-3xl"></div>
        <div class="absolute -bottom-16 left-1/3 h-48 w-48 rounded-full bg-emerald-400/15 blur-3xl"></div>
        <div class="relative flex h-full flex-col justify-between gap-6">
            <div>
                <p class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-disbun-100 backdrop-blur">
                    <svg class="h-3.5 w-3.5 text-disbun-amber-glow" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Dashboard Admin Aset
                </p>
                <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Ringkasan Inventaris Dinas Perkebunan</h1>
                <p class="mt-2 max-w-lg text-sm text-white/70 sm:text-base">
                    Pantau kesehatan aset, kelengkapan berkas, dan tindak lanjut prioritas dalam satu kanvas bento.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('monitoring-aset.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-disbun-amber to-disbun-amber-glow px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-black/10 transition hover:brightness-105">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    Monitoring Aset
                </a>
                <a href="{{ route('rkbmd.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/15">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    RKBMD
                </a>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 font-semibold text-white/80">
                    <span class="h-1.5 w-1.5 rounded-full bg-disbun-amber-glow"></span>
                    RKBMD Diajukan: {{ $rkbmdPendingCount }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 font-semibold text-white/80">
                    <span class="h-1.5 w-1.5 rounded-full bg-disbun-amber-glow"></span>
                    Izin Menunggu: {{ $izinMenungguCount }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 font-semibold text-white/80">
                    {{ now()->translatedFormat('l, d F Y') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Hero kanan: status kesehatan & verifikasi berkas --}}
    <div class="flex h-full flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-bold text-slate-800">Status Kesehatan Aset</h2>
            <span class="inline-flex items-center gap-1 rounded-full bg-disbun-50 px-2.5 py-1 text-[11px] font-bold text-disbun-700">
                {{ number_format($kondisiTotal, 0, ',', '.') }} aset dinilai
            </span>
        </div>

        {{-- Progress bar bertingkat --}}
        <div class="mt-4 flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
            <span class="h-full rounded-l-full bg-disbun-500 transition-all duration-700" style="width: {{ $pctBaik }}%"></span>
            <span class="h-full bg-amber-400 transition-all duration-700" style="width: {{ $pctRingan }}%"></span>
            <span class="h-full rounded-r-full bg-rose-500 transition-all duration-700" style="width: {{ $pctBerat }}%"></span>
        </div>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-disbun-50 px-2 py-2">
                <p class="text-lg font-extrabold text-disbun-700">{{ number_format($asetBaik, 0, ',', '.') }}</p>
                <p class="text-[10px] font-semibold text-disbun-600">Baik</p>
            </div>
            <div class="rounded-xl bg-amber-50 px-2 py-2">
                <p class="text-lg font-extrabold text-amber-600">{{ number_format($asetRusakRingan, 0, ',', '.') }}</p>
                <p class="text-[10px] font-semibold text-amber-600">Rusak Ringan</p>
            </div>
            <div class="rounded-xl bg-rose-50 px-2 py-2">
                <p class="text-lg font-extrabold text-rose-600">{{ number_format($asetRusakBerat, 0, ',', '.') }}</p>
                <p class="text-[10px] font-semibold text-rose-600">Rusak Berat</p>
            </div>
        </div>

        <div class="my-5 h-px bg-slate-100"></div>

        <div class="flex items-end justify-between gap-2">
            <div>
                <p class="text-xs font-semibold text-slate-500">Verifikasi Berkas SPPBI</p>
                <p class="mt-0.5 text-3xl font-extrabold tracking-tight text-slate-900">{{ $berkasPct }}<span class="text-base font-bold text-slate-400">%</span></p>
            </div>
            <p class="text-right text-[11px] leading-snug text-slate-400">
                {{ $berkasLengkap }} dari {{ $totalPemegang }} pemegang<br>sudah melengkapi TTD
            </p>
        </div>
        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <span class="block h-full rounded-full bg-gradient-to-r from-disbun-600 to-disbun-400 transition-all duration-700" style="width: {{ $berkasPct }}%"></span>
        </div>
        <p class="mt-2 text-[11px] font-medium text-amber-600">
            {{ $pemegangTanpaSppbiCount }} pemegang belum mengunggah scan TTD
        </p>
    </div>
</div>

{{-- METRIC BENTO TILES --}}
<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 40ms">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Total Aset Aktif</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-disbun-600 to-disbun-800 text-white shadow-sm">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($totalAset, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-slate-400">tercatat di sistem</p>
    </div>

    <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 80ms">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Nilai Perolehan</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-sm">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Rp {{ number_format($totalNilai, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-slate-400">total nilai perolehan</p>
    </div>

    <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 120ms">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Kendaraan</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-sm">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 18H3V5h11v13H9m-4 0a2 2 0 104 0m-4 0a2 2 0 114 0m4-8h5l3 3v5h-8m0 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
            </div>
        </div>
        <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
            <span class="text-disbun-600">{{ number_format($kendaraanTersedia, 0, ',', '.') }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-sky-600">{{ number_format($kendaraanDipakai, 0, ',', '.') }}</span>
        </p>
        <p class="mt-1 text-xs text-slate-400">tersedia / dipakai</p>
    </div>

    <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 160ms">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Tanah KIB A</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-sm">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            </div>
        </div>
        <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($tanahKibA, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-slate-400">persil tanah tercatat</p>
    </div>
</div>

{{-- PRIORITY ACTION HUB --}}
<div class="mt-6 grid grid-cols-1 items-stretch gap-4 lg:grid-cols-12">
    {{-- SPPBI belum diunggah --}}
    <div class="animate-fade-in-up flex flex-col rounded-3xl border border-amber-200/70 bg-gradient-to-b from-amber-50 to-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-5">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l3-3m-3 3l-3-3"/></svg>
            </span>
            <div>
                <h3 class="text-sm font-bold text-slate-800">SPPBI Belum Diunggah</h3>
                <p class="text-xs text-slate-400">Pemegang aset tanpa scan TTD</p>
            </div>
            <span class="ml-auto inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-amber-100 px-2 text-xs font-bold text-amber-700">
                {{ $pemegangTanpaSppbiCount }}
            </span>
        </div>

        <div class="mt-4 flex-1 space-y-2 max-h-[350px] overflow-y-auto pr-1">
            @forelse ($pegawaiTanpaSppbi as $pg)
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/80 p-3 ring-1 ring-amber-100">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-xs font-bold text-white shadow-sm">
                            {{ $inisialPegawai($pg->nama_pegawai) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $pg->nama_pegawai }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ $pg->skpd?->nama_skpd ?? '-' }} · {{ $pg->jumlah_aset }} aset</p>
                        </div>
                    </div>
                    <a href="{{ route('aset-barang.show', $pg->id_pegawai) }}"
                       class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-gradient-to-r from-disbun-amber to-disbun-amber-glow px-3 py-2 text-[11px] font-bold text-white shadow-sm transition hover:brightness-105">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Unggah Sekarang
                    </a>
                </div>
            @empty
                <div class="flex h-24 flex-col items-center justify-center rounded-2xl bg-white/70 text-center">
                    <svg class="h-6 w-6 text-disbun-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="mt-1.5 text-xs font-medium text-slate-400">Semua pemegang sudah unggah TTD</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Pajak kendaraan jatuh tempo --}}
    <div class="animate-fade-in-up flex flex-col rounded-3xl border border-rose-200/70 bg-gradient-to-b from-rose-50 to-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-7" style="animation-delay: 60ms">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </span>
            <div>
                <h3 class="text-sm font-bold text-slate-800">Pajak Kendaraan</h3>
                <p class="text-xs text-slate-400">Jatuh tempo ≤ 30 hari / lewat</p>
            </div>
            <span class="ml-auto inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-rose-100 px-2 text-xs font-bold text-rose-700">
                {{ $kendaraanPajakCount ?? $kendaraanPajak->count() }}
            </span>
        </div>

        <div class="mt-4 flex-1 space-y-2 max-h-[350px] overflow-y-auto pr-1">
            @forelse ($kendaraanPajak as $kend)
                @php $pajak = $kend->pajakAktif; @endphp
                <a href="{{ route('kendaraan.index') }}"
                   class="flex items-center justify-between gap-3 rounded-2xl bg-white/80 px-4 py-3 ring-1 ring-rose-100 transition hover:bg-white">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100/70 text-rose-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 18H3V5h11v13H9m-4 0a2 2 0 104 0m-4 0a2 2 0 114 0m4-8h5l3 3v5h-8m0 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $kend->aset?->barang?->nama_barang ?? $kend->aset?->merk ?? 'Kendaraan' }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-400">
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono font-semibold text-slate-600">{{ $kend->platAktif?->nomor_plat ?? '-' }}</span>
                                @if ($pajak?->tanggal_berakhir)
                                    berakhir {{ $pajak->tanggal_berakhir->format('d M Y') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <span @class([
                        'shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold',
                        'bg-rose-100 text-rose-700' => $pajak?->tanggal_berakhir?->isPast(),
                        'bg-amber-100 text-amber-700' => $pajak?->tanggal_berakhir?->isFuture(),
                    ])>
                        {{ $pajak?->tanggal_berakhir?->isPast() ? 'Lewat Masa' : 'Segera' }}
                    </span>
                </a>
            @empty
                <div class="flex h-24 flex-col items-center justify-center rounded-2xl bg-white/70 text-center">
                    <svg class="h-6 w-6 text-disbun-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="mt-1.5 text-xs font-medium text-slate-400">Tidak ada pajak yang perlu diperbarui</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- BOTTOM BENTO TRIO --}}
<div class="mt-6 grid grid-cols-1 items-stretch gap-4 lg:grid-cols-12">
    {{-- Kondisi fisik --}}
    <div class="animate-fade-in-up flex flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-4">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Kondisi Fisik Inventaris</h3>
                    <p class="text-xs text-slate-400">Distribusi kondisi aset aktif</p>
                </div>
            </div>
            <a href="{{ route('aset-barang.index') }}" class="text-xs font-semibold text-disbun-700 hover:text-disbun-600">Lihat</a>
        </div>

        <div class="mt-5 flex-1 space-y-4">
            <div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-disbun-700">Baik</span>
                    <span class="font-bold text-slate-700">{{ number_format($asetBaik, 0, ',', '.') }} · {{ $pctBaik }}%</span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                    <span class="block h-full rounded-full bg-disbun-500 transition-all duration-700" style="width: {{ $pctBaik }}%"></span>
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-amber-600">Rusak Ringan</span>
                    <span class="font-bold text-slate-700">{{ number_format($asetRusakRingan, 0, ',', '.') }} · {{ $pctRingan }}%</span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-amber-100">
                    <span class="block h-full rounded-full bg-amber-400 transition-all duration-700" style="width: {{ $pctRingan }}%"></span>
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-rose-600">Rusak Berat</span>
                    <span class="font-bold text-slate-700">{{ number_format($asetRusakBerat, 0, ',', '.') }} · {{ $pctBerat }}%</span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-rose-100">
                    <span class="block h-full rounded-full bg-rose-500 transition-all duration-700" style="width: {{ $pctBerat }}%"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Pengajuan izin pending --}}
    <div class="animate-fade-in-up flex flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-4" style="animation-delay: 60ms">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM7 9h8m3 5h-3m-8 0h8M4 9v2m13-4V5m-4-1H7a2 2 0 00-2 2v4m13 2v4a1 1 0 01-1 1h-1m-9 0H6a1 1 0 01-1-1v-4"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Pengajuan Izin Pending</h3>
                    <p class="text-xs text-slate-400">Menunggu persetujuan Admin Aset</p>
                </div>
            </div>
            @if ($izinMenungguCount > 0)
                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-700">{{ $izinMenungguCount }}</span>
            @endif
        </div>

        <div class="mt-4 flex-1 space-y-2">
            @forelse ($izinMenungguTerbaru as $izin)
                @php
                    $namaKendaraan = $izin->kendaraan?->aset?->barang?->nama_barang ?: 'Kendaraan';
                    $plat = $izin->kendaraan?->platAktif?->nomor_plat;
                @endphp
                <a href="{{ route('layanan.izin-kendaraan.index') }}"
                   class="block rounded-2xl bg-slate-50/70 px-4 py-3 transition hover:bg-amber-50/60">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-sm font-semibold text-slate-800">{{ $izin->pengaju?->nama_pegawai ?? '-' }}</p>
                        <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Menunggu</span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] text-slate-400">
                        {{ $namaKendaraan }}{{ $plat ? ' · ' . $plat : '' }} · {{ $izin->tanggal_berangkat?->format('d M Y') ?? '-' }}
                    </p>
                </a>
            @empty
                <div class="flex h-24 flex-col items-center justify-center rounded-2xl bg-slate-50/50 text-center">
                    <svg class="h-6 w-6 text-disbun-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="mt-1.5 text-xs font-medium text-slate-400">Semua pengajuan telah diproses</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Aktivitas terkini --}}
    <div class="animate-fade-in-up flex flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-4" style="animation-delay: 120ms">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Aktivitas Terkini</h3>
                    <p class="text-xs text-slate-400">Rekam jejak mutasi aset terbaru</p>
                </div>
            </div>
            <a href="{{ route('mutasi-aset.index') }}" class="text-xs font-semibold text-sky-600 hover:text-sky-500">Lihat</a>
        </div>

        <div class="mt-4 flex-1 space-y-3">
            @forelse ($aktivitasTerbaru as $mut)
                <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-disbun-500 ring-4 ring-disbun-50"></span>
                        <span class="w-px flex-1 bg-slate-100"></span>
                    </div>
                    <div class="min-w-0 pb-1">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $mut->jenis_mutasi ?? 'Mutasi Aset' }}
                            <span class="font-normal text-slate-400">· {{ $mut->tanggal_mutasi?->format('d M Y') ?? '-' }}</span>
                        </p>
                        <p class="mt-0.5 truncate text-[11px] text-slate-400">
                            {{ $mut->details_count }} aset
                            @if ($mut->keterangan) · {{ Str::limit($mut->keterangan, 60) }} @endif
                        </p>
                        <p class="mt-0.5 text-[10px] font-medium text-slate-300">diinput oleh {{ $mut->userPenginput?->username ?? '-' }}</p>
                    </div>
                </div>
            @empty
                <div class="flex h-24 flex-col items-center justify-center rounded-2xl bg-slate-50/50 text-center">
                    <svg class="h-6 w-6 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="mt-1.5 text-xs font-medium text-slate-400">Belum ada aktivitas mutasi</p>
                </div>
            @endforelse
        </div>
    </div>
</div>