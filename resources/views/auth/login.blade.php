<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Login') }} - Masuk</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-950 font-sans antialiased">
    <div class="flex min-h-screen">
        {{-- Panel kiri: branding --}}
        <div class="relative hidden w-1/2 overflow-hidden lg:block">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-500"></div>
            <div class="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-emerald-300/20 blur-3xl"></div>
            <div class="relative z-10 flex h-full flex-col justify-between p-12 text-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-bold leading-tight">SISINVEBUN</p>
                        <p class="text-xs text-emerald-100/80">Inventaris Aset Dinas Perkebunan</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <h1 class="text-4xl font-extrabold leading-tight">
                        Kelola inventaris aset dinas<br>menjadi mudah &amp; rapi.
                    </h1>
                    <p class="max-w-md text-emerald-50/90">
                        Sistem informasi inventaris aset untuk Dinas Perkebunan.
                        Akses diberikan oleh admin melalui akun pengguna masing-masing.
                    </p>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-sm backdrop-blur">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Login aman
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-sm backdrop-blur">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Remember me &amp; Cookie
                        </span>
                    </div>
                </div>

                <p class="text-xs text-emerald-100/70">&copy; {{ date('Y') }} Dinas Perkebunan. All rights reserved.</p>
            </div>
        </div>

        {{-- Panel kanan: form login --}}
        <div class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <div class="flex items-center gap-3 text-white">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <div>
                            <p class="font-bold text-white">SISINVEBUN</p>
                            <p class="text-xs text-slate-400">Inventaris Aset Dinas Perkebunan</p>
                        </div>
                    </div>
                </div>

                <h2 class="text-3xl font-extrabold text-white">Selamat datang kembali</h2>
                <p class="mt-2 text-slate-400">Silakan masuk dengan akun username &amp; password Anda.</p>

                <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
                    @csrf

                    {{-- Username --}}
                    <div>
                        <label for="username" class="mb-1.5 block text-sm font-medium text-slate-300">Username</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </span>
                            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder="Masukkan username Anda"
                                class="w-full rounded-xl border border-slate-700 bg-slate-900 py-3 pl-11 pr-4 text-slate-100 placeholder-slate-500 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 {{ $errors->has('username') ? 'border-red-500' : '' }}">
                        </div>
                    </div>

                    {{-- Password --}}
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="text-sm font-medium text-slate-300">Password</label>
                        </div>
                        <div class="relative" id="password-wrapper">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10a2 2 0 012-2h14a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7zm4-2V7a5 5 0 1110 0v1"/></svg>
                            </span>
                            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password Anda"
                                class="w-full rounded-xl border border-slate-700 bg-slate-900 py-3 pl-11 pr-11 text-slate-100 placeholder-slate-500 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 {{ $errors->has('password') ? 'border-red-500' : '' }}">
                            <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500 hover:text-slate-300" aria-label="Tampilkan password">
                                <svg id="icon-eye" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg id="icon-eye-off" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-xl border border-red-800 bg-red-950/50 px-4 py-3 text-sm text-red-300">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    {{-- Remember me --}}
                    <div class="flex items-center justify-between">
                        <label class="flex cursor-pointer select-none items-center gap-2.5">
                            <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-emerald-600 accent-emerald-500 focus:ring-emerald-500">
                            <span class="text-sm text-slate-300">Ingat saya</span>
                        </label>
                        <span class="flex items-center gap-1.5 text-xs text-slate-500" title="Saat 'Ingat saya' dicentang, sesi Anda tetap aktif lewat cookie bahkan setelah menutup browser.">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Cookie sesi
                        </span>
                    </div>

                    <button type="submit"
                        class="group flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-900/40 transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-950">
                        Masuk
                        <svg class="h-5 w-5 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </button>
                </form>

                <p class="mt-8 text-center text-sm text-slate-500">
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
        if (toggle && pw) {
            toggle.addEventListener('click', () => {
                const show = pw.type === 'password';
                pw.type = show ? 'text' : 'password';
                eye.classList.toggle('hidden', show);
                eyeOff.classList.toggle('hidden', !show);
            });
        }
    </script>
</body>
</html>
