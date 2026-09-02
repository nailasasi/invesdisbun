<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'INVENSBUN')</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased">

<div class="flex h-screen overflow-hidden">

  {{-- Backdrop mobile --}}
  <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-slate-900/60 backdrop-blur-sm lg:hidden"></div>

  {{-- SIDEBAR --}}
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 -translate-x-full flex-col bg-slate-900 text-slate-400 transition-transform duration-300 ease-in-out lg:static lg:z-auto lg:translate-x-0">

    <div class="relative overflow-hidden px-6 py-6">
      <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-emerald-500/20 blur-2xl"></div>
      <div class="relative flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-glow">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <div>
          <p class="font-bold tracking-tight text-white leading-tight">INVENSBUN</p>
          <p class="text-[11px] text-slate-500 leading-tight">Portal Aset Terintegrasi</p>
        </div>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-4">
      <div class="space-y-6 text-sm">
        {{-- DASHBOARD --}}
        <div>
          <p class="mb-1 px-3 text-[10px] font-bold tracking-widest text-slate-600 uppercase">Dashboard</p>
          <a href="{{ route('dashboard') }}"
             class="group flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('dashboard') ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-glow' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
          </a>
        </div>

        {{-- DATA ASET --}}
        <div>
          <p class="mb-1 px-3 text-[10px] font-bold tracking-widest text-slate-600 uppercase">Data Aset</p>
          <a href="{{ route('aset-barang.index') }}"
             class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('aset-barang.*') ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-glow' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Aset Barang
          </a>
          <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7a2 2 0 012-2h10a2 2 0 012 2v14M9 21v-4h6v4M9 10h.01M15 10h.01M9 14h.01M15 14h.01"/></svg>
            Aset Ruangan
          </a>
          <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 18H3V5h11v13H9m-4 0a2 2 0 104 0m-4 0a2 2 0 114 0m4-8h5l3 3v5h-8m0 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
            Kendaraan
          </a>
          <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            Tanah
          </a>
        </div>

        {{-- PENGELOLAAN ASET --}}
        @if (auth()->user()?->role?->nama_role === 'Admin Aset')
        <div>
          <p class="mb-1 px-3 text-[10px] font-bold tracking-widest text-slate-600 uppercase">Pengelolaan Aset</p>
          <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            Monitoring Aset
          </a>
          <a href="{{ route('mutasi-aset.index') }}"
             class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('mutasi-aset.*') ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-glow' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 9a8 8 0 00-14.32-3.296L4 8m16 8l-1.68 2.296A8 8 0 014 15"/></svg>
            Mutasi Aset
          </a>
          <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6m4-6v6"/></svg>
            Penghapusan Aset
          </a>
        </div>
        @endif

        {{-- DOKUMEN --}}
        <div>
          <p class="mb-1 px-3 text-[10px] font-bold tracking-widest text-slate-600 uppercase">Dokumen</p>
          <a href="{{ route('laporan.bulanan') }}"
             class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('laporan.*') ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-glow' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Laporan Bulanan
          </a>
          <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5"/></svg>
            Template Dokumen
          </a>
        </div>

        @if (auth()->user()?->role?->nama_role === 'Admin Aset')
        <div>
          <p class="mb-1 px-3 text-[10px] font-bold tracking-widest text-slate-600 uppercase">Administrasi</p>
          <a href="{{ route('user.index') }}"
             class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ request()->routeIs('user.*') ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-glow' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            User Management
          </a>
          <a href="#" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Pengaturan
          </a>
        </div>
        @endif
      </div>
    </nav>

    <div class="border-t border-white/5 px-3 py-4 text-sm">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-rose-400 transition hover:bg-rose-500/10 hover:text-rose-300">
          <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Keluar
        </button>
      </form>
    </div>
  </aside>

  {{-- MAIN --}}
  <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

    {{-- HEADER --}}
    <header class="z-20 flex shrink-0 items-center justify-between gap-3 border-b border-slate-200/70 bg-white/80 px-4 py-3 backdrop-blur-xl lg:px-6">
      <div class="flex min-w-0 items-center gap-3">
        <button id="sidebar-toggle" class="rounded-xl border border-slate-200 bg-white p-2 text-slate-500 shadow-soft transition hover:bg-slate-50 hover:text-slate-700 lg:hidden" aria-label="Buka menu">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="min-w-0">
          <h1 class="truncate text-lg font-bold tracking-tight text-slate-900">@yield('page-title', 'INVENSBUN')</h1>
          <p class="truncate text-xs text-slate-400">@yield('breadcrumb')</p>
        </div>
      </div>

      <div class="flex shrink-0 items-center gap-3">
        <span class="hidden items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 sm:inline-flex">
          <span class="relative flex h-1.5 w-1.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </span>
          Online
        </span>

        {{-- Profil dropdown --}}
        <div class="relative" id="user-menu-root">
          <button id="user-menu-btn" class="flex items-center gap-2.5 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-3 shadow-soft transition hover:bg-slate-50" aria-label="Menu profil">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-sm font-bold text-white">
              {{ strtoupper(substr(auth()->user()->pegawai?->nama_pegawai ?? auth()->user()->username ?? '-', 0, 2)) }}
            </span>
            <span class="hidden text-left leading-tight md:block">
              <span class="block text-sm font-semibold text-slate-800">{{ auth()->user()->pegawai?->nama_pegawai ?? auth()->user()->username ?? '-' }}</span>
              <span class="block text-[11px] text-slate-400">{{ auth()->user()?->role?->nama_role ?? '-' }}</span>
            </span>
            <svg id="user-menu-chevron" class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </button>

          <div id="user-menu" class="absolute right-0 top-full z-30 mt-2 hidden w-60 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft animate-fade-in">
            <div class="mb-1 border-b border-slate-100 px-3 pb-2 pt-1.5">
              <p class="text-sm font-semibold text-slate-800">{{ auth()->user()->pegawai?->nama_pegawai ?? auth()->user()->username ?? '-' }}</p>
              <p class="text-xs text-slate-400">{{ auth()->user()->username ?? '-' }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
              <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
              Dashboard
            </a>
            <a href="{{ route('profile.password') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
              <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M15 9l6-6m-12 3H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2v-4M9 11h2m0 0h4"/></svg>
              Ganti Password
            </a>
            <div class="my-1 border-t border-slate-100"></div>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Keluar
              </button>
            </form>
          </div>
        </div>
      </div>
    </header>

    {{-- CONTENT --}}
    <main class="flex-1 overflow-y-auto overscroll-contain">
      <div class="mx-auto max-w-[1400px] p-4 lg:p-6 animate-fade-in">
        @yield('content')
      </div>
    </main>
  </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const sidebarToggle = document.getElementById('sidebar-toggle');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.contains('-translate-x-full') ? openSidebar() : closeSidebar();
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    const userMenu = document.getElementById('user-menu');
    const userMenuBtn = document.getElementById('user-menu-btn');
    const chevron = document.getElementById('user-menu-chevron');
    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const closed = userMenu.classList.contains('hidden');
            userMenu.classList.toggle('hidden', !closed);
            chevron.classList.toggle('rotate-180', closed);
        });
        document.addEventListener('click', (e) => {
            if (!document.getElementById('user-menu-root').contains(e.target)) {
                userMenu.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        });
    }
</script>

@stack('scripts')

</body>
</html>