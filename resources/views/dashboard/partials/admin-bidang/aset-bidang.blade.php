@php
    $kondisiTotalBidang = $asetBaikBidang + $asetRusakRinganBidang + $asetRusakBeratBidang;
    $pctBaik = $kondisiTotalBidang > 0 ? round($asetBaikBidang / $kondisiTotalBidang * 100) : 0;
    $pctRingan = $kondisiTotalBidang > 0 ? round($asetRusakRinganBidang / $kondisiTotalBidang * 100) : 0;
    $pctBerat = $kondisiTotalBidang > 0 ? round($asetRusakBeratBidang / $kondisiTotalBidang * 100) : 0;
@endphp

<div class="space-y-4">
    {{-- Section Header: Informasi Aset Bidang --}}
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-800 sm:text-lg">Informasi Aset Bidang</h2>
                <p class="text-xs text-slate-400">Cakupan aset dinas yang tercatat menjadi kewenangan {{ $skpd->nama_skpd }}</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full bg-disbun-50 px-2.5 py-1 text-[11px] font-bold text-disbun-700">
            Unit SKPD
        </span>
    </div>

    {{-- 4 Metric Bento Tiles (1 Baris 4 Kolom) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Aset Bidang (Hijau) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Total Aset</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-disbun-600 to-disbun-800 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($totalAsetBidang, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">aset aktif di lingkungan bidang</p>
        </div>

        {{-- Nilai Perolehan (Oranye) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 40ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Nilai Perolehan</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Rp {{ number_format($nilaiAsetBidang, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">total nilai perolehan bidang</p>
        </div>

        {{-- Kendaraan Unit (Biru) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 80ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Kendaraan Bidang</p>
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

        {{-- Pegawai Pemegang (Ungu) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 120ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Pegawai Pemegang</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($totalPegawaiPemegang, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">pegawai memegang aset</p>
        </div>
    </div>

    {{-- Bento Baris Kedua: Statistik Kondisi Aset & Daftar Pegawai Pemegang --}}
    <div class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-12">
        {{-- Card Kiri: Statistik Kondisi Aset Bidang --}}
        <div class="animate-fade-in-up flex flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-5">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Statistik Kondisi Aset Bidang</h3>
                        <p class="text-xs text-slate-400">Distribusi kondisi fisik aset di lingkungan bidang</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-disbun-50 px-2.5 py-1 text-[11px] font-bold text-disbun-700">
                    {{ number_format($kondisiTotalBidang, 0, ',', '.') }} aset dinilai
                </span>
            </div>

            {{-- Progress bar bertingkat (Pola Admin Aset) --}}
            <div class="mt-4 flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                <span class="h-full rounded-l-full bg-disbun-500 transition-all duration-700" style="width: {{ $pctBaik }}%"></span>
                <span class="h-full bg-amber-400 transition-all duration-700" style="width: {{ $pctRingan }}%"></span>
                <span class="h-full rounded-r-full bg-rose-500 transition-all duration-700" style="width: {{ $pctBerat }}%"></span>
            </div>

            {{-- 3 Condition Tiles --}}
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-disbun-50 px-2 py-2.5">
                    <p class="text-lg font-extrabold text-disbun-700">{{ number_format($asetBaikBidang, 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-disbun-600">Baik ({{ $pctBaik }}%)</p>
                </div>
                <div class="rounded-xl bg-amber-50 px-2 py-2.5">
                    <p class="text-lg font-extrabold text-amber-600">{{ number_format($asetRusakRinganBidang, 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-amber-600">Rusak Ringan ({{ $pctRingan }}%)</p>
                </div>
                <div class="rounded-xl bg-rose-50 px-2 py-2.5">
                    <p class="text-lg font-extrabold text-rose-600">{{ number_format($asetRusakBeratBidang, 0, ',', '.') }}</p>
                    <p class="text-[10px] font-semibold text-rose-600">Rusak Berat ({{ $pctBerat }}%)</p>
                </div>
            </div>

            <div class="my-5 h-px bg-slate-100"></div>

            {{-- Ringkasan Kondisi --}}
            <div class="flex-1 rounded-2xl bg-slate-50/70 p-4 border border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $pctBaik >= 80 ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    <p class="text-xs font-semibold text-slate-700">Kesehatan Fisik Inventaris</p>
                </div>
                <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">
                    Sebanyak <span class="font-bold text-slate-800">{{ $pctBaik }}% aset</span> berada dalam kondisi prima dan siap menunjang kegiatan operasional unit {{ $skpd->nama_skpd }}.
                    @if ($asetRusakRinganBidang > 0 || $asetRusakBeratBidang > 0)
                        Tercatat {{ $asetRusakRinganBidang + $asetRusakBeratBidang }} unit membutuhkan pemeliharaan atau pengusulan perbaikan pada siklus RKBMD.
                    @endif
                </p>
            </div>
        </div>

        {{-- Card Kanan: Daftar Pegawai Pemegang Aset --}}
        <div class="animate-fade-in-up flex flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-7">
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Daftar Pegawai Pemegang Aset</h3>
                        <p class="text-xs text-slate-400">Aset aktif yang dipegang pegawai bidang</p>
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">
                    {{ $pemegangBidang->count() }} Terdata
                </span>
            </div>

            <div class="mt-2 flex-1 space-y-2 max-h-[360px] overflow-y-auto pr-1">
                @forelse ($pemegangBidang as $pemegang)
                    @php
                        $namaPg = $pemegang->pegawai?->nama_pegawai ?? '-';
                        $words = preg_split('/\s+/', trim($namaPg));
                        $inisialPg = mb_strtoupper(collect(array_slice($words, 0, 2))->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/80 p-3 ring-1 ring-slate-100 transition hover:bg-white hover:ring-disbun-200">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-disbun-700 text-xs font-bold text-white shadow-sm">
                                {{ $inisialPg }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">
                                    {{ $pemegang->aset?->kendaraan?->namaTampil() ?? $pemegang->aset?->barang?->nama_barang ?? $pemegang->aset?->merk ?? 'Aset Kedinasan' }}
                                </p>
                                <p class="truncate text-[11px] text-slate-400">
                                    <span class="font-mono text-slate-500">{{ $pemegang->aset?->nomor_kartu_barang ?? '-' }}</span> · <span class="font-medium text-slate-700">{{ $namaPg }}</span>
                                </p>
                            </div>
                        </div>
                        <span @class([
                            'shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold',
                            'bg-disbun-50 text-disbun-700' => $pemegang->aset?->kondisi === 'Baik',
                            'bg-amber-50 text-amber-700' => $pemegang->aset?->kondisi === 'Rusak Ringan',
                            'bg-rose-50 text-rose-700' => $pemegang->aset?->kondisi === 'Rusak Berat',
                            'bg-slate-100 text-slate-600' => ! in_array($pemegang->aset?->kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat']),
                        ])>
                            {{ $pemegang->aset?->kondisi ?? '-' }}
                        </span>
                    </div>
                @empty
                    <div class="flex h-28 flex-col items-center justify-center rounded-2xl bg-slate-50/50 text-center">
                        <svg class="h-6 w-6 text-disbun-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="mt-1.5 text-xs font-medium text-slate-400">Belum ada pemegang aset yang tercatat di bidang ini</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
