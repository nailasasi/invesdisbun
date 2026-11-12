<div class="space-y-4">
    {{-- Section Header: Monitoring RKBMD --}}
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-800 sm:text-lg">Monitoring RKBMD</h2>
                <p class="text-xs text-slate-400">Pemantauan usulan kebutuhan barang untuk {{ $skpd->nama_skpd }}</p>
            </div>
        </div>
        <a href="{{ route('rkbmd.index') }}"
           class="inline-flex w-fit items-center gap-2 rounded-xl bg-disbun-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800">
            <span>Buka Modul RKBMD</span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    </div>

    {{-- Banner Pengingat Usulan Draft / Status Bersih --}}
    @if ($rkbmdDraftCount > 0)
        <div class="animate-fade-in-up flex flex-col justify-between gap-3.5 rounded-3xl border border-amber-200/80 bg-gradient-to-r from-amber-50 via-amber-50/70 to-white p-5 shadow-bento sm:flex-row sm:items-center sm:p-6">
            <div class="flex items-start gap-3.5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
                <div>
                    <h4 class="text-sm font-bold text-amber-900">Pengingat Usulan Draft Belum Diajukan</h4>
                    <p class="mt-0.5 text-xs text-amber-700">
                        Terdapat <span class="font-bold underline">{{ $rkbmdDraftCount }} usulan Draft</span> yang belum diajukan ke Pengurus Barang. Segera ajukan agar masuk proses verifikasi.
                    </p>
                </div>
            </div>
            <a href="{{ route('rkbmd.index') }}"
               class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-gradient-to-r from-disbun-amber to-disbun-amber-glow px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:brightness-105">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Ajukan Usulan Draft
            </a>
        </div>
    @else
        <div class="animate-fade-in-up flex items-center gap-3.5 rounded-3xl border border-disbun-card-border bg-gradient-to-r from-emerald-50/60 to-white p-4 shadow-bento sm:p-5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div class="min-w-0">
                <h4 class="text-sm font-bold text-slate-800">Status RKBMD Bersih</h4>
                <p class="text-xs text-slate-500">Seluruh usulan RKBMD unit Anda telah diajukan atau selesai diverifikasi. Tidak ada draf tertahan.</p>
            </div>
        </div>
    @endif

    {{-- 4 Kartu Status Aktual RKBMD (1 Baris 4 Kolom) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Status Draft (Oranye) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Draft</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($rkbmdStats->draft ?? 0, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">belum diajukan ke pengurus</p>
        </div>

        {{-- Status Diajukan (Biru) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 40ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Diajukan</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($rkbmdStats->diajukan ?? 0, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">menunggu verifikasi dinas</p>
        </div>

        {{-- Status Disetujui Pengurus Barang (Hijau) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 80ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Disetujui Pengurus</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-disbun-600 to-disbun-800 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($rkbmdStats->disetujui ?? 0, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">disetujui pengurus barang</p>
        </div>

        {{-- Status Ditolak (Merah) --}}
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 120ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Ditolak</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-red-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($rkbmdStats->ditolak ?? 0, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">ditolak pengurus barang</p>
        </div>
    </div>
</div>
