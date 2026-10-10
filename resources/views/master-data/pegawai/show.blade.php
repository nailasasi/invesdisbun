@extends('layouts.app')

@section('title', 'Detail Pegawai')

@section('page-title', 'Detail Pegawai')

@section('content')
    @php
        $user = $pegawai->user;
        $isAktif = $user?->status_user === 'aktif';
        $initial = mb_substr((string) ($pegawai->nama_pegawai ?: '?'), 0, 1);
        $defaultRoleId = $roleList->firstWhere('nama_role', 'Pegawai')?->id_role;
    @endphp

    {{-- Navigasi Kembali --}}
    <div class="mb-4">
        <a href="{{ route('master-data.pegawai.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-disbun-700">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Data Pegawai
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

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-button href="{{ route('master-data.pegawai.edit', $pegawai) }}" variant="primary" icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                Edit Pegawai
            </x-button>

            @if ($user)
                <x-button href="{{ route('user.show', $pegawai) }}" variant="secondary" icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z">
                    Kelola Akun
                </x-button>
            @else
                <x-button type="button" id="btn-buat-akun" icon="M12 4v16m8-8H4">
                    Buat Akun
                </x-button>
            @endif
        </div>
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- DATA PEGAWAI --}}
    <x-card :padding="false">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-base font-bold text-slate-900">Data Pegawai</h3>
            <p class="text-xs text-slate-400">Identitas kepegawaian — diubah melalui halaman Edit Pegawai</p>
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
                    <span class="inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500">{{ $pegawai->skpd->jenis_skpd ?? '-' }}</span>
                @else
                    <p class="text-sm font-medium text-slate-400">-</p>
                @endif
            </div>

            <div class="space-y-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Lokasi</p>
                <p class="text-sm font-medium text-slate-800">{{ $lokasiPegawai?->nama_lokasi ?? '-' }}</p>
                <p class="text-[11px] text-slate-400">Turunan dari SKPD</p>
            </div>

            <div class="space-y-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ruangan</p>
                <p class="text-sm font-medium text-slate-800">{{ $pegawai->ruangan?->nama_ruangan ?? '-' }}</p>
            </div>
        </div>
    </x-card>

    {{-- AKUN PENGGUNA --}}
    <div class="mt-4">
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-bold text-slate-900">Akun Pengguna</h3>
                <p class="text-xs text-slate-400">Akun login milik pegawai ini — 0 atau 1 akun</p>
            </div>

            @if (! $user)
                <div class="flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold uppercase tracking-wide text-slate-600">Belum Ada Akun</p>
                            <p class="text-xs text-slate-400">Pegawai ini belum dapat login ke aplikasi.</p>
                        </div>
                    </div>

                    <x-button type="button" id="btn-buat-akun" icon="M12 4v16m8-8H4">
                        Buat Akun
                    </x-button>
                </div>
            @else
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Username</p>
                        <p class="font-mono text-sm font-medium text-slate-800">{{ $user->username }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Role</p>
                        <p class="text-sm font-medium text-slate-800">{{ $user->role?->nama_role ?? '-' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status</p>
                        <p class="text-sm font-medium {{ $isAktif ? 'text-disbun-700' : 'text-red-600' }}">{{ $isAktif ? 'Aktif' : 'Nonaktif' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Aksi</p>
                        <x-button href="{{ route('user.show', $pegawai) }}" variant="secondary" size="sm">Kelola Akun</x-button>
                    </div>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ===== Modal Buat Akun ===== --}}
    <div id="buat-akun-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-buat-akun-close></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Buat Akun Pengguna</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Akun diambil dari data pegawai yang sudah ada — tidak membuat pegawai baru.</p>
                </div>
                <button type="button" data-buat-akun-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="buat-akun-form" method="POST" action="{{ route('user.akun.store', $pegawai) }}" autocomplete="off">
                @csrf
                <div class="max-h-[90vh] space-y-5 overflow-y-auto px-6 py-6">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pegawai</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $pegawai->nama_pegawai }}</p>
                        <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-600 sm:grid-cols-3">
                            <div><span class="text-slate-400">NIP:</span> <span class="font-mono">{{ $pegawai->nip ?? '-' }}</span></div>
                            <div><span class="text-slate-400">Jabatan:</span> {{ $pegawai->jabatan ?? '-' }}</div>
                            <div><span class="text-slate-400">SKPD:</span> {{ $pegawai->skpd?->nama_skpd ?? '-' }}</div>
                        </dl>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="ba-role" class="block text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                            <select id="ba-role" name="id_role" required
                                    class="block w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('id_role') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                                @foreach ($roleList as $role)
                                    <option value="{{ $role->id_role }}" @selected((string) old('id_role', $defaultRoleId) === (string) $role->id_role)>{{ $role->nama_role }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_role"></p>
                            <p class="text-xs text-slate-400">Default: Pegawai. Role harus sesuai penempatan SKPD pegawai.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="ba-username" class="block text-sm font-medium text-slate-700">Username <span class="text-red-500">*</span></label>
                            <input type="text" id="ba-username" name="username" maxlength="255" value="{{ old('username', $pegawai->nip) }}"
                                   class="block w-full rounded-xl border px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('username') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="username"></p>
                            <p class="text-xs text-slate-400">Default: NIP.</p>
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label for="ba-password" class="block text-sm font-medium text-slate-700">
                                Password
                                <span class="text-xs font-normal text-slate-400">(kosongkan untuk menggunakan NIP)</span>
                            </label>
                            <input type="password" id="ba-password" name="password" autocomplete="new-password"
                                   class="block w-full rounded-xl border px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition {{ $errors->has('password') ? 'border-red-400 focus:border-red-500 focus:ring-red-400' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400' }}"
                                   placeholder="Minimal 8 karakter">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="password"></p>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nip"></p>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="pegawai"></p>
                        </div>
                    </div>

                    <p id="buat-akun-message" class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"></p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-buat-akun-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit-buat-akun" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                        Buat Akun
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const buatAkunModal = document.getElementById('buat-akun-modal');
        const buatAkunForm = document.getElementById('buat-akun-form');
        const btnBuatAkun = document.getElementById('btn-buat-akun');
        const btnSubmitBuatAkun = document.getElementById('btn-submit-buat-akun');
        const pesanBuatAkun = document.getElementById('buat-akun-message');

        function showErrors(container, errors) {
            container.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors).forEach(field => {
                const err = container.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }

        function openBuatAkun() {
            if (!buatAkunModal) return;
            buatAkunModal.classList.remove('hidden');
            buatAkunModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeBuatAkun() {
            if (!buatAkunModal) return;
            buatAkunModal.classList.add('hidden');
            buatAkunModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            buatAkunForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            pesanBuatAkun.classList.add('hidden');
        }

        if (btnBuatAkun) {
            btnBuatAkun.addEventListener('click', openBuatAkun);
        }

        if (buatAkunModal) {
            buatAkunModal.querySelectorAll('[data-buat-akun-close]').forEach(el => el.addEventListener('click', closeBuatAkun));
        }

        if (buatAkunForm) {
            buatAkunForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const original = btnSubmitBuatAkun.textContent;
                btnSubmitBuatAkun.textContent = 'Menyimpan...';
                btnSubmitBuatAkun.disabled = true;
                pesanBuatAkun.classList.add('hidden');

                const body = new FormData(buatAkunForm);

                try {
                    const res = await fetch(buatAkunForm.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body
                    });

                    if (res.status === 422) {
                        const data = await res.json();
                        if (data.errors) { showErrors(buatAkunForm, data.errors); }
                        if (data.message) {
                            pesanBuatAkun.textContent = data.message;
                            pesanBuatAkun.classList.remove('hidden');
                        }
                        btnSubmitBuatAkun.textContent = original;
                        btnSubmitBuatAkun.disabled = false;
                        return;
                    }

                    if (res.ok) {
                        window.location.href = @json(route('master-data.pegawai.show', $pegawai));
                    } else {
                        pesanBuatAkun.textContent = 'Terjadi kesalahan. Coba lagi.';
                        pesanBuatAkun.classList.remove('hidden');
                        btnSubmitBuatAkun.textContent = original;
                        btnSubmitBuatAkun.disabled = false;
                    }
                } catch (err) {
                    pesanBuatAkun.textContent = 'Koneksi bermasalah. Coba lagi.';
                    pesanBuatAkun.classList.remove('hidden');
                    btnSubmitBuatAkun.textContent = original;
                    btnSubmitBuatAkun.disabled = false;
                }
            });
        }

        @if ($errors->any())
            document.addEventListener('DOMContentLoaded', openBuatAkun);
        @endif
    </script>
    @endpush
@endsection
