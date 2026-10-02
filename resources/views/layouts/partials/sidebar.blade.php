{{-- SIDEBAR: Floating Docked --}}
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 my-3 ml-3 flex w-64 shrink-0 transform flex-col overflow-hidden rounded-3xl border border-white/10 bg-[#0f331f]/95 text-slate-200 shadow-2xl backdrop-blur-xl transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">

    {{-- Struktur utama: logo & footer diam, hanya daftar menu yang tergulung --}}
    <div class="flex flex-col h-full overflow-hidden">

    {{-- Branding: logo terang (latar transparan) + efek glow --}}
    <div class="relative overflow-hidden shrink-0 border-b border-white/10 px-2 pt-3 pb-5">
        <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-disbun-amber-glow/20 blur-2xl"></div>
        <div class="absolute -bottom-10 -left-6 h-24 w-24 rounded-full bg-emerald-400/10 blur-2xl"></div>
        <a href="{{ route('dashboard') }}" class="relative block w-full transition hover:scale-[1.02]">
            <img src="{{ asset('image/logo-invensbun.png') }}"
                 alt="Logo INVENSBUN"
                 class="mx-auto block w-full max-w-[260px] h-auto object-contain drop-shadow-[0_1px_10px_rgba(255,255,255,0.25)] brightness-110">
        </a>
    </div>

    {{-- Navigasi (hanya area ini yang digulir) --}}
    <nav class="flex-1 overflow-y-auto pr-1">
        <div class="space-y-6 px-3 pt-3 pb-4 text-sm">

            {{-- DASHBOARD UTAMA --}}
            <div>
                <p class="mb-1 px-3 text-[10px] font-extrabold uppercase tracking-widest text-emerald-400/60">Dashboard Utama</p>
                <a href="{{ route('dashboard') }}"
                   @class([
                       'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                       'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('dashboard'),
                       'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('dashboard'),
                   ])>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
            </div>

            {{-- LAYANAN --}}
            <div>
                <p class="mb-1 px-3 text-[10px] font-extrabold uppercase tracking-widest text-emerald-400/60">Layanan</p>
                <a href="{{ route('layanan.izin-kendaraan.index') }}"
                   @class([
                       'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                       'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('layanan.izin-kendaraan.*'),
                       'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('layanan.izin-kendaraan.*'),
                   ])>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Surat Izin Kendaraan
                </a>
            </div>

            {{-- INVENTARIS ASET --}}
            <div>
                <p class="mb-1 px-3 text-[10px] font-extrabold uppercase tracking-widest text-emerald-400/60">Inventaris Aset</p>

                <a href="{{ route('aset-barang.index') }}"
                   @class([
                       'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                       'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('aset-barang.*'),
                       'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('aset-barang.*'),
                   ])>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Aset Barang
                </a>

                <a href="{{ route('aset-ruangan.index') }}"
                   @class([
                       'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                       'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('aset-ruangan.*'),
                       'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('aset-ruangan.*'),
                   ])>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7a2 2 0 012-2h10a2 2 0 012 2v14M9 21v-4h6v4M9 10h.01M15 10h.01M9 14h.01M15 14h.01"/></svg>
                    Aset Ruangan
                </a>

                <a href="{{ route('kendaraan.index') }}"
                   @class([
                       'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                       'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('kendaraan.*'),
                       'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('kendaraan.*'),
                   ])>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 18H3V5h11v13H9m-4 0a2 2 0 104 0m-4 0a2 2 0 114 0m4-8h5l3 3v5h-8m0 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                    Kendaraan
                </a>

                @if (in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'UPT P2BTP']))
                    <a href="{{ route('tanah.index') }}"
                       @class([
                           'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                           'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('tanah.*'),
                           'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('tanah.*'),
                       ])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        Tanah
                    </a>
                @endif

                <a href="{{ route('rkbmd.index') }}"
                   @class([
                       'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                       'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('rkbmd.*'),
                       'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('rkbmd.*'),
                   ])>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    RKBMD
                </a>
            </div>

            @if (auth()->user()?->role?->nama_role === 'Admin Aset')
                {{-- PENGELOLAAN ASET --}}
                <div>
                    <p class="mb-1 px-3 text-[10px] font-extrabold uppercase tracking-widest text-emerald-400/60">Pengelolaan Aset</p>

                    <a href="{{ route('monitoring-aset.index') }}"
                       @class([
                           'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                           'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('monitoring-aset.*'),
                           'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('monitoring-aset.*'),
                       ])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        Monitoring Aset
                    </a>

                    <a href="{{ route('mutasi-aset.index') }}"
                       @class([
                           'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                           'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('mutasi-aset.*'),
                           'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('mutasi-aset.*'),
                       ])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 9a8 8 0 00-14.32-3.296L4 8m16 8l-1.68 2.296A8 8 0 014 15"/></svg>
                        Mutasi Aset
                    </a>

                    <a href="{{ route('penghapusan.index') }}"
                       @class([
                           'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                           'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('penghapusan.*'),
                           'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('penghapusan.*'),
                       ])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6m4-6v6"/></svg>
                        Penghapusan Aset
                    </a>
                </div>

                {{-- DOKUMEN --}}
                <div>
                    <p class="mb-1 px-3 text-[10px] font-extrabold uppercase tracking-widest text-emerald-400/60">Dokumen</p>

                    <a href="{{ route('laporan.bulanan') }}"
                       @class([
                           'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                           'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('laporan.*'),
                           'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('laporan.*'),
                       ])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Laporan Bulanan
                    </a>

                    <a href="{{ route('template-dokumen.index') }}"
                       @class([
                           'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                           'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('template-dokumen.*'),
                           'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('template-dokumen.*'),
                       ])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5"/></svg>
                        Template Dokumen
                    </a>
                </div>

                {{-- ADMINISTRASI --}}
                <div>
                    <p class="mb-1 px-3 text-[10px] font-extrabold uppercase tracking-widest text-emerald-400/60">Administrasi</p>

                    <a href="{{ route('user.index') }}"
                       @class([
                           'flex items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 font-medium transition',
                           'border-emerald-500 bg-gradient-to-r from-emerald-500/25 to-emerald-600/10 font-bold text-white' => request()->routeIs('user.*'),
                           'border-transparent text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('user.*'),
                       ])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        User Management
                    </a>

                    <a href="#" class="flex items-center gap-3 rounded-xl border-l-4 border-transparent px-3 py-2.5 font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Pengaturan
                    </a>
                </div>
            @endif
        </div>
    </nav>

    {{-- Footer: status sinkronisasi & logout (selalu di bawah) --}}
    <div class="shrink-0 mt-auto space-y-2 px-3 pb-4">
        <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2.5">
            <div class="flex items-center justify-between gap-2">
                <p class="flex items-center gap-2 text-xs font-semibold text-emerald-100">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    </span>
                    Disbun Cloud Sinkron
                </p>
                <span class="shrink-0 rounded-md bg-white/10 px-1.5 py-0.5 text-[9px] font-bold text-emerald-200/70">v2.4 Pro</span>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-rose-300 transition hover:bg-rose-500/10 hover:text-rose-200">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Keluar
            </button>
        </form>
    </div>
    </div>
</aside>
