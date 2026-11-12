{{-- HERO UNIT BIDANG --}}
<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0c2e1b] via-[#133e25] to-[#1c5531] p-6 text-white shadow-bento sm:p-8">
    {{-- Ambient glow kiri (menerangi badge, nama SKPD, dan identitas agar kaya warna hijau gelap) --}}
    <div class="absolute -left-12 -top-12 h-64 w-64 rounded-full bg-emerald-500/15 blur-3xl"></div>
    {{-- Ambient glow kanan (amber hangat khas Invensbun) --}}
    <div class="absolute -right-14 -top-14 h-56 w-56 rounded-full bg-disbun-amber-glow/20 blur-3xl"></div>
    {{-- Ambient glow bawah-tengah (penyeimbang pencahayaan gradasi hijau emerald) --}}
    <div class="absolute -bottom-16 left-1/2 h-48 w-48 rounded-full bg-emerald-400/15 blur-3xl"></div>

    <div class="relative flex flex-col justify-between gap-6 md:flex-row md:items-center">
        <div>
            <p class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-disbun-100 backdrop-blur">
                <svg class="h-3.5 w-3.5 text-disbun-amber-glow" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Dashboard Admin Bidang
            </p>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl text-white">{{ $skpd->nama_skpd }}</h1>
            <p class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-white/90">
                <span class="inline-flex items-center gap-1.5 font-semibold text-amber-300">
                    <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $diri->nama_pegawai ?? auth()->user()->username }}
                </span>
                @if (!empty($diri->nip))
                    <span class="text-white/70 text-xs font-normal">· NIP. {{ $diri->nip }}</span>
                @endif
                <span class="text-white/40 hidden sm:inline">•</span>
                <span class="text-white/70 text-xs hidden sm:inline">{{ now()->translatedFormat('l, d F Y') }}</span>
            </p>
            <p class="mt-2 max-w-xl text-xs text-white/80 sm:text-sm leading-relaxed">
                Ringkasan pengelolaan aset dan pemantauan usulan Rencana Kebutuhan Barang Milik Daerah (RKBMD) di lingkungan {{ $skpd->nama_skpd }}.
            </p>
        </div>
        <div class="flex shrink-0 items-center">
            <a href="{{ $diri ? route('aset-barang.show', ['pegawai' => $diri->id_pegawai, 'from' => 'dashboard']) : '#' }}"
               class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-disbun-amber to-disbun-amber-glow px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-amber-500/20 transition hover:brightness-105">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Lihat Aset Saya
            </a>
        </div>
    </div>
</div>

{{-- BAGIAN PERTAMA: INFORMASI ASET BIDANG --}}
<div class="mt-6">
    @include('dashboard.partials.admin-bidang.aset-bidang')
</div>

{{-- BAGIAN KEDUA: MONITORING RKBMD --}}
<div class="mt-8">
    @include('dashboard.partials.admin-bidang.rkbmd-monitoring')
</div>
