{{-- HEADER: Floating Glass Card --}}
<header class="sticky top-3 z-30 mb-6 flex items-center gap-4 rounded-3xl border border-white/80 bg-white/90 px-6 py-3.5 shadow-bento backdrop-blur-md">

    {{-- Kiri: toggle sidebar + judul halaman --}}
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <button id="sidebar-toggle" aria-expanded="true" aria-controls="sidebar"
                class="rounded-xl bg-slate-50 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-disbun-700"
                onclick="toggleSidebar()" aria-label="Buka/Tutup menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="min-w-0">
            <h1 class="truncate text-base font-extrabold tracking-tight text-slate-800">@yield('page-title', 'INVENSBUN')</h1>
            <p class="hidden truncate text-xs text-slate-400 md:block">@yield('breadcrumb')</p>
        </div>
    </div>

    {{-- Kanan: status, tanggal, profil --}}
    <div class="flex shrink-0 items-center gap-3">
        {{-- Status koneksi --}}
        <span class="hidden items-center gap-1.5 rounded-full bg-disbun-50 px-3 py-1.5 text-xs font-semibold text-disbun-700 sm:inline-flex">
            <span class="relative flex h-1.5 w-1.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-disbun-400 opacity-75"></span>
                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-disbun-500"></span>
            </span>
            Disbun Jatim Connected
        </span>

        {{-- Tanggal --}}
        <span class="inline-flex items-center gap-2 rounded-2xl border border-disbun-card-border bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm">
            <svg class="h-4 w-4 text-disbun-amber" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ now()->translatedFormat('l, d M Y') }}
        </span>

        {{-- Profil --}}
        <div class="relative" id="user-menu-root">
            <button id="user-menu-btn" class="flex items-center gap-2.5 rounded-full bg-white py-1 pl-1 pr-3 shadow-sm ring-2 ring-disbun-100 transition hover:ring-disbun-200" aria-label="Menu profil">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-disbun-600 to-disbun-900 text-xs font-bold text-white">
                    {{ strtoupper(substr(auth()->user()->pegawai?->nama_pegawai ?? auth()->user()->username ?? '-', 0, 2)) }}
                </span>
                <span class="hidden text-left leading-tight md:block">
                    <span class="block text-sm font-semibold text-slate-800">{{ auth()->user()->pegawai?->nama_pegawai ?? auth()->user()->username ?? '-' }}</span>
                    <span class="block text-[11px] text-slate-400">{{ auth()->user()?->role?->nama_role ?? '-' }}</span>
                </span>
                <svg id="user-menu-chevron" class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div id="user-menu" class="absolute right-0 top-full z-50 mt-2 hidden w-60 overflow-hidden rounded-2xl border border-disbun-card-border bg-white p-1.5 shadow-bento animate-fade-in">
                <div class="mb-1 border-b border-disbun-card-border px-3 pb-2 pt-1.5">
                    <p class="text-sm font-semibold text-slate-800">{{ auth()->user()->pegawai?->nama_pegawai ?? auth()->user()->username ?? '-' }}</p>
                    <p class="text-xs text-slate-400">{{ auth()->user()->username ?? '-' }}</p>
                </div>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-disbun-50">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('profile.password') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-disbun-50">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M15 9l6-6m-12 3H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2v-4M9 11h2m0 0h4"/></svg>
                    Ganti Password
                </a>
                <div class="my-1 border-t border-disbun-card-border"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
