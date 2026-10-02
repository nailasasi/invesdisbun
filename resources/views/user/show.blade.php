@extends('layouts.app')

@section('title', 'Detail User')

@section('page-title', 'Detail User')

@section('content')
    @php
        $user = $pegawai->user;
        $isAktif = $user?->status_user === 'aktif';
        $isSelf = auth()->id() === $user?->id_user;
        $initial = mb_substr((string) ($pegawai->nama_pegawai ?: '?'), 0, 1);

        // Konfigurasi tampilan timeline per jenis aktivitas.
        $logMeta = [
            'user.created' => [
                'label' => 'Tambah User',
                'chip' => 'bg-disbun-100 text-disbun-800',
                'icon' => 'bg-disbun-50 text-disbun-700',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>',
            ],
            'user.updated' => [
                'label' => 'Edit User',
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
            'user.deleted' => [
                'label' => 'Hapus User',
                'chip' => 'bg-red-100 text-red-700',
                'icon' => 'bg-red-50 text-red-600',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>',
            ],
        ];
        $logMetaDefault = $logMeta['user.updated'];

        $oldSkpd = old('id_skpd', $pegawai->id_skpd);
        $oldRuangan = old('id_ruangan', $pegawai->id_ruangan);
        $lokasiPegawai = \App\Models\Lokasi::untukSkpd($pegawai->id_skpd);
    @endphp

    {{-- Navigasi Kembali --}}
    <div class="mb-4">
        <a href="{{ route('user.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-disbun-700">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke User Management
        </a>
    </div>

    {{-- Header & Aksi --}}
    <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-disbun-700 text-xl font-extrabold text-white shadow-sm">
                {{ mb_strtoupper($initial) }}
            </div>
            <div class="min-w-0">
                <h2 class="truncate text-2xl font-extrabold tracking-tight text-slate-900">{{ $pegawai->nama_pegawai }}</h2>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $user?->role?->nama_role ?? 'Tanpa Role' }}
                    </span>
                    @if ($user)
                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-0.5 text-[11px] font-bold {{ $isAktif ? 'bg-disbun-100 text-disbun-800' : 'bg-red-100 text-red-700' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $isAktif ? 'bg-disbun-600' : 'bg-red-500' }}"></span>
                            {{ $isAktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">Belum ada akun</span>
                    @endif
                    <span class="text-xs text-slate-400">NIP. {{ $pegawai->nip ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-button type="button" id="btn-edit" variant="primary" icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                Edit Pengguna
            </x-button>

            <x-button type="button" id="btn-role" variant="secondary" icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                Ubah Role
            </x-button>

            @if ($user && ! $isSelf)
                @if ($isAktif)
                    <x-button type="button" id="btn-status" variant="secondary" icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        Nonaktifkan Akun
                    </x-button>
                @else
                    <x-button type="button" id="btn-status" variant="secondary" icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z">
                        Aktifkan Akun
                    </x-button>
                @endif

                <x-button type="button" id="btn-hapus" variant="outline" icon="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                    Hapus
                </x-button>
            @endif
        </div>
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- Tab Pills --}}
    <div class="mb-4 overflow-x-auto">
        <div class="flex w-max min-w-full items-center gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-2xs">
            <button type="button" data-tab-target="tab-profil"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl bg-disbun-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition" role="tab">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Profil
            </button>
            <button type="button" data-tab-target="tab-riwayat"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-bold text-slate-600 transition" role="tab">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Riwayat
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $riwayatLogs->count() }}</span>
            </button>
        </div>
    </div>

    {{-- ===== TAB 1: PROFIL ===== --}}
    <div data-tab-panel id="tab-profil" class="mt-4 space-y-4">
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-bold text-slate-900">Informasi Pegawai</h3>
                <p class="text-xs text-slate-400">Data jabatan, struktur organisasi, dan akun login</p>
            </div>

            <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-6 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Jabatan --}}
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Jabatan</p>
                    <p class="text-sm font-medium text-slate-800">{{ $pegawai->jabatan ?? '-' }}</p>
                </div>

                {{-- SKPD --}}
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">SKPD</p>
                    @if ($pegawai->skpd)
                        <p class="text-sm font-medium text-slate-800">{{ $pegawai->skpd->nama_skpd }}</p>
                        <span class="inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500">{{ $pegawai->skpd->jenis_skpd ?? '-' }}</span>
                    @else
                        <p class="text-sm font-medium text-slate-400">-</p>
                    @endif
                </div>

                {{-- Lokasi --}}
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Lokasi</p>
                    <p class="text-sm font-medium text-slate-800">{{ $lokasiPegawai?->nama_lokasi ?? '-' }}</p>
                    <p class="text-[11px] text-slate-400">Terisi otomatis dari SKPD</p>
                </div>

                {{-- Ruangan --}}
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ruangan</p>
                    <p class="text-sm font-medium text-slate-800">{{ $pegawai->ruangan?->nama_ruangan ?? '-' }}</p>
                    @if ($pegawai->ruangan?->lantai)
                        <p class="text-[11px] text-slate-400">Lantai {{ $pegawai->ruangan->lantai }}</p>
                    @endif
                </div>

                {{-- Username --}}
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Username</p>
                    <p class="font-mono text-sm font-medium text-slate-800">{{ $user?->username ?? '-' }}</p>
                </div>

                {{-- Role --}}
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Role / Hak Akses</p>
                    <p class="text-sm font-medium text-slate-800">{{ $user?->role?->nama_role ?? '-' }}</p>
                </div>
            </div>
        </x-card>

        {{-- Info Akun --}}
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-bold text-slate-900">Informasi Akun</h3>
                <p class="text-xs text-slate-400">Status login dan jejak waktu pembuatan akun</p>
            </div>
            <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status Akun</p>
                    <p class="text-sm font-medium {{ $isAktif ? 'text-disbun-700' : 'text-red-600' }}">
                        {{ $user ? ($isAktif ? 'Aktif' : 'Nonaktif') : 'Belum ada akun' }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Akun Dibuat</p>
                    <p class="text-sm font-medium text-slate-800">
                        {{ $user?->created_at ? \Carbon\Carbon::parse($user->created_at)->format('d M Y, H:i') : '-' }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Terakhir Diperbarui</p>
                    <p class="text-sm font-medium text-slate-800">
                        {{ $user?->updated_at ? \Carbon\Carbon::parse($user->updated_at)->format('d M Y, H:i') : '-' }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Aset Dipegang</p>
                    <p class="text-sm font-medium text-slate-800">{{ $jumlahAsetAktif }} aset aktif</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ===== TAB 2: RIWAYAT ===== --}}
    <div data-tab-panel id="tab-riwayat" class="mt-4 hidden">
        <div class="space-y-4 rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Riwayat Aktivitas User</h3>
                <p class="text-[11px] text-slate-500">
                    Jejak perubahan data pegawai, role, dan status akun. Riwayat mutasi aset tetap tercatat pada halaman detail aset masing-masing.
                </p>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse ($riwayatLogs as $log)
                    @php
                        $meta = $logMeta[$log->log_type] ?? $logMetaDefault;
                        $aktor = $log->actor?->pegawai?->nama_pegawai
                            ?? $log->nama_aktor
                            ?? $log->actor?->username
                            ?? 'Pengguna Sistem';
                        $aktorRole = $log->actor?->role?->nama_role ?? 'Admin Aset';
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
                                        <span class="font-bold text-slate-800">{{ $aktor }}</span>
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
                        Belum ada riwayat aktivitas untuk user ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ===== Modal Edit Pengguna ===== --}}
    <div id="edit-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-edit-close></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Edit Pengguna</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Role diubah lewat tombol Ubah Role, bukan di sini.</p>
                </div>
                <button type="button" data-edit-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('user.update', $pegawai->id_pegawai) }}" autocomplete="off">
                @csrf
                <input type="hidden" name="_form" value="edit">
                <div class="max-h-[90vh] space-y-5 overflow-y-auto px-6 py-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="e-nip" class="block text-sm font-medium text-slate-700">NIP <span class="text-red-500">*</span></label>
                            <input type="text" id="e-nip" name="nip" required value="{{ old('nip', $pegawai->nip) }}"
                                   class="block w-full rounded-xl border px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('nip') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                            @error('nip')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="e-nama" class="block text-sm font-medium text-slate-700">Nama Pegawai <span class="text-red-500">*</span></label>
                            <input type="text" id="e-nama" name="nama_pegawai" required value="{{ old('nama_pegawai', $pegawai->nama_pegawai) }}"
                                   class="block w-full rounded-xl border px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('nama_pegawai') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                            @error('nama_pegawai')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="e-jabatan" class="block text-sm font-medium text-slate-700">Jabatan</label>
                            <input type="text" id="e-jabatan" name="jabatan" value="{{ old('jabatan', $pegawai->jabatan) }}"
                                   class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            @error('jabatan')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="e-skpd" class="block text-sm font-medium text-slate-700">SKPD</label>
                            <select id="e-skpd" name="id_skpd"
                                    class="block w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('id_skpd') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                                <option value="">-- Pilih SKPD --</option>
                                @foreach ($skpdList as $skpd)
                                    @php
                                        $lokasiSkpd = $skpd->isUpt() ? $skpd->lokasi : $lokasiDinas;
                                    @endphp
                                    <option
                                        value="{{ $skpd->id_skpd }}"
                                        data-lokasi-id="{{ $lokasiSkpd?->id_lokasi ?? '' }}"
                                        data-lokasi-nama="{{ $lokasiSkpd?->nama_lokasi ?? '' }}"
                                        @selected((string) $oldSkpd === (string) $skpd->id_skpd)
                                    >{{ $skpd->nama_skpd }} ({{ $skpd->jenis_skpd }})</option>
                                @endforeach
                            </select>
                            @error('id_skpd')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="e-lokasi" class="block text-sm font-medium text-slate-700">Lokasi</label>
                            <input
                                type="text"
                                id="e-lokasi"
                                readonly
                                value="{{ $lokasiPegawai?->nama_lokasi ?? '' }}"
                                class="block w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-600 shadow-sm focus:outline-none transition"
                            >
                            <p class="text-xs text-slate-400">Terisi otomatis dari SKPD: Sekretariat &amp; Bidang → Kantor Dinas; UPT → kantor UPT-nya.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="e-ruangan" class="block text-sm font-medium text-slate-700">Ruangan</label>
                            <select id="e-ruangan" name="id_ruangan"
                                    class="block w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('id_ruangan') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                                <option value="">-- Pilih Ruangan --</option>
                                @foreach ($ruanganList as $ruangan)
                                    <option
                                        value="{{ $ruangan->id_ruangan }}"
                                        data-lokasi="{{ $ruangan->id_lokasi ?? '' }}"
                                        @if ($ruangan->id_skpd === null) data-shared="1" @endif
                                        @selected((string) $oldRuangan === (string) $ruangan->id_ruangan)
                                    >{{ $ruangan->nama_ruangan }}</option>
                                @endforeach
                            </select>
                            @error('id_ruangan')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                            <p class="text-xs text-slate-400">Ruang bersama tersedia untuk semua SKPD.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="e-username" class="block text-sm font-medium text-slate-700">Username</label>
                            <input type="text" id="e-username" name="username" maxlength="255" value="{{ old('username', $user?->username) }}"
                                   class="block w-full rounded-xl border px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('username') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                            @error('username')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="e-password" class="block text-sm font-medium text-slate-700">
                                Password
                                <span class="text-xs font-normal text-slate-400">(kosongkan = tidak diubah)</span>
                            </label>
                            <input type="password" id="e-password" name="password" autocomplete="new-password"
                                   class="block w-full rounded-xl border px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('password') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}"
                                   placeholder="Minimal 8 karakter">
                            @error('password')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-edit-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Modal Ubah Role ===== --}}
    <div id="role-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-role-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Ubah Role</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Hak akses untuk {{ $pegawai->nama_pegawai }}.</p>
                </div>
                <button type="button" data-role-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('user.role.update', $pegawai->id_pegawai) }}">
                @csrf
                <input type="hidden" name="_form" value="role">
                <div class="px-6 py-5">
                    <div class="space-y-1.5">
                        <label for="r-role" class="block text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                        <select id="r-role" name="id_role" required
                                class="block w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('id_role') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                            @foreach ($roleList as $role)
                                <option value="{{ $role->id_role }}" @selected((string) old('id_role', $user?->id_role) === (string) $role->id_role)>{{ $role->nama_role }}</option>
                            @endforeach
                        </select>
                        @error('id_role')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-500">
                        Mengubah role tidak memindahkan SKPD maupun aset yang dipegang pengguna.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-role-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Modal Ubah Status Akun ===== --}}
    <div id="status-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-status-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">
                        {{ $isAktif ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                    </h3>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $pegawai->nama_pegawai }} &middot; {{ $user?->username }}</p>
                </div>
                <button type="button" data-status-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('user.status.update', $pegawai->id_pegawai) }}">
                @csrf
                <input type="hidden" name="status_user" value="{{ $isAktif ? 'nonaktif' : 'aktif' }}">

                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $isAktif ? 'bg-amber-100' : 'bg-disbun-100' }}">
                            @if ($isAktif)
                                <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            @else
                                <svg class="h-5 w-5 text-disbun-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <p class="text-sm leading-relaxed text-slate-600">
                            @if ($isAktif)
                                Akun akan dinonaktifkan sehingga tidak dapat login lagi. Data pegawai, riwayat aktivitas, dan aset yang dipegang tetap tersimpan. Status bisa diaktifkan kembali kapan saja.
                            @else
                                Akun akan diaktifkan kembali dan dapat login seperti semula.
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-status-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-xl px-4 py-2 text-sm font-medium text-white shadow-sm {{ $isAktif ? 'bg-amber-600 hover:bg-amber-700' : 'bg-disbun-700 hover:bg-disbun-800' }}">
                        {{ $isAktif ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Modal Hapus Permanen ===== --}}
    <x-modal-confirm
        id="hapus-modal"
        :action="route('user.destroy', $pegawai->id_pegawai)"
        method="DELETE"
        title="Hapus Permanen User"
        :message="'Menghapus '.$pegawai->nama_pegawai.' akan menghilangkan akun login dan data pegawai secara permanen. Aset, dokumen SPPBI, dan usulan RKBMD ikut terhapus. Bila hanya ingin menutup akses login, gunakan tombol Nonaktifkan Akun.'"
        button-text="Hapus Permanen"
    />

    @push('scripts')
    <script>
        // ---------- Tab switching ----------
        const tabBtns = Array.from(document.querySelectorAll('[data-tab-target]'));
        const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));

        function activateTab(id) {
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

        // ---------- Modal helpers ----------
        function setupModal(modalId, triggerId, closeAttr) {
            const modal = document.getElementById(modalId);
            const trigger = document.getElementById(triggerId);

            const close = () => {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            };

            if (trigger && modal) {
                trigger.addEventListener('click', () => {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                });
            }

            if (modal) {
                modal.querySelectorAll('[' + closeAttr + ']').forEach((el) => el.addEventListener('click', close));
            }

            return { modal: modal, close: close };
        }

        const editModal = setupModal('edit-modal', 'btn-edit', 'data-edit-close');
        const roleModal = setupModal('role-modal', 'btn-role', 'data-role-close');
        const statusModal = setupModal('status-modal', 'btn-status', 'data-status-close');

        // Modal konfirmasi hapus memakai komponen x-modal-confirm.
        const btnHapus = document.getElementById('btn-hapus');
        const hapusModal = document.getElementById('hapus-modal');
        if (btnHapus && hapusModal) {
            btnHapus.addEventListener('click', () => {
                hapusModal.classList.remove('hidden');
                hapusModal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            });
            hapusModal.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', () => {
                hapusModal.classList.add('hidden');
                hapusModal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }));
        }

        // ---------- Cascade SKPD -> Lokasi -> Ruangan ----------
        const eSkpd = document.getElementById('e-skpd');
        const eLokasi = document.getElementById('e-lokasi');
        const eRuangan = document.getElementById('e-ruangan');

        let stickyRuangan = @json($oldRuangan);

        function applyEditRuanganFilter(selectedRuangan) {
            const lokasiId = eSkpd.options[eSkpd.selectedIndex]?.dataset.lokasiId || '';
            let hasOption = false;

            eRuangan.querySelectorAll('option').forEach((opt) => {
                if (!opt.value) { opt.style.display = ''; return; }
                // Ruang bersama (tanpa skpd) selalu boleh dipilih.
                const match = opt.dataset.shared === '1' || (lokasiId && opt.dataset.lokasi === lokasiId);
                opt.style.display = match ? '' : 'none';
                if (match) hasOption = true;
            });

            const stick = selectedRuangan || '';
            eRuangan.value = stick && hasOption ? stick : '';
            eRuangan.querySelector('option[value=""]').textContent = hasOption
                ? '-- Pilih Ruangan --'
                : (lokasiId ? '-- Tidak ada ruangan untuk lokasi ini --' : '-- Pilih SKPD dulu --');
        }

        function applyEditLokasiDisplay() {
            const opt = eSkpd.options[eSkpd.selectedIndex];
            if (eLokasi) {
                eLokasi.value = opt && opt.value ? (opt.dataset.lokasiNama || '-') : '';
            }
            stickyRuangan = '';
            applyEditRuanganFilter();
        }

        if (eSkpd && eRuangan) {
            applyEditRuanganFilter(stickyRuangan);

            eSkpd.addEventListener('change', applyEditLokasiDisplay);
        }

        // Buka kembali modal yang gagal tervalidasi agar pesan error langsung terlihat.
        @if ($errors->hasAny(['nip', 'nama_pegawai', 'jabatan', 'id_skpd', 'id_ruangan', 'username', 'password']) && old('_form') !== 'role')
            editModal.modal?.classList.remove('hidden');
            editModal.modal?.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        @endif

        @if ($errors->has('id_role'))
            roleModal.modal?.classList.remove('hidden');
            roleModal.modal?.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        @endif
    </script>
    @endpush
@endsection