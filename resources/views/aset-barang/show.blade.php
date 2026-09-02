@extends('layouts.app')

@section('title', 'Detail Aset Barang')

@section('content')
    <a href="{{ route('aset-barang.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-emerald-600">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Aset Barang
    </a>

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Aset {{ $pegawai->nama_pegawai }}</h2>
            <p class="mt-1 text-sm text-slate-500">Aset barang yang sedang dipegang oleh pegawai.</p>
        </div>
        @if ($isAdminAset)
            <x-button type="button" id="btn-tambah" icon="M12 4v16m8-8H4">
                Tambah Aset
            </x-button>
        @endif
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- Profil pegawai --}}
    <x-card>
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-xl font-bold text-white shadow-glow">
                {{ strtoupper(substr($pegawai->nama_pegawai, 0, 2)) }}
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="text-lg font-bold text-slate-900">{{ $pegawai->nama_pegawai }}</h3>
                <p class="text-sm text-slate-500">{{ $pegawai->jabatan ?? '-' }} &bull; {{ $pegawai->skpd?->nama_skpd ?? '-' }}</p>
            </div>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-3 sm:text-right">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">NIP</dt>
                    <dd class="font-semibold text-slate-700">{{ $pegawai->nip ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Aset</dt>
                    <dd class="font-semibold text-emerald-600">{{ $asetList->count() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Nilai</dt>
                    <dd class="font-semibold text-slate-700">Rp {{ number_format($asetList->sum(fn ($item) => (float) $item->aset->nilai_perolehan), 0, ',', '.') }}</dd>
                </div>
            </dl>
        </div>
    </x-card>

    {{-- Daftar aset --}}
    <div class="mt-6">
        <x-card :padding="false">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Aset Barang Dipegang</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kartu Barang</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Merk</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kondisi</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            @if ($isAdminAset)
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($asetList as $i => $item)
                            @php $aset = $item->aset; @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $aset->barang->nama_barang ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nomor_kartu_barang ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->merk ?? '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @php
                                        $kondisiColor = match ($aset->kondisi) {
                                            'Baik' => 'bg-emerald-100 text-emerald-700',
                                            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                                            'Rusak Berat' => 'bg-red-100 text-red-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->status_aset ?? '-' }}</td>
                                @if ($isAdminAset)
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" data-edit-modal="{{ $aset->id_aset }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                                Edit
                                            </button>
                                            <button type="button" data-delete-target="{{ $aset->id_aset }}" data-delete-name="{{ $aset->barang->nama_barang ?? $aset->nomor_kartu_barang }}" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdminAset ? 8 : 7 }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada aset yang dipegang pegawai ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    @if ($isAdminAset)
        {{-- Modal Tambah / Edit Aset --}}
        <div id="aset-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-modal-close></div>
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative z-10 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 id="modal-title" class="text-lg font-semibold text-slate-900">Tambah Aset</h3>
                        <p id="modal-subtitle" class="mt-0.5 text-sm text-slate-500">Aset baru akan dipegang oleh {{ $pegawai->nama_pegawai }}.</p>
                    </div>
                    <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form id="aset-form" method="POST" action="{{ route('aset-barang.store', $pegawai->id_pegawai) }}" autocomplete="off">
                    @csrf
                    <input type="hidden" id="field-id" name="id" value="">
                    <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="field-pegawai" class="block text-sm font-medium text-slate-700">Pemegang <span class="text-red-500">*</span></label>
                                <select id="field-pegawai" name="id_pegawai" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                    @foreach ($allPegawai as $p)
                                        <option value="{{ $p->id_pegawai }}" @selected($p->id_pegawai === $pegawai->id_pegawai)>{{ $p->nama_pegawai }}</option>
                                    @endforeach
                                </select>
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_pegawai"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-barang" class="block text-sm font-medium text-slate-700">Nama Barang <span class="text-red-500">*</span></label>
                                <input type="text" id="field-barang" name="nama_barang" required maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Laptop">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_barang"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-kartu" class="block text-sm font-medium text-slate-700">Nomor Kartu Barang <span class="text-red-500">*</span></label>
                                <input type="text" id="field-kartu" name="nomor_kartu_barang" maxlength="50" required class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: KIB-001">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_kartu_barang"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-merk" class="block text-sm font-medium text-slate-700">Merk</label>
                                <input type="text" id="field-merk" name="merk" maxlength="100" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Canon">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="merk"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-pengadaan" class="block text-sm font-medium text-slate-700">Tanggal Pengadaan</label>
                                <input type="date" id="field-pengadaan" name="tanggal_pengadaan" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_pengadaan"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-perolehan" class="block text-sm font-medium text-slate-700">Tanggal Perolehan</label>
                                <input type="date" id="field-perolehan" name="tanggal_perolehan" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_perolehan"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-habis-pakai" class="block text-sm font-medium text-slate-700">Tanggal Habis Pakai</label>
                                <input type="date" id="field-habis-pakai" name="tanggal_habis_pakai" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_habis_pakai"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-nilai" class="block text-sm font-medium text-slate-700">Nilai Perolehan</label>
                                <input type="number" id="field-nilai" name="nilai_perolehan" step="0.01" min="0" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 5000000">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nilai_perolehan"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-kondisi" class="block text-sm font-medium text-slate-700">Kondisi <span class="text-red-500">*</span></label>
                                <select id="field-kondisi" name="kondisi" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                                    <option value="" selected>-- Pilih Kondisi --</option>
                                    @foreach ($kondisiList as $kondisi)
                                        <option value="{{ $kondisi }}">{{ $kondisi }}</option>
                                    @endforeach
                                </select>
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="kondisi"></p>
                            </div>

                            <div class="space-y-1.5">
                                <label for="field-status" class="block text-sm font-medium text-slate-700">Status Aset <span class="text-red-500">*</span></label>
                                <input type="text" id="field-status" name="status_aset" required maxlength="50" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Aktif">
                                <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="status_aset"></p>
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
                        <h3 class="text-lg font-semibold text-slate-900">Hapus Aset</h3>
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
    @endif

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const isAdminAset = @json($isAdminAset);

        const storeUrl = @json(route('aset-barang.store', $pegawai->id_pegawai));
        const updateUrl = @json(route('aset-barang.aset.update', ['aset' => '__ID__']));

        const modal = document.getElementById('aset-modal');
        const deleteModal = document.getElementById('delete-modal');
        const form = document.getElementById('aset-form');

        function showErrors(errors) {
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors).forEach(field => {
                const err = form.querySelector('[data-error-for="' + field + '"]');
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
                    document.getElementById('field-pegawai').value = @json($pegawai->id_pegawai);
                    form.action = storeUrl;
                    document.getElementById('modal-title').textContent = 'Tambah Aset';
                    document.getElementById('modal-subtitle').textContent = 'Aset baru akan dipegang oleh ' + @json($pegawai->nama_pegawai) + '.';
                    document.getElementById('btn-submit').textContent = 'Simpan';
                    form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                    openModal();
                });
            }

            document.querySelectorAll('[data-edit-modal]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.editModal;
                    form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                    try {
                        const res = await fetch(updateUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Gagal mengambil data');
                        const data = await res.json();
                        document.getElementById('field-id').value = data.id_aset;
                        document.getElementById('field-pegawai').value = data.id_pegawai ?? '';
                        document.getElementById('field-barang').value = data.nama_barang ?? '';
                        document.getElementById('field-kartu').value = data.nomor_kartu_barang ?? '';
                        document.getElementById('field-merk').value = data.merk ?? '';
                        document.getElementById('field-pengadaan').value = data.tanggal_pengadaan ?? '';
                        document.getElementById('field-perolehan').value = data.tanggal_perolehan ?? '';
                        document.getElementById('field-habis-pakai').value = data.tanggal_habis_pakai ?? '';
                        document.getElementById('field-nilai').value = data.nilai_perolehan ?? '';
                        document.getElementById('field-kondisi').value = data.kondisi ?? '';
                        document.getElementById('field-status').value = data.status_aset ?? '';
                        form.action = updateUrl.replace('__ID__', id);
                        document.getElementById('modal-title').textContent = 'Edit Aset';
                        document.getElementById('modal-subtitle').textContent = 'Perbarui data aset.';
                        document.getElementById('btn-submit').textContent = 'Perbarui';
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
                try {
                    const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body });
                    if (res.status === 422) {
                        const data = await res.json();
                        showErrors(data.errors);
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
                    const res = await fetch(updateUrl.replace('__ID__', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
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