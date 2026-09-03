@extends('layouts.app')

@section('title', 'Aset Ruangan')

@section('content')
    <x-page-header title="Aset Ruangan" subtitle="Kelola ruangan beserta aset yang ditempatkan di dalamnya">
        @if ($isAdminAset)
            <x-slot name="actions">
                <x-button type="button" id="btn-tambah" icon="M12 4v16m8-8H4">
                    Tambah Ruangan
                </x-button>
            </x-slot>
        @endif
    </x-page-header>

    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        @if ($isAdminAset)
        <form method="GET" action="{{ route('aset-ruangan.index') }}" class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center">
            <select name="ruangan" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition sm:w-72">
                <option value="">Semua Ruangan</option>
                @foreach ($ruanganOptions as $r)
                    <option value="{{ $r->id_ruangan }}" @selected(request('ruangan') == $r->id_ruangan)>{{ $r->nama_ruangan }}</option>
                @endforeach
            </select>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                Filter
            </button>
        </form>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Ruangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Lantai</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SKPD</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah Aset</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($ruanganList as $i => $ruangan)
                        @php
                            $editJson = json_encode([
                                'id' => $ruangan->id_ruangan,
                                'nama_ruangan' => $ruangan->nama_ruangan,
                                'lantai' => $ruangan->lantai,
                                'id_skpd' => $ruangan->id_skpd,
                            ]);
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $ruanganList->firstItem() + $i }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $ruangan->nama_ruangan }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $ruangan->lantai ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $ruangan->skpd?->nama_skpd ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    {{ $ruangan->jumlah_aset }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('aset-ruangan.show', $ruangan->id_ruangan) }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100">
                                        Detail
                                    </a>
                                    @if ($isAdminAset)
                                        <button type="button" data-edit-modal="{{ $editJson }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                                            Edit
                                        </button>
                                        <button type="button" data-delete-target="{{ $ruangan->id_ruangan }}" data-delete-name="{{ $ruangan->nama_ruangan }}" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">
                                            Hapus
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada ruangan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $ruanganList->links() }}
        </div>
    </x-card>

    @if ($isAdminAset)
    {{-- Modal Tambah / Edit Ruangan --}}
    <div id="ruangan-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-modal-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 id="modal-title" class="text-lg font-semibold text-slate-900">Tambah Ruangan</h3>
                    <p id="modal-subtitle" class="mt-0.5 text-sm text-slate-500">Lengkapi data ruangan.</p>
                </div>
                <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="ruangan-form" method="POST" action="{{ route('aset-ruangan.store') }}" autocomplete="off">
                @csrf
                <input type="hidden" id="modal-id" value="">
                <div class="space-y-5 px-6 py-6">
                    <x-input label="Nama Ruangan" name="nama_ruangan" id="field-nama" placeholder="contoh: Ruang Kepala" required maxlength="100" />
                    <x-input label="Lantai" name="lantai" id="field-lantai" placeholder="contoh: 2" maxlength="20" />
                    <x-select label="SKPD" name="id_skpd" id="field-skpd" :options="$skpdOptions->pluck('nama_skpd', 'id_skpd')" placeholder="-- Pilih SKPD --" />
                    <p id="form-error" class="hidden rounded-xl bg-red-50 px-4 py-2 text-xs font-medium text-red-600"></p>
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
                    <h3 class="text-lg font-semibold text-slate-900">Hapus Ruangan</h3>
                    <p class="mt-1 text-sm text-slate-500">Apakah Anda yakin ingin menghapus <span id="delete-name" class="font-medium text-slate-700"></span>? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" data-delete-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="button" id="btn-delete-confirm" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">Hapus</button>
            </div>
        </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const storeUrl = @json(route('aset-ruangan.store'));
        const updateUrl = @json(route('aset-ruangan.update', ['ruangan' => '__ID__']));
        const destroyUrl = @json(route('aset-ruangan.destroy', ['ruangan' => '__ID__']));

        const modal = document.getElementById('ruangan-modal');
        const deleteModal = document.getElementById('delete-modal');
        const form = document.getElementById('ruangan-form');

        function openModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('form-error').classList.add('hidden');
        }
        function openDeleteModal(id, name) {
            document.getElementById('delete-name').textContent = name;
            deleteModal.setAttribute('data-current-id', id);
            deleteModal.classList.remove('hidden');
            deleteModal.classList.add('flex');
        }
        function closeDeleteModal() {
            deleteModal.classList.add('hidden');
            deleteModal.classList.remove('flex');
        }

        const btnTambah = document.getElementById('btn-tambah');
        if (btnTambah) {
            btnTambah.addEventListener('click', () => {
                form.reset();
                document.getElementById('modal-id').value = '';
                form.action = storeUrl;
                document.getElementById('modal-title').textContent = 'Tambah Ruangan';
                document.getElementById('modal-subtitle').textContent = 'Lengkapi data ruangan.';
                document.getElementById('btn-submit').textContent = 'Simpan';
                openModal();
            });
        }

        document.querySelectorAll('[data-edit-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const data = JSON.parse(btn.dataset.editModal);
                document.getElementById('modal-id').value = data.id;
                document.getElementById('field-nama').value = data.nama_ruangan ?? '';
                document.getElementById('field-lantai').value = data.lantai ?? '';
                document.getElementById('field-skpd').value = data.id_skpd ?? '';
                form.action = updateUrl.replace('__ID__', data.id);
                document.getElementById('modal-title').textContent = 'Edit Ruangan';
                document.getElementById('modal-subtitle').textContent = 'Perbarui data ruangan.';
                document.getElementById('btn-submit').textContent = 'Perbarui';
                openModal();
            });
        });

        document.querySelectorAll('[data-delete-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                openDeleteModal(btn.dataset.deleteTarget, btn.dataset.deleteName);
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submit = document.getElementById('btn-submit');
            const original = submit.textContent;
            const errBox = document.getElementById('form-error');
            errBox.classList.add('hidden');
            submit.textContent = 'Menyimpan...';
            submit.disabled = true;
            const body = new FormData(form);
            try {
                const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                if (res.status === 422) {
                    const data = await res.json();
                    const errors = Object.values(data.errors || {}).flat();
                    if (errors.length) { errBox.textContent = errors[0]; errBox.classList.remove('hidden'); }
                    submit.textContent = original;
                    submit.disabled = false;
                    return;
                }
                if (res.ok) { window.location.reload(); }
                else if (res.status === 403) { alert('Anda tidak memiliki hak akses.'); submit.textContent = original; submit.disabled = false; }
                else { alert('Terjadi kesalahan. Coba lagi.'); submit.textContent = original; submit.disabled = false; }
            } catch (err) {
                alert('Koneksi bermasalah. Coba lagi.');
                submit.textContent = original;
                submit.disabled = false;
            }
        });

        document.getElementById('btn-delete-confirm').addEventListener('click', async () => {
            const id = deleteModal.getAttribute('data-current-id');
            const btn = document.getElementById('btn-delete-confirm');
            btn.textContent = 'Menghapus...';
            btn.disabled = true;
            try {
                const res = await fetch(destroyUrl.replace('__ID__', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                if (res.ok) { window.location.reload(); }
                else { alert('Gagal menghapus data.'); btn.textContent = 'Hapus'; btn.disabled = false; }
            } catch (err) {
                alert('Koneksi bermasalah.');
                btn.textContent = 'Hapus';
                btn.disabled = false;
            }
        });

        modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
        deleteModal.querySelectorAll('[data-delete-close]').forEach(el => el.addEventListener('click', closeDeleteModal));
    </script>
    @endpush
    @endif
@endsection
