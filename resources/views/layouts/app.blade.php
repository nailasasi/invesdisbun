<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'INVENSBUN')</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans bg-disbun-warm-canvas text-slate-800 antialiased">

<div class="flex h-screen overflow-hidden">

  {{-- Backdrop mobile --}}
  <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-900/50 backdrop-blur-sm lg:hidden"></div>

  {{-- SIDEBAR (floating docked) --}}
  @include('layouts.partials.sidebar')

  {{-- MAIN --}}
  <main id="main-content" class="flex-1 overflow-y-auto min-h-screen transition-all duration-300 ease-in-out lg:ml-72 pr-3 py-3 lg:pr-6">
    <div class="mx-auto max-w-[1400px] animate-fade-in">

      {{-- HEADER (floating glass card) --}}
      @include('layouts.partials.header')

      @yield('content')
    </div>
  </main>
</div>

{{-- Utilitas kelas untuk state collapse sidebar (desktop) --}}
<div class="hidden lg:ml-6 lg:-translate-x-full" aria-hidden="true"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const backdrop = document.getElementById('sidebar-backdrop');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const isDesktop = window.innerWidth >= 1024;

        if (isDesktop) {
            const isCollapsed = sidebar.classList.contains('lg:-translate-x-full');
            if (isCollapsed) {
                sidebar.classList.add('lg:translate-x-0');
                sidebar.classList.remove('lg:-translate-x-full');
                mainContent.classList.remove('lg:ml-6');
                mainContent.classList.add('lg:ml-72');
            } else {
                sidebar.classList.add('lg:-translate-x-full');
                sidebar.classList.remove('lg:translate-x-0');
                mainContent.classList.remove('lg:ml-72');
                mainContent.classList.add('lg:ml-6');
            }
            sidebarToggle.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
        } else {
            const isHidden = sidebar.classList.contains('-translate-x-full');
            if (isHidden) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            sidebarToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        }
    }

    const backdrop = document.getElementById('sidebar-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 1024) {
                return;
            }
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('sidebar-toggle').setAttribute('aria-expanded', 'false');
        });
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

@stack('modals')

@stack('scripts')

</body>
</html>
