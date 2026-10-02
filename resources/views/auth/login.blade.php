<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk &middot; {{ config('app.name', 'INVENSBUN') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <main class="w-full lg:grid lg:grid-cols-2 lg:h-screen lg:overflow-hidden">

        {{-- ============================================================
             PANEL KIRI: BRANDING DINAS PERKEBUNAN
        ============================================================ --}}
        <div class="relative hidden overflow-hidden lg:flex">
            <div class="absolute inset-0 bg-gradient-to-br from-[#0c2e1b] via-[#0f331f] to-[#07160d]"></div>

            {{-- Ambient blur oranye & hijau --}}
            <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-disbun-amber-glow/25 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-28 -right-20 h-80 w-80 rounded-full bg-disbun-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute left-1/3 top-1/2 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl"></div>

            <div class="relative z-10 flex h-full flex-col justify-between overflow-hidden px-8 py-6 lg:px-12 lg:py-8">
                {{-- Brand bar --}}
                <div class="flex items-center gap-6">
                    <img src="{{ asset('image/logo-jatim.png') }}"
                         alt="Logo Pemerintah Provinsi Jawa Timur"
                         class="h-16 w-auto shrink-0 object-contain drop-shadow-md">
                    <span class="h-12 w-px bg-white/25"></span>
                    <img src="{{ asset('image/logo-invensbun.png') }}"
                         alt="INVENSBUN"
                         class="h-12 w-auto max-w-[280px] object-contain drop-shadow-md">
                </div>

                {{-- Headline --}}
                <div class="my-auto max-w-xl space-y-6">
                    <h1 class="text-4xl font-extrabold leading-[1.15] tracking-tight text-white xl:text-5xl">
                        Kelola Inventaris &amp; Berkas Aset Dinas secara
                        <span class="text-emerald-400">Presisi</span> &amp;
                        <span class="text-[#ff9900]">Transparan</span>.
                    </h1>
                    <p class="max-w-lg text-sm leading-relaxed text-white/70">
                        Portal resmi aset Dinas Perkebunan Provinsi Jawa Timur untuk
                        manajemen inventaris, berkas digital, dan pelaporan aset
                        secara terpadu, akurat, serta akuntabel.
                    </p>

                    {{-- 3 Kartu Fitur Mini --}}
                    <div class="grid gap-3 pt-2 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/15 bg-white/[0.05] p-3.5 backdrop-blur-md flex flex-col items-center text-center transition hover:-translate-y-1">
                            <span class="rounded-full bg-emerald-500/25 text-emerald-300 ring-4 ring-emerald-500/10 p-2.5 mb-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </span>
                            <p class="mt-2 text-sm font-bold text-white">Inventaris Aset</p>
                            <p class="mt-0.5 text-xs text-white/60">Tertata &amp; Akurat</p>
                            <span class="h-1 w-10 rounded-full bg-gradient-to-r from-emerald-400 to-transparent mt-3"></span>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/[0.05] p-3.5 backdrop-blur-md flex flex-col items-center text-center transition hover:-translate-y-1">
                            <span class="rounded-full bg-amber-500/25 text-amber-300 ring-4 ring-amber-500/10 p-2.5 mb-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            </span>
                            <p class="mt-2 text-sm font-bold text-white">Berkas Aset</p>
                            <p class="mt-0.5 text-xs text-white/60">Digital &amp; Aman</p>
                            <span class="h-1 w-10 rounded-full bg-gradient-to-r from-amber-400 to-transparent mt-3"></span>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/[0.05] p-3.5 backdrop-blur-md flex flex-col items-center text-center transition hover:-translate-y-1">
                            <span class="rounded-full bg-emerald-500/25 text-emerald-300 ring-4 ring-emerald-500/10 p-2.5 mb-2">
                                <svg class="h-5 w-5 text-[#ff9900]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </span>
                            <p class="mt-2 text-sm font-bold text-white">Transparan</p>
                            <p class="mt-0.5 text-xs text-white/60">Mendukung Akuntabilitas</p>
                            <span class="h-1 w-10 rounded-full bg-gradient-to-r from-emerald-400 to-transparent mt-3"></span>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <p class="text-xs text-white/50">
                    &copy; {{ date('Y') }} Dinas Perkebunan Provinsi Jawa Timur
                </p>
            </div>
        </div>

        {{-- ============================================================
             PANEL KANAN: FORM LOGIN
        ============================================================ --}}
        <div class="flex w-full flex-col justify-between overflow-hidden bg-disbun-warm-canvas px-8 py-6 lg:h-full lg:px-12 lg:py-8 lg:overflow-y-auto">
            <div class="my-auto w-full max-w-lg">

                {{-- Strip gelap pill (khusus mobile) --}}
                <div class="mb-6 lg:hidden">
                    <div class="flex items-center gap-3 rounded-2xl bg-[#0f331f] px-5 py-4">
                        <img src="{{ asset('image/logo-jatim.png') }}"
                             alt="Logo Pemerintah Provinsi Jawa Timur"
                             class="h-10 w-auto object-contain">
                        <span class="h-8 w-px bg-white/20"></span>
                        <img src="{{ asset('image/logo-invensbun.png') }}"
                             alt="INVENSBUN"
                             class="h-6 w-auto max-w-[140px] object-contain object-left">
                    </div>
                </div>

                {{-- Kartu login --}}
                <div class="rounded-3xl border border-white/80 bg-white/90 p-8 shadow-bento backdrop-blur-xl sm:p-10">

                    {{-- Badge status --}}
                    <span class="inline-flex items-center gap-2.5 rounded-full border border-disbun-card-border bg-disbun-50 px-3 py-1.5 text-xs font-semibold text-disbun-700">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        </span>
                        Portal Internal Pegawai &amp; Operator
                    </span>

                    <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-slate-800 sm:text-3xl">
                        Selamat Datang
                    </h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Silakan masuk dengan akun username &amp; password Anda.
                    </p>

                    <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-4">
                        @csrf

                        {{-- Username --}}
                        <div>
                            <label for="username" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Username / NIP
                            </label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </span>
                                <input id="username"
                                       type="text"
                                       name="username"
                                       value="{{ old('username') }}"
                                       required
                                       autofocus
                                       autocomplete="username"
                                       placeholder="Masukkan username atau NIP"
                                        class="w-full rounded-2xl border bg-slate-50 h-12 pl-12 pr-4 text-sm text-slate-900 transition placeholder:text-slate-400 focus:outline-none focus:ring-2 {{ $errors->has('username') ? 'border-red-300 bg-red-50/50 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-disbun-600 focus:ring-disbun-400/40' }}">
                            </div>
                        </div>

                        {{-- Password --}}
                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Password
                            </label>
                            <div class="relative" id="password-wrapper">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10a2 2 0 012-2h14a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7zm4-2V7a5 5 0 1110 0v1"/></svg>
                                </span>
                                <input id="password"
                                       type="password"
                                       name="password"
                                       required
                                       autocomplete="current-password"
                                       placeholder="Masukkan password Anda"
                                        class="w-full rounded-2xl border bg-slate-50 h-12 pl-12 pr-12 text-sm text-slate-900 transition placeholder:text-slate-400 focus:outline-none focus:ring-2 {{ $errors->has('password') ? 'border-red-300 bg-red-50/50 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-disbun-600 focus:ring-disbun-400/40' }}">
                                <button type="button"
                                        id="toggle-password"
                                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 transition hover:text-disbun-700 focus:outline-none"
                                        aria-label="Tampilkan password">
                                    <svg id="icon-eye" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg id="icon-eye-off" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Banner error --}}
                        @if ($errors->any())
                            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                @foreach ($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        {{-- Remember me --}}
                        <div class="flex items-center justify-between gap-3">
                            <label class="flex cursor-pointer select-none items-center gap-2.5">
                                <input type="checkbox"
                                       name="remember"
                                       value="1"
                                       class="h-5 w-5 rounded-lg border-slate-300 accent-disbun-600 focus:ring-disbun-400">
                                <span class="text-sm font-medium text-slate-700">Ingat saya di perangkat ini</span>
                            </label>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-disbun-50 px-2.5 py-1 text-xs font-medium text-disbun-700 ring-1 ring-inset ring-disbun-100"
                                  title="Saat 'Ingat saya' dicentang, sesi Anda tetap aktif lewat cookie setelah menutup browser.">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                ⓘ Berlaku 30 hari
                            </span>
                        </div>

                        {{-- Submit --}}
                        <button type="submit"
                                class="w-full h-12 rounded-2xl bg-gradient-to-r from-[#07834d] via-[#099955] to-[#0ba35c] hover:from-[#067343] hover:to-[#099955] text-white font-bold text-sm tracking-wide shadow-md transition active:scale-[0.99] flex items-center justify-center">
                            Masuk ke Dashboard
                        </button>
                    </form>

                    {{-- Banner catatan keamanan data --}}
                    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-disbun-card-border bg-disbun-50 px-4 py-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-disbun-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <p class="text-xs leading-relaxed text-slate-600">
                            Data Anda dilindungi. Aktivitas login tercatat dan seluruh
                            perubahan data aset tercatat dalam sistem untuk keperluan
                            akuntabilitas.
                        </p>
                    </div>
                </div>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Belum punya akun? Hubungi admin Dinas Perkebunan.
                </p>
            </div>
        </div>
    </div>

    <script>
        const toggle = document.getElementById('toggle-password');
        const pw = document.getElementById('password');
        const eye = document.getElementById('icon-eye');
        const eyeOff = document.getElementById('icon-eye-off');

        if (toggle && pw && eye && eyeOff) {
            toggle.addEventListener('click', () => {
                const show = pw.type === 'password';
                pw.type = show ? 'text' : 'password';
                eye.classList.toggle('hidden', show);
                eyeOff.classList.toggle('hidden', !show);
                toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
            });
        }
    </script>
</body>
</html>
