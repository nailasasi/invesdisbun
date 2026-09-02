@extends('layouts.app')

@section('title', 'User Management')

@section('content')
    <x-page-header title="User Management" subtitle="Kelola data pegawai, akun login, dan hak akses (role)">
        <x-slot:actions>
            <x-button type="button" id="btn-tambah" icon="M12 4v16m8-8H4">
                Tambah User
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <form method="GET" action="{{ route('user.index') }}" class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input
                    type="text"
                    name="search"
                    id="search-pegawai"
                    value="{{ request('search') }}"
                    placeholder="Cari NIP atau Nama pegawai..."
                    autocomplete="off"
                    class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition"
                >
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIP</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKPD</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($pegawaiList as $i => $pegawai)
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $pegawaiList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $pegawai->nip ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->nama_pegawai }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->jabatan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $pegawai->skpd?->nama_skpd ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" data-role-modal="{{ $pegawai->id_pegawai }}" data-role-name="{{ $pegawai->nama_pegawai }}" data-role-current="{{ $pegawai->user?->id_role ?? '' }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        Ubah Role
                                    </button>
                                    <button type="button" data-edit-modal="{{ $pegawai->id_pegawai }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        Edit
                                    </button>
                                    <button type="button" data-delete-target="{{ $pegawai->id_pegawai }}" data-delete-name="{{ $pegawai->nama_pegawai }}" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada data user.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $pegawaiList->links() }}
        </div>
    </x-card>

    {{-- Modal Tambah / Edit User --}}
    <div id="user-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-modal-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 id="modal-title" class="text-lg font-semibold text-slate-900">Tambah User</h3>
                    <p id="modal-subtitle" class="mt-0.5 text-sm text-slate-500">Isi data pegawai, akun login, dan role.</p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="user-form" method="POST" action="{{ route('user.store') }}" autocomplete="off">
                @csrf
                <input type="hidden" id="field-id" name="id" value="">
                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-6">
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
                                    <option value="{{ $skpd->id_skpd }}">{{ $skpd->nama_skpd }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_skpd"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="field-role" class="block text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                            <select id="field-role" name="id_role" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                @foreach ($roleList as $role)
                                    <option value="{{ $role->id_role }}" @selected($role->nama_role === 'Pegawai')>{{ $role->nama_role }}</option>
                                @endforeach
                            </select>
                            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_role"></p>
                        </div>

                        <div id="account-fields" class="grid grid-cols-1 gap-5 sm:col-span-2 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="field-username" class="block text-sm font-medium text-slate-700">Username <span class="text-red-500">*</span></label>
                                <input type="text" id="field-username" name="username" maxlength="255" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="Username login">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="username"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-password" class="block text-sm font-medium text-slate-700">
                                    Reset Password
                                    <span id="password-hint" class="text-xs font-normal text-slate-400">(kosongkan = tidak diubah)</span>
                                </label>
                                <input type="password" id="field-password" name="password" autocomplete="new-password" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="Minimal 8 karakter">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="password"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                        Simpan
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    {{-- Modal Ubah Role --}}
    <div id="role-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-role-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900">Ubah Role</h3>
                    <p class="mt-1 text-sm text-slate-500">Ubah hak akses untuk <span id="role-pegawai-name" class="font-medium text-slate-700"></span></p>
                </div>
            </div>
            <form id="role-form" class="mt-5">
                <div class="space-y-1.5">
                    <label for="role-field" class="block text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                    <select id="role-field" name="id_role" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        @foreach ($roleList as $role)
                            <option value="{{ $role->id_role }}">{{ $role->nama_role }}</option>
                        @endforeach
                    </select>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_role"></p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" data-role-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" id="btn-role-confirm" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Simpan</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div id="delete-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-delete-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900">Hapus User</h3>
                    <p class="mt-1 text-sm text-slate-500">Apakah Anda yakin ingin menghapus <span id="delete-name" class="font-medium text-slate-700"></span> beserta akun loginnnya? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" data-delete-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="button" id="btn-delete-confirm" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">Hapus</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const searchIndexUrl = @json(route('user.index'));
        const storeUrl = @json(route('user.store'));
        const roleUrl = @json(route('user.role.update', ['pegawai' => '__ID__']));

        const searchInput = document.getElementById('search-pegawai');
        if (searchInput) {
            let searchTimer;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const keyword = searchInput.value.trim();
                    const url = new URL(searchIndexUrl, window.location.origin);
                    if (keyword) {
                        url.searchParams.set('search', keyword);
                    }
                    window.location.href = url.toString();
                }, 300);
            });
        }

        const modal = document.getElementById('user-modal');
        const roleModal = document.getElementById('role-modal');
        const roleForm = document.getElementById('role-form');
        const deleteModal = document.getElementById('delete-modal');
        const form = document.getElementById('user-form');
        const accountFields = document.getElementById('account-fields');

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

        function openRoleModal(id, name, currentRole) {
            if (!roleModal) return;
            document.getElementById('role-pegawai-name').textContent = name;
            roleModal.setAttribute('data-current-id', id);
            const sel = document.getElementById('role-field');
            if (currentRole) { sel.value = ''; sel.value = currentRole; }
            roleModal.classList.remove('hidden');
            roleModal.classList.add('flex');
            roleForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }
        function closeRoleModal() {
            if (!roleModal) return;
            roleModal.classList.add('hidden');
            roleModal.classList.remove('flex');
        }

        function openDeleteModal(id, name) {
            if (!deleteModal) return;
            document.getElementById('delete-name').textContent = name;
            deleteModal.setAttribute('data-current-id', id);
            deleteModal.classList.remove('hidden');
            deleteModal.classList.add('flex');
        }
        function closeDeleteModal() {
            if (!deleteModal) return;
            deleteModal.classList.add('hidden');
            deleteModal.classList.remove('flex');
        }

        if (form) {
            const btnTambah = document.getElementById('btn-tambah');
            if (btnTambah) {
                btnTambah.addEventListener('click', () => {
                    form.reset();
                    document.getElementById('field-id').value = '';
                    form.action = storeUrl;
                    document.getElementById('modal-title').textContent = 'Tambah User';
                    document.getElementById('modal-subtitle').textContent = 'Isi data pegawai, akun login, dan role.';
                    document.getElementById('btn-submit').textContent = 'Simpan';
                    accountFields.classList.add('hidden');
                    form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                    openModal();
                });
            }

            document.querySelectorAll('[data-edit-modal]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.editModal;
                    form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                    try {
                        const res = await fetch(storeUrl + '/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Gagal mengambil data');
                        const data = await res.json();
                        document.getElementById('field-id').value = data.id_pegawai;
                        document.getElementById('field-nip').value = data.nip ?? '';
                        document.getElementById('field-nama').value = data.nama_pegawai;
                        document.getElementById('field-jabatan').value = data.jabatan ?? '';
                        document.getElementById('field-skpd').value = data.id_skpd ?? '';
                        const roleSel = document.getElementById('field-role');
                        if (data.id_role) roleSel.value = data.id_role;
                        document.getElementById('field-username').value = data.username ?? '';
                        document.getElementById('field-password').value = '';
                        form.action = storeUrl + '/' + id;
                        document.getElementById('modal-title').textContent = 'Edit User';
                        document.getElementById('modal-subtitle').textContent = 'Perbarui data pegawai, username, password, dan role.';
                        document.getElementById('btn-submit').textContent = 'Perbarui';
                        accountFields.classList.remove('hidden');
                        openModal();
                    } catch (e) {
                        alert(e.message);
                    }
                });
            });

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
                    const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                    if (res.status === 422) {
                        const data = await res.json();
                        showErrors(form, data.errors);
                        submit.textContent = original;
                        submit.disabled = false;
                        return;
                    }
                    if (res.ok) {
                        window.location.reload();
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

        document.querySelectorAll('[data-role-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                openRoleModal(btn.dataset.roleModal, btn.dataset.roleName, btn.dataset.roleCurrent);
            });
        });
        if (roleForm) {
            roleModal.querySelectorAll('[data-role-close]').forEach(el => el.addEventListener('click', closeRoleModal));
            roleForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submit = document.getElementById('btn-role-confirm');
                const id = roleModal.getAttribute('data-current-id');
                const original = submit.textContent;
                submit.textContent = 'Menyimpan...';
                submit.disabled = true;
                const body = new FormData(roleForm);
                try {
                    const res = await fetch(roleUrl.replace('__ID__', id), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                    if (res.status === 422) {
                        const data = await res.json();
                        showErrors(roleForm, data.errors);
                        submit.textContent = original;
                        submit.disabled = false;
                        return;
                    }
                    if (res.ok) {
                        window.location.reload();
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
        }

        document.querySelectorAll('[data-delete-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                openDeleteModal(btn.dataset.deleteTarget, btn.dataset.deleteName);
            });
        });
        if (deleteModal) {
            deleteModal.querySelectorAll('[data-delete-close]').forEach(el => el.addEventListener('click', closeDeleteModal));
            const btnConfirm = document.getElementById('btn-delete-confirm');
            btnConfirm.addEventListener('click', async () => {
                const id = deleteModal.getAttribute('data-current-id');
                btnConfirm.textContent = 'Menghapus...';
                btnConfirm.disabled = true;
                try {
                    const res = await fetch(storeUrl + '/' + id, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        alert('Gagal menghapus data.');
                        btnConfirm.textContent = 'Hapus';
                        btnConfirm.disabled = false;
                    }
                } catch (err) {
                    alert('Koneksi bermasalah.');
                    btnConfirm.textContent = 'Hapus';
                    btnConfirm.disabled = false;
                }
            });
        }
    </script>
    @endpush
@endsection