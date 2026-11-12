@extends('layouts.app')

@section('title', 'Detail Pegawai - ' . $pegawai->nama_pegawai)

@section('page-title', 'Detail Pegawai')

@section('content')
    @php
        $user = $pegawai->user;
        $isAktif = $user?->status_user === 'aktif';
        $isSelf = auth()->id() === $user?->id_user;
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';
        $initial = mb_substr((string) ($pegawai->nama_pegawai ?: '?'), 0, 1);
        $defaultRoleId = $roleList->firstWhere('nama_role', 'Pegawai')?->id_role ?? $roleList->first()?->id_role;
        $nipLen = strlen(trim((string) $pegawai->nip));
        $nipCukupPanjang = $nipLen >= 8;

        // Konfigurasi icon & badge timeline aktivitas
        $logMeta = [
            'pegawai.created' => [
                'label' => 'Tambah Pegawai',
                'chip' => 'bg-emerald-100 text-emerald-800',
                'icon' => 'bg-emerald-50 text-emerald-700',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>',
            ],
            'pegawai.updated' => [
                'label' => 'Edit Pegawai',
                'chip' => 'bg-slate-100 text-slate-700',
                'icon' => 'bg-slate-50 text-slate-600',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
            ],
            'user.created' => [
                'label' => 'Buat Akun',
                'chip' => 'bg-disbun-100 text-disbun-800',
                'icon' => 'bg-disbun-50 text-disbun-700',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>',
            ],
            'user.updated' => [
                'label' => 'Edit Akun',
                'chip' => 'bg-slate-100 text-slate-700',
                'icon' => 'bg-blue-50 text-blue-600',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
            ],
            'user.role_changed' => [
                'label' => 'Ubah Role',
                'chip' => 'bg-indigo-100 text-indigo-700',
                'icon' => 'bg-indigo-50 text-indigo-600',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
            ],
            'user.status_changed' => [
                'label' => 'Status Akun',
                'chip' => 'bg-amber-100 text-amber-800',
                'icon' => 'bg-amber-50 text-amber-600',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
            ],
        ];
        $logMetaDefault = [
            'label' => 'Aktivitas',
            'chip' => 'bg-slate-100 text-slate-700',
            'icon' => 'bg-slate-50 text-slate-600',
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ];
    @endphp

    {{-- Navigasi Kembali --}}
    <div class="mb-4">
        <a href="{{ route('master-data.pegawai.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-disbun-700">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Manajemen Pegawai
        </a>
    </div>

    {{-- Header Identitas Utama --}}
    <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-disbun-700 text-xl font-extrabold text-white shadow-sm">
                {{ mb_strtoupper($initial) }}
            </div>
            <div class="min-w-0">
                <h2 class="truncate text-2xl font-extrabold tracking-tight text-slate-900">{{ $pegawai->nama_pegawai }}</h2>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <span class="text-xs text-slate-400">NIP. {{ $pegawai->nip ?? '-' }}</span>
                    @if ($user)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700">
                            {{ $user?->role?->nama_role ?? 'Tanpa Role' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-0.5 text-[11px] font-bold {{ $isAktif ? 'bg-disbun-100 text-disbun-800' : 'bg-red-100 text-red-700' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $isAktif ? 'bg-disbun-600' : 'bg-red-500' }}"></span>
                            {{ $isAktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">Belum Ada Akun</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- Tab Navigation (TEPAT DUA TAB) --}}
    <div class="mb-4 overflow-x-auto">
        <div class="flex w-max min-w-full items-center gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-2xs">
            <button type="button" data-tab-target="tab-pegawai-akun"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl bg-disbun-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition" role="tab">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                1. Data Pegawai &amp; Akun
                @if ($user)
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $isAktif ? 'bg-disbun-100 text-disbun-800' : 'bg-red-100 text-red-700' }}">{{ $isAktif ? 'Aktif' : 'Nonaktif' }}</span>
                @else
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">Belum Ada Akun</span>
                @endif
            </button>
            <button type="button" data-tab-target="tab-riwayat"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition hover:text-disbun-700" role="tab">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                2. Riwayat Aktivitas
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $riwayatLogs->count() }}</span>
            </button>
        </div>
    </div>

    {{-- ===== TAB 1: DATA PEGAWAI & AKUN ===== --}}
    <div data-tab-panel id="tab-pegawai-akun" class="mt-4 space-y-4">
        {{-- Bagian Pertama: Identitas Pegawai --}}
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-bold text-slate-900">Identitas Pegawai</h3>
                <p class="text-xs text-slate-400">Data fisik pegawai, penempatan SKPD, dan ruangan kerja.</p>
            </div>

            <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">NIP</p>
                    <p class="font-mono text-sm font-medium text-slate-800">{{ $pegawai->nip ?? '-' }}</p>
                </div>

                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama Pegawai</p>
                    <p class="text-sm font-medium text-slate-800">{{ $pegawai->nama_pegawai }}</p>
                </div>

                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Jabatan</p>
                    <p class="text-sm font-medium text-slate-800">{{ $pegawai->jabatan ?? '-' }}</p>
                </div>

                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">SKPD</p>
                    @if ($pegawai->skpd)
                        <p class="text-sm font-medium text-slate-800">{{ $pegawai->skpd->nama_skpd }}</p>
                    @else
                        <p class="text-sm font-medium text-slate-400">-</p>
                    @endif
                </div>

                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Jenis SKPD</p>
                    @if ($pegawai->skpd?->jenis_skpd)
                        <span class="inline-block rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold uppercase text-slate-600">{{ $pegawai->skpd->jenis_skpd }}</span>
                    @else
                        <p class="text-sm font-medium text-slate-400">-</p>
                    @endif
                </div>

                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Lokasi</p>
                    <p class="text-sm font-medium text-slate-800">{{ $lokasiPegawai?->nama_lokasi ?? '-' }}</p>
                    <p class="text-[11px] text-slate-400">Turunan dari SKPD</p>
                </div>

                <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ruangan Kerja</p>
                    <p class="text-sm font-medium text-slate-800">{{ $pegawai->ruangan?->nama_ruangan ?? '-' }}</p>
                </div>
            </div>
        </x-card>

        {{-- Bagian Kedua: Informasi Akun Pengguna --}}
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-bold text-slate-900">Informasi Akun Pengguna</h3>
                <p class="text-xs text-slate-400">Akun login untuk autentikasi dan otorisasi aplikasi Invensbun.</p>
            </div>

            @if (! $user)
                <div class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100">
                            <svg class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div>
                            <span class="inline-block rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold uppercase text-slate-600">Belum Ada Akun</span>
                            <p class="mt-1 text-sm text-slate-600">Pegawai ini belum memiliki akun login dan belum dapat mengakses sistem Invensbun. Pembuatan akun dilakukan melalui menu daftar Manajemen Pegawai.</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Username</p>
                            <p class="font-mono text-sm font-bold text-slate-900">{{ $user->username }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Role Akun</p>
                            <p class="text-sm font-semibold text-indigo-700">{{ $user->role?->nama_role ?? '-' }}</p>
                            <p class="text-[10px] text-slate-400">Sesuai unit: {{ $pegawai->skpd?->nama_skpd ?? '-' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status Akun</p>
                            <p class="text-sm font-bold {{ $isAktif ? 'text-disbun-700' : 'text-red-600' }}">
                                {{ $isAktif ? 'Aktif' : 'Nonaktif' }}
                            </p>
                            <p class="text-[10px] text-slate-400">{{ $isAktif ? 'Dapat login ke sistem' : 'Akses login ditutup' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Akun Dibuat</p>
                            <p class="text-sm font-medium text-slate-800">
                                {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('d M Y, H:i') : '-' }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ===== TAB 2: RIWAYAT AKTIVITAS ===== --}}
    <div data-tab-panel id="tab-riwayat" class="mt-4 hidden space-y-4">
        <div class="space-y-4 rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Riwayat Aktivitas Pegawai &amp; Akun</h3>
                <p class="text-[11px] text-slate-500">
                    Jejak perubahan data pegawai, akun login, role, status, dan password. Informasi rahasia seperti nilai password tidak dicatat.
                </p>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse ($riwayatLogs as $log)
                    @php
                        $meta = $logMeta[$log->log_type] ?? $logMetaDefault;
                        $aktorNama = $log->actor?->pegawai?->nama_pegawai
                            ?? $log->nama_aktor
                            ?? $log->actor?->username
                            ?? 'Sistem';
                        $aktorRole = $log->actor?->role?->nama_role ?? '-';
                        $oldValues = $log->old_values ?? [];
                        $newValues = $log->new_values ?? [];
                        $fields = array_values(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))));
                    @endphp

                    <div class="py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-xl {{ $meta['icon'] }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">{!! $meta['svg'] !!}</svg>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ $meta['chip'] }}">
                                            {{ $meta['label'] }}
                                        </span>
                                        <span class="font-bold text-slate-800">{{ $aktorNama }}</span>
                                        <span class="text-[11px] text-slate-400">({{ $aktorRole }})</span>
                                    </div>
                                    @if ($log->description)
                                        <p class="mt-1 text-[11px] leading-relaxed text-slate-600">{{ $log->description }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <span class="block font-mono text-[11px] text-slate-600">
                                    {{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i') }} WIB
                                </span>
                                <span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</span>
                            </div>
                        </div>

                        @if (! empty($fields))
                            <details class="mt-2.5 ml-10">
                                <summary class="cursor-pointer list-none text-[11px] font-semibold text-disbun-700 transition hover:text-disbun-800">
                                    Lihat {{ count($fields) }} perubahan field
                                </summary>
                                <div class="mt-2 overflow-hidden rounded-xl border border-slate-100">
                                    <table class="min-w-full divide-y divide-slate-100 text-[11px]">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th scope="col" class="px-3 py-2 text-left font-semibold text-slate-500">Field</th>
                                                <th scope="col" class="px-3 py-2 text-left font-semibold text-slate-500">Sebelum</th>
                                                <th scope="col" class="px-3 py-2 text-left font-semibold text-slate-500">Sesudah</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            @foreach ($fields as $field)
                                                @php
                                                    $label = $fieldLabels[$field] ?? $field;
                                                    $before = $oldValues[$field] ?? null;
                                                    $after = $newValues[$field] ?? null;
                                                    $beforeLabel = is_bool($before) ? ($before ? 'Ya' : 'Tidak') : ($before === null || $before === '' ? '(kosong)' : $before);
                                                    $afterLabel = is_bool($after) ? ($after ? 'Ya' : 'Tidak') : ($after === null || $after === '' ? '(kosong)' : $after);
                                                @endphp
                                                <tr>
                                                    <td class="px-3 py-2 font-medium text-slate-700">{{ $label }}</td>
                                                    <td class="px-3 py-2 text-slate-500">{{ $beforeLabel }}</td>
                                                    <td class="px-3 py-2 font-medium text-slate-800">{{ $afterLabel }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endif
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada riwayat aktivitas untuk pegawai ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // ---------- Tab switching (Tepat 2 Tab) ----------
        const tabBtns = Array.from(document.querySelectorAll('[data-tab-target]'));
        const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));

        function activateTab(id) {
            // Alias kompatibilitas bila diakses via ?tab=tab-pegawai atau ?tab=tab-akun
            if (id === 'tab-pegawai' || id === 'tab-akun') {
                id = 'tab-pegawai-akun';
            }
            tabBtns.forEach((b) => {
                const on = b.dataset.tabTarget === id;
                b.classList.toggle('bg-disbun-700', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('shadow-xs', on);
                b.classList.toggle('text-slate-600', !on);
            });
            tabPanels.forEach((p) => p.classList.toggle('hidden', p.id !== id));
        }

        tabBtns.forEach((b) => b.addEventListener('click', () => activateTab(b.dataset.tabTarget)));

        // URL query parameter ?tab=tab-akun or #tab-akun support
        const urlParams = new URLSearchParams(window.location.search);
        let requestedTab = urlParams.get('tab') || window.location.hash.replace('#', '');
        if (requestedTab === 'tab-pegawai' || requestedTab === 'tab-akun') {
            requestedTab = 'tab-pegawai-akun';
        }
        if (requestedTab && document.getElementById(requestedTab)) {
            activateTab(requestedTab);
        }
    </script>
    @endpush
@endsection
