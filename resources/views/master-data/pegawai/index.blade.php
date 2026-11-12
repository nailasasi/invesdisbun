@extends('layouts.app')

@section('title', 'Manajemen Pegawai')

@section('page-title', 'Manajemen Pegawai')

@section('content')
    <x-alert type="success" />
    <x-alert type="error" />

    {{-- Ringkasan Informatif Hasil Import Excel --}}
    @if (session('import_summary'))
        @php $sum = session('import_summary'); @endphp
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h4 class="text-sm font-bold text-slate-800">Hasil Import Data Pegawai</h4>
                    <p class="text-xs text-slate-400">Ringkasan pemrosesan berkas Excel / CSV</p>
                </div>
                <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" onclick="this.closest('.mb-6').remove()" aria-label="Tutup ringkasan">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 text-center">
                    <p class="text-xs font-semibold text-emerald-700">Data Baru</p>
                    <p class="mt-1 text-xl font-extrabold text-emerald-800">{{ $sum['created'] }}</p>
                </div>
                <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-3 text-center">
                    <p class="text-xs font-semibold text-blue-700">Data Diperbarui</p>
                    <p class="mt-1 text-xl font-extrabold text-blue-800">{{ $sum['updated'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-center">
                    <p class="text-xs font-semibold text-slate-600">Baris Dilewati</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-700">{{ $sum['skipped'] }}</p>
                </div>
                <div class="rounded-xl border border-red-100 bg-red-50/60 p-3 text-center">
                    <p class="text-xs font-semibold text-red-700">Baris Gagal</p>
                    <p class="mt-1 text-xl font-extrabold text-red-800">{{ $sum['failed'] }}</p>
                </div>
            </div>
            @if (!empty($sum['errors']))
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-xs font-bold text-red-800">Daftar Baris yang Gagal Diproses ({{ count($sum['errors']) }} baris):</p>
                    <ul class="mt-2 max-h-48 list-disc space-y-1 overflow-y-auto pl-5 text-xs text-red-700">
                        @foreach ($sum['errors'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('master-data.pegawai.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input
                        type="text"
                        name="search"
                        id="search-pegawai"
                        value="{{ request('search') }}"
                        placeholder="Cari NIP, Nama, atau Jabatan..."
                        autocomplete="off"
                        class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition"
                    >
                </div>

                <select name="skpd" id="filter-skpd"
                        class="w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition sm:w-auto sm:max-w-[16rem]">
                    <option value="">Semua SKPD</option>
                    @foreach ($skpdList as $skpd)
                        <option value="{{ $skpd->id_skpd }}" @selected((string) request('skpd') === (string) $skpd->id_skpd)>
                            {{ $skpd->nama_skpd }}
                        </option>
                    @endforeach
                </select>

                <select name="status_akun" id="filter-status-akun"
                        class="w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition sm:w-auto">
                    <option value="">Semua Status Akun</option>
                    <option value="belum" @selected(request('status_akun') === 'belum')>Belum Ada Akun</option>
                    <option value="aktif" @selected(request('status_akun') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status_akun') === 'nonaktif')>Nonaktif</option>
                </select>

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800 sm:w-auto">
                    Cari
                </button>
            </form>

            <x-slot name="actions">
                <x-button type="button" id="btn-import-pegawai" variant="secondary" class="w-full sm:w-auto" icon="M12 16V4m0 0L8 8m4-4l4 4m-9 8H5a2 2 0 00-2 2v2a2 2 0 002 2h14a2 2 0 002-2v-2a2 2 0 00-2-2h-2">
                    Import Excel
                </x-button>
                <x-button type="button" id="btn-tambah-pegawai" icon="M12 4v16m8-8H4">
                    Tambah Pegawai
                </x-button>
            </x-slot>
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIP</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKPD</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ruangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status Akun</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($pegawaiList as $i => $pegawai)
                        @php
                            $statusUser = $pegawai->user?->status_user;
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $pegawaiList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $pegawai->nip ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->nama_pegawai }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->jabatan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                <div>{{ $pegawai->skpd?->nama_skpd ?? '-' }}</div>
                                @if ($pegawai->skpd?->jenis_skpd)
                                    <span class="inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500">{{ $pegawai->skpd->jenis_skpd }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->ruangan?->nama_ruangan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($pegawai->user?->role)
                                    <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                        {{ $pegawai->user->role->nama_role }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($statusUser === null)
                                    <span class="inline-block rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">Belum Ada Akun</span>
                                @elseif ($statusUser === 'aktif')
                                    <span class="inline-block rounded-md bg-disbun-100 px-2 py-0.5 text-[10px] font-bold uppercase text-disbun-800">Aktif</span>
                                @else
                                    <span class="inline-block rounded-md bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase text-red-700">Nonaktif</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                @php
                                    $user = $pegawai->user;
                                    $isAktif = $user?->status_user === 'aktif';
                                    $isSelf = auth()->id() && $user && auth()->id() === $user->id_user;
                                @endphp
                                <div class="inline-flex items-center gap-1 rounded-xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                    {{-- 1. Detail (Selalu tampil) --}}
                                    <a href="{{ route('master-data.pegawai.show', $pegawai) }}"
                                       title="Detail Pegawai"
                                       aria-label="Lihat detail pegawai {{ $pegawai->nama_pegawai }}"
                                       class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    {{-- 2. Edit Pegawai (Modal) --}}
                                    <button type="button"
                                            data-edit-pegawai
                                            data-pegawai-id="{{ $pegawai->id_pegawai }}"
                                            data-nip="{{ $pegawai->nip ?? '' }}"
                                            data-nama="{{ $pegawai->nama_pegawai }}"
                                            data-jabatan="{{ $pegawai->jabatan ?? '' }}"
                                            data-skpd-id="{{ $pegawai->id_skpd }}"
                                            data-ruangan-id="{{ $pegawai->id_ruangan ?? '' }}"
                                            data-action="{{ route('master-data.pegawai.update', $pegawai) }}"
                                            title="Edit Pegawai"
                                            aria-label="Edit pegawai {{ $pegawai->nama_pegawai }}"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-disbun-700 transition hover:bg-disbun-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-500">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    @if ($user)
                                        {{-- 3. Ubah Role (Hanya bila sudah punya akun) --}}
                                        @if ($isSelf)
                                            <button type="button" disabled
                                                    title="Anda tidak dapat mengubah role akun sendiri"
                                                    aria-label="Ubah role akun (akun sendiri tidak dapat diubah)"
                                                    class="flex h-7 w-7 cursor-not-allowed items-center justify-center rounded-lg bg-slate-100 text-slate-300">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </button>
                                        @else
                                            <button type="button"
                                                    data-ubah-role
                                                    data-pegawai-id="{{ $pegawai->id_pegawai }}"
                                                    data-nama="{{ $pegawai->nama_pegawai }}"
                                                    data-nip="{{ $pegawai->nip ?? '-' }}"
                                                    data-skpd="{{ $pegawai->skpd?->nama_skpd ?? '-' }}"
                                                    data-jenis-skpd="{{ $pegawai->skpd?->jenis_skpd ?? '' }}"
                                                    data-current-role-id="{{ $user->id_role }}"
                                                    data-action="{{ route('user.role.update', $pegawai) }}"
                                                    title="Ubah Role"
                                                    aria-label="Ubah role akun {{ $pegawai->nama_pegawai }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </button>
                                        @endif

                                        {{-- 4. Nonaktifkan / Aktifkan Akun (Saling eksklusif) --}}
                                        @if ($isAktif)
                                            {{-- Akun Aktif -> Ikon Nonaktifkan Akun --}}
                                            @if ($isSelf)
                                                <button type="button" disabled
                                                        title="Anda tidak dapat menonaktifkan akun sendiri"
                                                        aria-label="Nonaktifkan akun (akun sendiri tidak dapat dinonaktifkan)"
                                                        class="flex h-7 w-7 cursor-not-allowed items-center justify-center rounded-lg bg-slate-100 text-slate-300">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"/>
                                                    </svg>
                                                </button>
                                            @else
                                                <button type="button"
                                                        data-ubah-status
                                                        data-pegawai-id="{{ $pegawai->id_pegawai }}"
                                                        data-nama="{{ $pegawai->nama_pegawai }}"
                                                        data-username="{{ $user->username }}"
                                                        data-target-status="nonaktif"
                                                        data-action="{{ route('user.status.update', $pegawai) }}"
                                                        title="Nonaktifkan Akun"
                                                        aria-label="Nonaktifkan akun {{ $pegawai->nama_pegawai }}"
                                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        @else
                                            {{-- Akun Nonaktif -> Ikon Aktifkan Akun --}}
                                            @if ($isSelf)
                                                <button type="button" disabled
                                                        title="Anda tidak dapat mengaktifkan akun sendiri"
                                                        aria-label="Aktifkan akun"
                                                        class="flex h-7 w-7 cursor-not-allowed items-center justify-center rounded-lg bg-slate-100 text-slate-300">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7zM17 11l2 2 4-4"/>
                                                    </svg>
                                                </button>
                                            @else
                                                <button type="button"
                                                        data-ubah-status
                                                        data-pegawai-id="{{ $pegawai->id_pegawai }}"
                                                        data-nama="{{ $pegawai->nama_pegawai }}"
                                                        data-username="{{ $user->username }}"
                                                        data-target-status="aktif"
                                                        data-action="{{ route('user.status.update', $pegawai) }}"
                                                        title="Aktifkan Akun"
                                                        aria-label="Aktifkan akun {{ $pegawai->nama_pegawai }}"
                                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7zM17 11l2 2 4-4"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        @endif
                                    @else
                                        {{-- 3. Buat Akun (Hanya bila belum punya akun) --}}
                                        <button type="button"
                                                data-buat-akun
                                                data-pegawai-id="{{ $pegawai->id_pegawai }}"
                                                data-nama="{{ $pegawai->nama_pegawai }}"
                                                data-nip="{{ $pegawai->nip ?? '' }}"
                                                data-jabatan="{{ $pegawai->jabatan ?? '-' }}"
                                                data-skpd="{{ $pegawai->skpd?->nama_skpd ?? '-' }}"
                                                data-jenis-skpd="{{ $pegawai->skpd?->jenis_skpd ?? '' }}"
                                                data-action="{{ route('user.akun.store', $pegawai) }}"
                                                title="Buat Akun"
                                                aria-label="Buat akun pengguna untuk {{ $pegawai->nama_pegawai }}"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-sm text-slate-400">Tidak ada data pegawai yang cocok dengan pencarian/filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $pegawaiList->links() }}
        </div>
    </x-card>

    @push('modals')
    {{-- Modal Import Data Pegawai --}}
    <div id="import-pegawai-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-import-pegawai-close></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Import Data Pegawai dari Excel</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Unggah file .xlsx / .csv untuk menambah banyak pegawai sekaligus.</p>
                </div>
                <button type="button" data-import-pegawai-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('master-data.pegawai.import') }}" enctype="multipart/form-data" class="px-6 py-6">
                @csrf
                <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                    <div>
                        <span class="block text-sm font-semibold text-slate-700">Belum punya format file?</span>
                        <span class="text-xs text-slate-500">Unduh template standar import data pegawai</span>
                    </div>
                    <a href="{{ route('master-data.pegawai.template') }}"
                       class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-disbun-100 bg-white px-3 py-1.5 text-xs font-semibold text-disbun-800 shadow-sm transition hover:bg-disbun-50">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>Unduh Template</span>
                    </a>
                </div>
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">File Excel / CSV <span class="text-red-500">*</span></label>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="text-xs text-slate-400">Kolom: NIP, Nama Pegawai, Jabatan, Nama SKPD, Nama Ruangan. Identitas unik = NIP (wajib). SKPD &amp; Ruangan dicocokkan berdasarkan nama. Maks 5MB.</p>
                        @error('file')
                            <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="import-pegawai-mode" class="block text-sm font-medium text-slate-700">Bila NIP sudah terdaftar</label>
                        <select id="import-pegawai-mode" name="mode" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="skip" @selected(old('mode', 'skip') === 'skip')>Lewati (tidak mengubah data lama)</option>
                            <option value="update" @selected(old('mode') === 'update')>Perbarui data yang sudah ada</option>
                        </select>
                        @error('mode')
                            <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-end gap-2">
                    <button type="button" data-import-pegawai-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Import</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Ubah Role --}}
    <div id="modal-ubah-role" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-role-modal-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Ubah Role Pengguna</h3>
                    <p id="modal-role-pegawai-info" class="mt-0.5 text-sm text-slate-500"></p>
                </div>
                <button type="button" data-role-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="form-ubah-role" method="POST" action="">
                @csrf
                <div class="px-6 py-5">
                    <div class="space-y-1.5">
                        <label for="select-role" class="block text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                        <select id="select-role" name="id_role" required
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-disbun-500 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition">
                            @foreach ($roleList as $role)
                                <option value="{{ $role->id_role }}" data-role-nama="{{ $role->nama_role }}">{{ $role->nama_role }}</option>
                            @endforeach
                        </select>
                        <p id="modal-role-hint" class="text-[11px] text-slate-400">Pilihan role disesuaikan dengan penempatan SKPD pegawai.</p>
                        <p id="modal-role-error" class="hidden text-xs font-medium text-red-600"></p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-role-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit-ubah-role" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Simpan Role</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Ubah Status Akun (Aktifkan / Nonaktifkan) --}}
    <div id="modal-ubah-status" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-status-modal-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 id="modal-status-title" class="text-lg font-semibold text-slate-900">Ubah Status Akun</h3>
                    <p id="modal-status-pegawai-info" class="mt-0.5 text-sm text-slate-500"></p>
                </div>
                <button type="button" data-status-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="form-ubah-status" method="POST" action="">
                @csrf
                <input type="hidden" id="status-target-input" name="status_user" value="">

                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div id="modal-status-icon-wrapper" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <svg id="modal-status-icon-amber" class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <svg id="modal-status-icon-emerald" class="hidden h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p id="modal-status-description" class="text-sm leading-relaxed text-slate-600"></p>
                    </div>
                    <p id="modal-status-error" class="mt-3 hidden text-xs font-medium text-red-600"></p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-status-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit-ubah-status" class="rounded-xl px-5 py-2 text-sm font-medium text-white shadow-sm transition">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Tambah Pegawai --}}
    <div id="modal-tambah-pegawai" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-tambah-modal-close></div>
        <div class="relative w-full max-w-xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-5 py-3.5">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Tambah Pegawai</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Tambahkan data pegawai baru ke dalam sistem.</p>
                </div>
                <button type="button" data-tambah-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="form-tambah-pegawai" method="POST" action="{{ route('master-data.pegawai.store') }}" autocomplete="off">
                @csrf
                <div class="max-h-[72vh] space-y-3.5 overflow-y-auto px-5 py-4">
                    @include('master-data.pegawai.partials.form', [
                        'mode' => 'create',
                        'prefix' => 'tambah_',
                        'pegawai' => null,
                        'skpdList' => $skpdList,
                        'ruanganList' => $ruanganList,
                        'lokasiDinas' => $lokasiDinas,
                    ])
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3.5">
                    <button type="button" data-tambah-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit-tambah-pegawai" class="rounded-xl bg-disbun-700 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-disbun-800 transition">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Edit Pegawai --}}
    <div id="modal-edit-pegawai" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-edit-modal-close></div>
        <div class="relative w-full max-w-xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-5 py-3.5">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Edit Pegawai</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Perbarui informasi identitas dan penempatan pegawai.</p>
                </div>
                <button type="button" data-edit-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="form-edit-pegawai" method="POST" action="" autocomplete="off">
                @csrf
                <div class="max-h-[72vh] space-y-3.5 overflow-y-auto px-5 py-4">
                    @include('master-data.pegawai.partials.form', [
                        'mode' => 'edit',
                        'prefix' => 'edit_',
                        'pegawai' => null,
                        'skpdList' => $skpdList,
                        'ruanganList' => $ruanganList,
                        'lokasiDinas' => $lokasiDinas,
                    ])
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3.5">
                    <button type="button" data-edit-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit-edit-pegawai" class="rounded-xl bg-disbun-700 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-disbun-800 transition">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Buat Akun --}}
    <div id="modal-buat-akun" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-buat-akun-close></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Buat Akun Pengguna</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Buat kredensial login untuk pegawai terpilih.</p>
                </div>
                <button type="button" data-buat-akun-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="form-buat-akun" method="POST" action="" autocomplete="off">
                @csrf
                <div class="max-h-[75vh] space-y-4 overflow-y-auto px-6 py-5">
                    <div id="buat-akun-general-error" class="hidden rounded-xl border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-700"></div>

                    {{-- Info Card Pegawai --}}
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pegawai</p>
                        <p id="ba-card-nama" class="mt-1 text-sm font-bold text-slate-900"></p>
                        <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-600 sm:grid-cols-3">
                            <div><span class="text-slate-400">NIP:</span> <span id="ba-card-nip" class="font-mono"></span></div>
                            <div><span class="text-slate-400">Jabatan:</span> <span id="ba-card-jabatan"></span></div>
                            <div><span class="text-slate-400">SKPD:</span> <span id="ba-card-skpd"></span></div>
                        </dl>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label for="ba-role" class="block text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                            <select id="ba-role" name="id_role" required
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-disbun-500 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition">
                                @foreach ($roleList as $role)
                                    <option value="{{ $role->id_role }}" data-role-nama="{{ $role->nama_role }}">{{ $role->nama_role }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_role"></p>
                            <p class="text-[11px] text-slate-400">Pilihan role otomatis disesuaikan dengan jenis SKPD pegawai.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="ba-username" class="block text-sm font-medium text-slate-700">Username <span class="text-red-500">*</span></label>
                            <input type="text" id="ba-username" name="username" maxlength="255"
                                   class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-disbun-500 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="username"></p>
                            <p class="text-[11px] text-slate-400">Default: NIP pegawai.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="ba-password" class="block text-sm font-medium text-slate-700">
                                Password
                                <span id="ba-password-required-mark" class="hidden text-xs font-semibold text-amber-600">*</span>
                            </label>
                            <input type="password" id="ba-password" name="password" autocomplete="new-password"
                                   class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-disbun-500 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition">
                            <p id="ba-password-hint" class="text-[11px] text-slate-400"></p>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="password"></p>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nip"></p>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="pegawai"></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-buat-akun-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit-buat-akun" class="rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-disbun-800 transition">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        // Pencarian & filter diterapkan hanya saat tombol Cari ditekan
        // (atau Enter pada kolom pencarian) melalui submit form GET.

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value || '';

        // ---- Modal Import Data Pegawai ----
        const importPegawaiModal = document.getElementById('import-pegawai-modal');
        const btnImportPegawai = document.getElementById('btn-import-pegawai');
        if (btnImportPegawai && importPegawaiModal) {
            btnImportPegawai.addEventListener('click', () => {
                importPegawaiModal.classList.remove('hidden');
                importPegawaiModal.classList.add('flex');
            });
        }
        if (importPegawaiModal) {
            importPegawaiModal.querySelectorAll('[data-import-pegawai-close]').forEach(el => el.addEventListener('click', () => {
                importPegawaiModal.classList.add('hidden');
                importPegawaiModal.classList.remove('flex');
            }));
        }

        // ---- Modal Ubah Role ----
        const roleModal = document.getElementById('modal-ubah-role');
        const roleForm = document.getElementById('form-ubah-role');
        const roleInfo = document.getElementById('modal-role-pegawai-info');
        const roleSelect = document.getElementById('select-role');
        const roleHint = document.getElementById('modal-role-hint');
        const roleError = document.getElementById('modal-role-error');

        function openRoleModal(btn) {
            const nama = btn.dataset.nama || '';
            const skpd = btn.dataset.skpd || '-';
            const jenisSkpd = btn.dataset.jenisSkpd || '';
            const currentRoleId = btn.dataset.currentRoleId || '';
            const action = btn.dataset.action || '';

            roleForm.action = action;
            roleInfo.textContent = `${nama} (${skpd})`;
            roleHint.textContent = `Pilihan role disesuaikan dengan penempatan SKPD: ${skpd} (${jenisSkpd || '-'}).`;
            if (roleError) {
                roleError.textContent = '';
                roleError.classList.add('hidden');
            }

            // Filter opsi role sesuai jenis SKPD pegawai
            Array.from(roleSelect.options).forEach(opt => {
                const roleNama = opt.dataset.roleNama;
                let allowed = false;
                if (roleNama === 'Pegawai') {
                    allowed = true;
                } else if (roleNama === 'Admin Aset' && jenisSkpd === 'Sekretariat') {
                    allowed = true;
                } else if (roleNama === 'Admin Bidang' && jenisSkpd === 'Bidang') {
                    allowed = true;
                } else if (roleNama === 'Admin UPT' && jenisSkpd === 'UPT') {
                    allowed = true;
                }

                opt.hidden = !allowed;
                opt.disabled = !allowed;
            });

            roleSelect.value = currentRoleId;
            if (roleSelect.selectedIndex === -1 || roleSelect.selectedOptions[0]?.disabled) {
                const firstAllowed = Array.from(roleSelect.options).find(o => !o.disabled);
                if (firstAllowed) firstAllowed.selected = true;
            }

            roleModal.classList.remove('hidden');
            roleModal.classList.add('flex');
        }

        function closeRoleModal() {
            roleModal.classList.add('hidden');
            roleModal.classList.remove('flex');
        }

        document.querySelectorAll('[data-ubah-role]').forEach(btn => {
            btn.addEventListener('click', () => openRoleModal(btn));
        });
        document.querySelectorAll('[data-role-modal-close]').forEach(el => {
            el.addEventListener('click', closeRoleModal);
        });

        if (roleForm) {
            roleForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btnSubmit = document.getElementById('btn-submit-ubah-role');
                btnSubmit.disabled = true;
                btnSubmit.textContent = 'Menyimpan...';

                try {
                    const formData = new FormData(roleForm);
                    const res = await fetch(roleForm.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });
                    const data = await res.json();
                    if (!res.ok || data.success === false) {
                        const errMsg = data.errors ? Object.values(data.errors)[0]?.[0] : (data.message || 'Gagal mengubah role.');
                        if (roleError) {
                            roleError.textContent = errMsg;
                            roleError.classList.remove('hidden');
                        } else {
                            alert(errMsg);
                        }
                        btnSubmit.disabled = false;
                        btnSubmit.textContent = 'Simpan Role';
                        return;
                    }
                    window.location.reload();
                } catch (err) {
                    alert('Terjadi kesalahan saat menyimpan role.');
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = 'Simpan Role';
                }
            });
        }

        // ---- Modal Ubah Status Akun ----
        const statusModal = document.getElementById('modal-ubah-status');
        const statusForm = document.getElementById('form-ubah-status');
        const statusTitle = document.getElementById('modal-status-title');
        const statusInfo = document.getElementById('modal-status-pegawai-info');
        const statusInput = document.getElementById('status-target-input');
        const statusDesc = document.getElementById('modal-status-description');
        const statusIconWrapper = document.getElementById('modal-status-icon-wrapper');
        const statusIconAmber = document.getElementById('modal-status-icon-amber');
        const statusIconEmerald = document.getElementById('modal-status-icon-emerald');
        const btnSubmitStatus = document.getElementById('btn-submit-ubah-status');
        const statusError = document.getElementById('modal-status-error');

        function openStatusModal(btn) {
            const nama = btn.dataset.nama || '';
            const username = btn.dataset.username || '';
            const targetStatus = btn.dataset.targetStatus || 'aktif';
            const action = btn.dataset.action || '';

            statusForm.action = action;
            statusInput.value = targetStatus;
            statusInfo.textContent = `${nama} · ${username}`;
            if (statusError) {
                statusError.textContent = '';
                statusError.classList.add('hidden');
            }

            if (targetStatus === 'nonaktif') {
                statusTitle.textContent = 'Nonaktifkan Akun';
                statusDesc.textContent = 'Akun akan dinonaktifkan sehingga tidak dapat login lagi. Sesi pengguna akan langsung dihentikan pada permintaan berikutnya. Data pegawai, aset, dan riwayat aktivitas tetap aman.';
                statusIconWrapper.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100';
                statusIconAmber.classList.remove('hidden');
                statusIconEmerald.classList.add('hidden');
                btnSubmitStatus.className = 'rounded-xl px-5 py-2 text-sm font-medium text-white shadow-sm bg-amber-600 hover:bg-amber-700 transition';
                btnSubmitStatus.textContent = 'Nonaktifkan';
            } else {
                statusTitle.textContent = 'Aktifkan Akun';
                statusDesc.textContent = 'Akun akan diaktifkan kembali sehingga pengguna dapat login ke aplikasi Invensbun.';
                statusIconWrapper.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100';
                statusIconAmber.classList.add('hidden');
                statusIconEmerald.classList.remove('hidden');
                btnSubmitStatus.className = 'rounded-xl px-5 py-2 text-sm font-medium text-white shadow-sm bg-disbun-700 hover:bg-disbun-800 transition';
                btnSubmitStatus.textContent = 'Aktifkan';
            }

            statusModal.classList.remove('hidden');
            statusModal.classList.add('flex');
        }

        function closeStatusModal() {
            statusModal.classList.add('hidden');
            statusModal.classList.remove('flex');
        }

        document.querySelectorAll('[data-ubah-status]').forEach(btn => {
            btn.addEventListener('click', () => openStatusModal(btn));
        });
        document.querySelectorAll('[data-status-modal-close]').forEach(el => {
            el.addEventListener('click', closeStatusModal);
        });

        if (statusForm) {
            statusForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                btnSubmitStatus.disabled = true;
                const origText = btnSubmitStatus.textContent;
                btnSubmitStatus.textContent = 'Memproses...';

                try {
                    const formData = new FormData(statusForm);
                    const res = await fetch(statusForm.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });
                    const data = await res.json();
                    if (!res.ok || data.success === false) {
                        const errMsg = data.errors ? Object.values(data.errors)[0]?.[0] : (data.message || 'Gagal mengubah status akun.');
                        if (statusError) {
                            statusError.textContent = errMsg;
                            statusError.classList.remove('hidden');
                        } else {
                            alert(errMsg);
                        }
                        btnSubmitStatus.disabled = false;
                        btnSubmitStatus.textContent = origText;
                        return;
                    }
                    window.location.reload();
                } catch (err) {
                    alert('Terjadi kesalahan saat memproses status akun.');
                    btnSubmitStatus.disabled = false;
                    btnSubmitStatus.textContent = origText;
                }
            });
        }

        // ---- Helper Tampilkan & Bersihkan Error ----
        function showFieldErrors(container, errors) {
            container.querySelectorAll('.field-error').forEach(e => {
                e.textContent = '';
                e.classList.add('hidden');
            });
            Object.keys(errors).forEach(field => {
                const err = container.querySelector('[data-error-for="' + field + '"]');
                if (err) {
                    err.textContent = errors[field][0];
                    err.classList.remove('hidden');
                }
            });
        }

        function clearFieldErrors(container) {
            container.querySelectorAll('.field-error').forEach(e => {
                e.textContent = '';
                e.classList.add('hidden');
            });
        }

        // ---- Shared Dropdown Synchronization (SKPD -> Lokasi & Filter Ruangan) ----
        function setupSkpdRuanganSync(skpdSelect, lokasiInput, ruanganSelect) {
            if (!skpdSelect || !ruanganSelect) return () => {};

            function applySync(keepRuanganId) {
                const opt = skpdSelect.options[skpdSelect.selectedIndex];
                const lokasiId = opt?.dataset.lokasiId || '';
                const lokasiNama = opt?.dataset.lokasiNama || '';

                if (lokasiInput) {
                    lokasiInput.value = lokasiNama || (opt && opt.value ? '-' : '');
                }

                let hasOption = false;
                Array.from(ruanganSelect.options).forEach(rOpt => {
                    if (!rOpt.value) {
                        rOpt.style.display = '';
                        return;
                    }
                    const match = rOpt.dataset.shared === '1' || (lokasiId && rOpt.dataset.lokasi === lokasiId);
                    rOpt.style.display = match ? '' : 'none';
                    if (match) hasOption = true;
                });

                const keep = keepRuanganId || '';
                const keepOpt = Array.from(ruanganSelect.options).find(o => o.value === String(keep));
                if (keep && keepOpt && keepOpt.style.display !== 'none') {
                    ruanganSelect.value = String(keep);
                } else {
                    ruanganSelect.value = '';
                }
            }

            skpdSelect.addEventListener('change', () => applySync(''));
            return applySync;
        }

        // Inisialisasi Sinkronisasi untuk Modal Tambah & Edit
        const tambahSkpdSelect = document.getElementById('tambah_id_skpd');
        const tambahLokasiInput = document.getElementById('tambah_lokasi');
        const tambahRuanganSelect = document.getElementById('tambah_id_ruangan');
        const syncTambah = setupSkpdRuanganSync(tambahSkpdSelect, tambahLokasiInput, tambahRuanganSelect);

        const editSkpdSelect = document.getElementById('edit_id_skpd');
        const editLokasiInput = document.getElementById('edit_lokasi');
        const editRuanganSelect = document.getElementById('edit_id_ruangan');
        const syncEdit = setupSkpdRuanganSync(editSkpdSelect, editLokasiInput, editRuanganSelect);

        // ---- Modal Tambah Pegawai Handler ----
        const modalTambah = document.getElementById('modal-tambah-pegawai');
        const formTambah = document.getElementById('form-tambah-pegawai');
        const btnOpenTambah = document.getElementById('btn-tambah-pegawai');
        const btnSubmitTambah = document.getElementById('btn-submit-tambah-pegawai');
        const tambahGeneralError = document.getElementById('tambah_general-error');

        function openTambahModal() {
            if (!modalTambah) return;
            if (formTambah) formTambah.reset();
            clearFieldErrors(formTambah);
            if (tambahGeneralError) {
                tambahGeneralError.textContent = '';
                tambahGeneralError.classList.add('hidden');
            }
            syncTambah('');
            modalTambah.classList.remove('hidden');
            modalTambah.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeTambahModal() {
            if (!modalTambah) return;
            modalTambah.classList.add('hidden');
            modalTambah.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        if (btnOpenTambah) {
            btnOpenTambah.addEventListener('click', openTambahModal);
        }
        document.querySelectorAll('[data-tambah-modal-close]').forEach(el => {
            el.addEventListener('click', closeTambahModal);
        });

        if (formTambah) {
            formTambah.addEventListener('submit', async (e) => {
                e.preventDefault();
                btnSubmitTambah.disabled = true;
                const origText = btnSubmitTambah.textContent;
                btnSubmitTambah.textContent = 'Menyimpan...';

                clearFieldErrors(formTambah);
                if (tambahGeneralError) {
                    tambahGeneralError.textContent = '';
                    tambahGeneralError.classList.add('hidden');
                }

                try {
                    const formData = new FormData(formTambah);
                    const res = await fetch(formTambah.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const data = await res.json();

                    if (res.status === 422) {
                        if (data.errors) {
                            showFieldErrors(formTambah, data.errors);
                        }
                        if (data.message && (!data.errors || Object.keys(data.errors).length === 0)) {
                            if (tambahGeneralError) {
                                tambahGeneralError.textContent = data.message;
                                tambahGeneralError.classList.remove('hidden');
                            }
                        }
                        btnSubmitTambah.disabled = false;
                        btnSubmitTambah.textContent = origText;
                        return;
                    }

                    if (!res.ok || data.success === false) {
                        const errMsg = data.message || 'Gagal menambahkan pegawai.';
                        if (tambahGeneralError) {
                            tambahGeneralError.textContent = errMsg;
                            tambahGeneralError.classList.remove('hidden');
                        } else {
                            alert(errMsg);
                        }
                        btnSubmitTambah.disabled = false;
                        btnSubmitTambah.textContent = origText;
                        return;
                    }

                    window.location.reload();
                } catch (err) {
                    if (tambahGeneralError) {
                        tambahGeneralError.textContent = 'Terjadi kesalahan koneksi saat menyimpan data.';
                        tambahGeneralError.classList.remove('hidden');
                    } else {
                        alert('Terjadi kesalahan koneksi.');
                    }
                    btnSubmitTambah.disabled = false;
                    btnSubmitTambah.textContent = origText;
                }
            });
        }

        // ---- Modal Edit Pegawai Handler ----
        const modalEdit = document.getElementById('modal-edit-pegawai');
        const formEdit = document.getElementById('form-edit-pegawai');
        const editNipInput = document.getElementById('edit_nip');
        const editNamaInput = document.getElementById('edit_nama_pegawai');
        const editJabatanInput = document.getElementById('edit_jabatan');
        const editGeneralError = document.getElementById('edit_general-error');
        const btnSubmitEdit = document.getElementById('btn-submit-edit-pegawai');

        function openEditModal(btn) {
            const action = btn.dataset.action || '';
            const nip = btn.dataset.nip || '';
            const nama = btn.dataset.nama || '';
            const jabatan = btn.dataset.jabatan || '';
            const skpdId = btn.dataset.skpdId || '';
            const ruanganId = btn.dataset.ruanganId || '';

            formEdit.action = action;
            editNipInput.value = nip;
            editNamaInput.value = nama;
            editJabatanInput.value = jabatan;
            editSkpdSelect.value = skpdId;

            clearFieldErrors(formEdit);
            if (editGeneralError) {
                editGeneralError.textContent = '';
                editGeneralError.classList.add('hidden');
            }

            syncEdit(ruanganId);

            modalEdit.classList.remove('hidden');
            modalEdit.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeEditModal() {
            modalEdit.classList.add('hidden');
            modalEdit.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        document.querySelectorAll('[data-edit-pegawai]').forEach(btn => {
            btn.addEventListener('click', () => openEditModal(btn));
        });
        document.querySelectorAll('[data-edit-modal-close]').forEach(el => {
            el.addEventListener('click', closeEditModal);
        });

        if (formEdit) {
            formEdit.addEventListener('submit', async (e) => {
                e.preventDefault();
                btnSubmitEdit.disabled = true;
                const origText = btnSubmitEdit.textContent;
                btnSubmitEdit.textContent = 'Menyimpan...';

                clearFieldErrors(formEdit);
                if (editGeneralError) {
                    editGeneralError.textContent = '';
                    editGeneralError.classList.add('hidden');
                }

                try {
                    const formData = new FormData(formEdit);
                    formData.append('_method', 'PUT');

                    const res = await fetch(formEdit.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const data = await res.json();

                    if (res.status === 422) {
                        if (data.errors) {
                            showFieldErrors(formEdit, data.errors);
                        }
                        if (data.message && (!data.errors || Object.keys(data.errors).length === 0)) {
                            if (editGeneralError) {
                                editGeneralError.textContent = data.message;
                                editGeneralError.classList.remove('hidden');
                            }
                        }
                        btnSubmitEdit.disabled = false;
                        btnSubmitEdit.textContent = origText;
                        return;
                    }

                    if (!res.ok || data.success === false) {
                        const errMsg = data.message || 'Gagal menyimpan perubahan data pegawai.';
                        if (editGeneralError) {
                            editGeneralError.textContent = errMsg;
                            editGeneralError.classList.remove('hidden');
                        } else {
                            alert(errMsg);
                        }
                        btnSubmitEdit.disabled = false;
                        btnSubmitEdit.textContent = origText;
                        return;
                    }

                    window.location.reload();
                } catch (err) {
                    if (editGeneralError) {
                        editGeneralError.textContent = 'Terjadi kesalahan koneksi saat menyimpan data.';
                        editGeneralError.classList.remove('hidden');
                    } else {
                        alert('Terjadi kesalahan koneksi.');
                    }
                    btnSubmitEdit.disabled = false;
                    btnSubmitEdit.textContent = origText;
                }
            });
        }

        // ---- Modal Buat Akun ----
        const buatAkunModal = document.getElementById('modal-buat-akun');
        const buatAkunForm = document.getElementById('form-buat-akun');
        const baCardNama = document.getElementById('ba-card-nama');
        const baCardNip = document.getElementById('ba-card-nip');
        const baCardJabatan = document.getElementById('ba-card-jabatan');
        const baCardSkpd = document.getElementById('ba-card-skpd');
        const baRoleSelect = document.getElementById('ba-role');
        const baUsernameInput = document.getElementById('ba-username');
        const baPasswordInput = document.getElementById('ba-password');
        const baPasswordHint = document.getElementById('ba-password-hint');
        const baPasswordRequiredMark = document.getElementById('ba-password-required-mark');
        const buatAkunGeneralError = document.getElementById('buat-akun-general-error');
        const btnSubmitBuatAkun = document.getElementById('btn-submit-buat-akun');

        function openBuatAkunModal(btn) {
            const action = btn.dataset.action || '';
            const nama = btn.dataset.nama || '';
            const nip = btn.dataset.nip || '';
            const jabatan = btn.dataset.jabatan || '-';
            const skpd = btn.dataset.skpd || '-';
            const jenisSkpd = btn.dataset.jenisSkpd || '';

            buatAkunForm.action = action;
            baCardNama.textContent = nama;
            baCardNip.textContent = nip || '-';
            baCardJabatan.textContent = jabatan;
            baCardSkpd.textContent = skpd;

            baUsernameInput.value = nip;
            baPasswordInput.value = '';

            const nipLength = (nip || '').trim().length;
            if (nipLength >= 8) {
                baPasswordInput.required = false;
                baPasswordInput.placeholder = 'Minimal 8 karakter (opsional, default: NIP)';
                if (baPasswordRequiredMark) baPasswordRequiredMark.classList.add('hidden');
                if (baPasswordHint) {
                    baPasswordHint.textContent = 'Opsional: kosongkan jika ingin menggunakan NIP sebagai password awal.';
                    baPasswordHint.className = 'text-[11px] text-slate-400';
                }
            } else {
                baPasswordInput.required = true;
                baPasswordInput.placeholder = 'Minimal 8 karakter (wajib diisi)';
                if (baPasswordRequiredMark) baPasswordRequiredMark.classList.remove('hidden');
                if (baPasswordHint) {
                    baPasswordHint.textContent = '* Wajib diisi karena NIP pegawai kurang dari 8 karakter.';
                    baPasswordHint.className = 'text-[11px] font-semibold text-amber-600';
                }
            }

            clearFieldErrors(buatAkunForm);
            if (buatAkunGeneralError) {
                buatAkunGeneralError.textContent = '';
                buatAkunGeneralError.classList.add('hidden');
            }

            // Filter role sesuai jenis SKPD pegawai
            Array.from(baRoleSelect.options).forEach(opt => {
                const roleNama = opt.dataset.roleNama;
                let allowed = false;
                if (roleNama === 'Pegawai') {
                    allowed = true;
                } else if (roleNama === 'Admin Aset' && jenisSkpd === 'Sekretariat') {
                    allowed = true;
                } else if (roleNama === 'Admin Bidang' && jenisSkpd === 'Bidang') {
                    allowed = true;
                } else if (roleNama === 'Admin UPT' && jenisSkpd === 'UPT') {
                    allowed = true;
                }

                opt.hidden = !allowed;
                opt.disabled = !allowed;
            });

            const defaultRoleOpt = Array.from(baRoleSelect.options).find(o => !o.disabled && o.dataset.roleNama === 'Pegawai')
                || Array.from(baRoleSelect.options).find(o => !o.disabled);
            if (defaultRoleOpt) {
                baRoleSelect.value = defaultRoleOpt.value;
            }

            buatAkunModal.classList.remove('hidden');
            buatAkunModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeBuatAkunModal() {
            buatAkunModal.classList.add('hidden');
            buatAkunModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        document.querySelectorAll('[data-buat-akun]').forEach(btn => {
            btn.addEventListener('click', () => openBuatAkunModal(btn));
        });
        document.querySelectorAll('[data-buat-akun-close]').forEach(el => {
            el.addEventListener('click', closeBuatAkunModal);
        });

        if (buatAkunForm) {
            buatAkunForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                btnSubmitBuatAkun.disabled = true;
                const origText = btnSubmitBuatAkun.textContent;
                btnSubmitBuatAkun.textContent = 'Menyimpan...';

                clearFieldErrors(buatAkunForm);
                if (buatAkunGeneralError) {
                    buatAkunGeneralError.textContent = '';
                    buatAkunGeneralError.classList.add('hidden');
                }

                try {
                    const formData = new FormData(buatAkunForm);
                    const res = await fetch(buatAkunForm.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const data = await res.json();

                    if (res.status === 422) {
                        if (data.errors) {
                            showFieldErrors(buatAkunForm, data.errors);
                        }
                        if (data.message && (!data.errors || Object.keys(data.errors).length === 0)) {
                            if (buatAkunGeneralError) {
                                buatAkunGeneralError.textContent = data.message;
                                buatAkunGeneralError.classList.remove('hidden');
                            }
                        }
                        btnSubmitBuatAkun.disabled = false;
                        btnSubmitBuatAkun.textContent = origText;
                        return;
                    }

                    if (!res.ok || data.success === false) {
                        const errMsg = data.message || 'Gagal membuat akun.';
                        if (buatAkunGeneralError) {
                            buatAkunGeneralError.textContent = errMsg;
                            buatAkunGeneralError.classList.remove('hidden');
                        } else {
                            alert(errMsg);
                        }
                        btnSubmitBuatAkun.disabled = false;
                        btnSubmitBuatAkun.textContent = origText;
                        return;
                    }

                    window.location.reload();
                } catch (err) {
                    if (buatAkunGeneralError) {
                        buatAkunGeneralError.textContent = 'Terjadi kesalahan koneksi saat membuat akun.';
                        buatAkunGeneralError.classList.remove('hidden');
                    } else {
                        alert('Terjadi kesalahan koneksi.');
                    }
                    btnSubmitBuatAkun.disabled = false;
                    btnSubmitBuatAkun.textContent = origText;
                }
            });
        }
    </script>
    @endpush
@endsection
