@extends('layouts.app')

@section('title', 'Kendaraan')

@section('content')
    <x-page-header title="Kendaraan" subtitle="Data kendaraan dinas beserta pemegangnya">
        @if ($isAdminAset)
            <x-slot name="actions">
                <button type="button" id="btn-import" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4m-9 8H5a2 2 0 00-2 2v2a2 2 0 002 2h14a2 2 0 002-2v-2a2 2 0 00-2-2h-2"/></svg>
                    Import Excel
                </button>
                <button type="button" id="btn-tambah" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah Kendaraan
                </button>
            </x-slot>
        @endif
    </x-page-header>

    <x-alert type="success" />
    <x-alert type="error" />

    <x-card :padding="false">
        <form method="GET" action="{{ route('kendaraan.index') }}" class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nama, kartu, merk, rangka, mesin..."
                    autocomplete="off"
                    class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition"
                >
            </div>
            <div class="flex flex-wrap gap-3">
                <select name="status" class="block rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status') === 'nonaktif')>Non-Aktif</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                    Cari
                </button>
                @if (request('search') || request('status'))
                    <a href="{{ route('kendaraan.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Foto</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Kendaraan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plat Aktif</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pajak Jatuh Tempo</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang</th>
                        @if ($isAdminAset)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai</th>
                            <th scope="col" class="px-6 py-3 text-right whitespace-nowrap text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($kendaraanList as $index => $k)
                        @php
                            $platAktif = $k->platAktif;
                            $pajakAktif = $k->pajakAktif;
                            $isLate = $pajakAktif?->tanggal_berakhir ? $pajakAktif->tanggal_berakhir->lt(\Carbon\Carbon::today()) : false;
                            $pajakColor = $isLate ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700';
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $kendaraanList->firstItem() + $index }}</td>
                            <td class="whitespace-nowrap px-6 py-4">
                                @if ($k->foto)
                                    <img src="{{ asset('storage/' . $k->foto) }}" alt="Foto kendaraan" class="h-10 w-14 rounded-lg object-cover">
                                @else
                                    <span class="inline-flex h-10 w-14 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <a href="{{ route('kendaraan.show', $k->id_kendaraan) }}" class="font-medium text-emerald-600 hover:underline">
                                    {{ $k->aset?->barang?->nama_barang ?? 'Kendaraan' }}
                                </a>
                                <p class="text-xs text-slate-400">{{ $k->aset?->nomor_kartu_barang ?? '-' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $k->jenis_kendaraan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-700">{{ $platAktif->nomor_plat ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($pajakAktif?->tanggal_berakhir)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pajakColor }}">
                                        {{ $pajakAktif->tanggal_berakhir->format('d M Y') }}{{ $isLate ? ' (Terlambat)' : '' }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($k->pemegang)
                                    <span class="font-medium text-slate-700">{{ $k->pemegang }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            @if ($isAdminAset)
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $k->aset?->nilai_perolehan ? 'Rp ' . number_format((float) $k->aset->nilai_perolehan, 0, ',', '.') : '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-right text-sm">
                                    <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                        <a href="{{ route('kendaraan.show', $k->id_kendaraan) }}" title="Detail Kendaraan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-white text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <button type="button" data-delete-target="{{ $k->id_kendaraan }}" data-delete-name="{{ $k->aset?->barang?->nama_barang ?? 'Kendaraan' }}" title="Hapus Kendaraan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdminAset ? 9 : 7 }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada data kendaraan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($kendaraanList->hasPages())
            <div class="px-6 py-4">
                {{ $kendaraanList->links() }}
            </div>
        @endif
    </x-card>

    @if ($isAdminAset)
        <div id="import-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="fixed inset-0" data-import-close></div>
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Import Kendaraan dari Excel</h3>
                            <p class="mt-0.5 text-sm text-slate-500">Unggah file .xlsx / .csv untuk menambah banyak kendaraan sekaligus.</p>
                        </div>
                        <button type="button" data-import-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('kendaraan.import') }}" enctype="multipart/form-data" class="px-6 py-6">
                        @csrf
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">File Excel / CSV <span class="text-red-500">*</span></label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <p class="text-xs text-slate-400">Format kolom: Nama, No. Kartu, Jenis, No. Rangka, No. Mesin, Pemegang, Pajak Jatuh Tempo, Nominal/Total Pajak, No. Polisi, Masa Aktif. Maks 5MB.</p>
                            @error('file')
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="import-header-row" class="block text-sm font-medium text-slate-700">Baris Header</label>
                            <input type="number" id="import-header-row" name="header_row" value="2" min="1" max="50" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <p class="text-xs text-slate-400">Nomor baris tempat nama kolom berada (baris judul besar di atasnya dihitung). Urutan kolom bebas; nama header dikenali otomatis.</p>
                            @error('header_row')
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mt-6 flex items-center justify-end gap-2">
                            <button type="button" data-import-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">Import</button>
                        </div>
                    </form>
                </div>
        </div>

        <x-modal-confirm id="delete-modal"
                         title="Usulkan Penghapusan Kendaraan?"
                         message="Kendaraan akan masuk antrean usulan penghapusan dan hilang dari daftar aktif. Pembatalan dilakukan di halaman Penghapusan."
                         confirm-label="Usulkan Hapus"
                         confirm-variant="danger" />
    @endif

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const storeUrl = @json(route('kendaraan.store'));
        const editUrl = @json(route('kendaraan.edit', ['kendaraan' => '__ID__']));
        const updateUrl = @json(route('kendaraan.update', ['kendaraan' => '__ID__']));
        const createDataUrl = @json(route('kendaraan.create'));

        const modal = document.getElementById('kendaraan-modal');
        const form = document.getElementById('kendaraan-form');
        let loadedDropdowns = false;

        const importModal = document.getElementById('import-modal');
        if (document.getElementById('btn-import')) {
            document.getElementById('btn-import').addEventListener('click', () => {
                importModal.classList.remove('hidden');
                importModal.classList.add('flex');
            });
        }
        if (importModal) {
            importModal.querySelectorAll('[data-import-close]').forEach(el => el.addEventListener('click', () => {
                importModal.classList.add('hidden');
                importModal.classList.remove('flex');
            }));
        }

        function showErrors(errors) {
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            Object.keys(errors).forEach(field => {
                const err = form.querySelector('[data-error-for="' + field + '"]');
                if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
            });
        }

        function fillOptions(select, options, valueKey, labelKey, placeholder) {
            select.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = ''; opt.textContent = placeholder;
            select.appendChild(opt);
            options.forEach(o => {
                const el = document.createElement('option');
                el.value = o[valueKey]; el.textContent = o[labelKey];
                select.appendChild(el);
            });
        }

        function openModal() {
            modal.style.visibility = 'visible';
            document.body.classList.add('overflow-hidden');
        }
        function closeModal() {
            modal.style.visibility = 'hidden';
            document.body.classList.remove('overflow-hidden');
            form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
        }
        modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));

        async function ensureDropdowns() {
            if (loadedDropdowns) return;
            const res = await fetch(createDataUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const data = await res.json();
            fillOptions(document.getElementById('field-jenis'), data.jenisList.map(v => ({ value: v })), 'value', 'value', '-- Pilih Jenis --');
            fillOptions(document.getElementById('field-kondisi'), data.kondisiList.map(v => ({ value: v })), 'value', 'value', '-- Pilih Kondisi --');
            loadedDropdowns = true;
        }

        if (document.getElementById('btn-tambah')) {
            document.getElementById('btn-tambah').addEventListener('click', async () => {
                await ensureDropdowns();
                document.getElementById('modal-title').textContent = 'Tambah Kendaraan';
                form.reset();
                document.getElementById('field-id').value = '';
                document.getElementById('field-id-aset').value = '';
                form.action = storeUrl;
                openModal();
            });
        }

        document.querySelectorAll('[data-edit-target]').forEach(btn => {
            btn.addEventListener('click', async () => {
                await ensureDropdowns();
                const id = btn.dataset.editTarget;
                const res = await fetch(editUrl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                const d = await res.json();
                document.getElementById('modal-title').textContent = 'Edit Kendaraan';
                document.getElementById('field-id').value = d.id_kendaraan;
                document.getElementById('field-id-aset').value = d.id_aset;
                document.getElementById('field-nama').value = d.nama_kendaraan ?? '';
                document.getElementById('field-jenis').value = d.jenis_kendaraan ?? '';
                document.getElementById('field-pemegang').value = d.pemegang ?? '';
                document.getElementById('field-keterangan').value = d.keterangan ?? '';
                document.getElementById('field-kartu').value = d.nomor_kartu_barang ?? '';
                document.getElementById('field-plat').value = d.plat_nomor ?? '';
                document.getElementById('field-tgl-plat').value = d.plat_tanggal ?? '';
                document.getElementById('field-rangka').value = d.nomor_rangka ?? '';
                document.getElementById('field-mesin').value = d.nomor_mesin ?? '';
                document.getElementById('field-merk').value = d.merk ?? '';
                document.getElementById('field-tipe').value = d.tipe ?? '';
                document.getElementById('field-nilai').value = d.nilai_perolehan ?? '';
                document.getElementById('field-kondisi').value = d.kondisi ?? '';
                document.getElementById('field-status').value = d.status_aset ?? '';
                document.getElementById('field-tgl-pengadaan').value = d.tanggal_pengadaan ?? '';
                document.getElementById('field-tgl-perolehan').value = d.tanggal_perolehan ?? '';
                document.getElementById('field-tgl-habis').value = d.tanggal_habis_pakai ?? '';
                document.getElementById('field-pajak-tgl').value = d.pajak_tanggal_berakhir ?? '';
                document.getElementById('field-pajak-5thn').checked = !!d.pajak_max_tahunan;
                document.getElementById('field-pajak-nominal').value = d.pajak_nominal ?? '';
                document.getElementById('field-pajak-total').value = d.pajak_total ?? '';
                form.action = updateUrl.replace('__ID__', id);
                openModal();
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

        {{-- Delete handling --}}
        const deleteModal = document.getElementById('delete-modal');
        const deleteForm = deleteModal ? deleteModal.querySelector('form') : null;
        if (deleteModal) {
            deleteModal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', () => deleteModal.classList.add('hidden')));
        }
        document.querySelectorAll('[data-delete-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.deleteTarget;
                const name = btn.dataset.deleteName;
                if (!deleteModal) return;
                const msgEl = deleteModal.querySelector('[data-confirm-message]');
                if (msgEl) msgEl.textContent = 'Usulkan penghapusan kendaraan "' + name + '"? Kendaraan akan hilang dari daftar aktif hingga proses usulan selesai.';
                deleteForm.action = @json(route('kendaraan.destroy', ['kendaraan' => '__ID__'])).replace('__ID__', id);
                deleteModal.classList.remove('hidden');
                deleteModal.classList.add('flex');
            });
        });
        if (deleteForm) {
            deleteForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const res = await fetch(deleteForm.action, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Gagal mengajukan usulan penghapusan.');
                }
            });
        }
    </script>
    @endpush

    @if ($isAdminAset)
        @push('modals')
            @include('kendaraan._edit-modal')
        @endpush
    @endif
@endsection
