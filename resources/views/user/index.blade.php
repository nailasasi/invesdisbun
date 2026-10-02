@extends('layouts.app')

@section('title', 'User Management')

@section('page-title', 'User Management')

@section('content')
    @php $defaultRoleId = $roleList->firstWhere('nama_role', 'Pegawai')?->id_role; @endphp

    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('user.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input
                        type="text"
                        name="search"
                        id="search-pegawai"
                        value="{{ request('search') }}"
                        placeholder="Cari NIP, Nama, atau Username..."
                        autocomplete="off"
                        class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition"
                    >
                </div>
                <select
                    name="status"
                    id="filter-status"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-disbun-600 focus:ring-2 focus:ring-disbun-400 transition"
                >
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    Cari
                </button>
            </form>

            <x-slot name="actions">
                <x-button type="button" id="btn-tambah" icon="M12 4v16m8-8H4">
                    Tambah User
                </x-button>
            </x-slot>
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIP</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKPD</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($pegawaiList as $i => $pegawai)
                        @php
                            $statusUser = $pegawai->user?->status_user;
                            $isAktif = $statusUser === 'aktif';
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $pegawaiList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $pegawai->nip ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->nama_pegawai }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->jabatan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->skpd?->nama_skpd ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($statusUser === null)
                                    <span class="inline-block rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">Belum ada akun</span>
                                @else
                                    <span class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ $isAktif ? 'bg-disbun-100 text-disbun-800' : 'bg-red-100 text-red-700' }}">
                                        {{ $isAktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <a
                                    href="{{ route('user.show', $pegawai->id_pegawai) }}"
                                    class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-disbun-600"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg>
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada data user.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $pegawaiList->links() }}
        </div>
    </x-card>

    {{-- Modal Tambah User --}}
    <div id="user-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Tambah User</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Isi data pegawai, SKPD, dan akun login.</p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="user-form" method="POST" action="{{ route('user.store') }}" autocomplete="off">
                @csrf
                <div class="max-h-[90vh] space-y-5 overflow-y-auto px-6 py-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="field-nip" class="block text-sm font-medium text-slate-700">NIP <span class="text-red-500">*</span></label>
                            <input type="text" id="field-nip" name="nip" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 198001012010011001">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nip"></p>
                            <p class="text-xs text-slate-400">NIP dipakai sebagai username awal.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-nama" class="block text-sm font-medium text-slate-700">Nama Pegawai <span class="text-red-500">*</span></label>
                            <input type="text" id="field-nama" name="nama_pegawai" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Drs. Budi Santoso">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_pegawai"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-jabatan" class="block text-sm font-medium text-slate-700">Jabatan</label>
                            <input type="text" id="field-jabatan" name="jabatan" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Kepala Bidang Perkebunan">
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="jabatan"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-skpd" class="block text-sm font-medium text-slate-700">SKPD</label>
                            <select id="field-skpd" name="id_skpd" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <option value="" selected>-- Pilih SKPD --</option>
                                @foreach ($skpdList as $skpd)
                                    @php
                                        $lokasiSkpd = $skpd->isUpt() ? $skpd->lokasi : $lokasiDinas;
                                    @endphp
                                    <option value="{{ $skpd->id_skpd }}" data-lokasi-id="{{ $lokasiSkpd?->id_lokasi ?? '' }}" data-lokasi-nama="{{ $lokasiSkpd?->nama_lokasi ?? '' }}">
                                        {{ $skpd->nama_skpd }} ({{ $skpd->jenis_skpd }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_skpd"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-lokasi" class="block text-sm font-medium text-slate-700">Lokasi</label>
                            <input
                                type="text"
                                id="field-lokasi"
                                readonly
                                placeholder="-- Pilih SKPD dulu --"
                                class="block w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-600 shadow-sm focus:outline-none transition"
                            >
                            <p class="text-xs text-slate-400">Terisi otomatis dari SKPD: Sekretariat &amp; Bidang → Kantor Dinas; UPT → kantor UPT-nya.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-ruangan" class="block text-sm font-medium text-slate-700">Ruangan</label>
                            <select id="field-ruangan" name="id_ruangan" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <option value="" selected>-- Pilih SKPD dulu --</option>
                                @foreach ($ruanganList as $ruangan)
                                    <option
                                        value="{{ $ruangan->id_ruangan }}"
                                        data-lokasi="{{ $ruangan->id_lokasi ?? '' }}"
                                        @if ($ruangan->id_skpd === null) data-shared="1" @endif
                                    >{{ $ruangan->nama_ruangan }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_ruangan"></p>
                            <p class="text-xs text-slate-400">Ruang bersama tersedia untuk semua SKPD.</p>
                        </div>

                        <input type="hidden" id="field-role-hidden" name="id_role" value="{{ $defaultRoleId ?? '' }}">

                        <div id="account-fields" class="grid grid-cols-1 gap-5 sm:col-span-2 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="field-username" class="block text-sm font-medium text-slate-700">Username <span class="text-red-500">*</span></label>
                                <input type="text" id="field-username" name="username" maxlength="255" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="Username login">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="username"></p>
                                <p id="username-hint" class="text-xs text-slate-400">Tambah: otomatis terisi dari NIP.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-password" class="block text-sm font-medium text-slate-700">
                                    Password
                                    <span id="password-hint" class="text-xs font-normal text-slate-400">(kosongkan = sesuai NIP default)</span>
                                </label>
                                <input type="password" id="field-password" name="password" autocomplete="new-password" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="Minimal 8 karakter">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="password"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const searchIndexUrl = @json(route('user.index'));
        const storeUrl = @json(route('user.store'));

        const searchInput = document.getElementById('search-pegawai');
        if (searchInput) {
            let searchTimer;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const keyword = searchInput.value.trim();
                    const status = document.getElementById('filter-status').value;
                    const url = new URL(searchIndexUrl, window.location.origin);
                    if (keyword) { url.searchParams.set('search', keyword); }
                    if (status) { url.searchParams.set('status', status); }
                    window.location.href = url.toString();
                }, 300);
            });
        }

        const modal = document.getElementById('user-modal');
        const form = document.getElementById('user-form');
        const fieldNip = document.getElementById('field-nip');
        const fieldUsername = document.getElementById('field-username');
        const fieldSkpd = document.getElementById('field-skpd');
        const fieldLokasi = document.getElementById('field-lokasi');
        const fieldRuangan = document.getElementById('field-ruangan');

        function showErrors(container, errors) {
            container.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors).forEach(field => {
                const err = container.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }

        function openModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }

        function autoFillUsername() {
            if (fieldNip && fieldUsername && !fieldUsername.dataset.touched) {
                fieldUsername.value = fieldNip.value;
            }
        }

        // Ruangan mengikuti Lokasi SKPD yang dipilih. Ruang bersama
        // (tanpa skpd) selalu tetap selectable untuk seluruh SKPD.
        function applyRuanganFilter() {
            const lokasiId = fieldSkpd.options[fieldSkpd.selectedIndex]?.dataset.lokasiId || '';
            let hasOption = false;

            fieldRuangan.querySelectorAll('option').forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                const match = opt.dataset.shared === '1' || (lokasiId && opt.dataset.lokasi === lokasiId);
                opt.style.display = match ? '' : 'none';
                if (match) hasOption = true;
            });

            fieldRuangan.value = '';
            fieldRuangan.querySelector('option[value=""]').textContent = hasOption
                ? '-- Pilih Ruangan --'
                : (lokasiId ? '-- Tidak ada ruangan untuk lokasi ini --' : '-- Pilih SKPD dulu --');
        }

        // Lokasi kerja terisi otomatis dari SKPD: UPT -> kantor UPT-nya,
        // Sekretariat & Bidang -> kantor dinas.
        function applyLokasiDisplay() {
            const opt = fieldSkpd.options[fieldSkpd.selectedIndex];
            if (fieldLokasi) {
                fieldLokasi.value = opt && opt.value ? (opt.dataset.lokasiNama || '-') : '';
                fieldLokasi.placeholder = opt && opt.value ? '' : '-- Pilih SKPD dulu --';
            }
            applyRuanganFilter();
        }

        fieldSkpd.addEventListener('change', applyLokasiDisplay);
        fieldUsername.addEventListener('input', () => { fieldUsername.dataset.touched = '1'; });

        if (form) {
            const btnTambah = document.getElementById('btn-tambah');
            if (btnTambah) {
                btnTambah.addEventListener('click', () => {
                    form.reset();
                    delete fieldUsername.dataset.touched;
                    form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                    applyLokasiDisplay();
                    autoFillUsername();
                    openModal();
                });
            }

            if (fieldNip) {
                fieldNip.addEventListener('input', autoFillUsername);
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-submit');
                const original = submit.textContent;
                submit.textContent = 'Menyimpan...';
                submit.disabled = true;
                const body = new FormData(form);
                if (!form.querySelector('[name="username"]').value) {
                    body.delete('username');
                }
                try {
                    const res = await fetch(form.action || storeUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                    if (res.status === 422) {
                        const data = await res.json();
                        showErrors(form, data.errors);
                        submit.textContent = original;
                        submit.disabled = false;
                        return;
                    }
                    if (res.ok) {
                        window.location.href = @json(route('user.index'));
                    } else {
                        alert('Terjadi kesalahan. Coba lagi.');
                        submit.textContent = original;
                        submit.disabled = false;
                    }
                } catch (err) {
                    alert('Koneksi bermasalah. Coba lagi.');
                    submit.textContent = original;
                    submit.disabled = false;
                }
            });

            modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
        }
    </script>
    @endpush
@endsection