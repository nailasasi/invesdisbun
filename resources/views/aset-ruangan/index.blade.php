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
            <div class="relative flex-1 sm:max-w-sm">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nama ruangan..."
                    autocomplete="off"
                    class="block w-full rounded-xl border border-slate-300 py-2 pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition"
                >
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                Cari
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah Aset</th>
                        <th scope="col" class="px-6 py-3 text-right whitespace-nowrap text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($ruangan->status === 'Nonaktif')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Nonaktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    {{ $ruangan->jumlah_aset }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-right text-sm">
                                <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                    <a href="{{ route('aset-ruangan.show', $ruangan->id_ruangan) }}" title="Lihat Inventaris Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-white text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if ($isAdminAset)
                                        <button type="button" data-edit-modal="{{ $editJson }}" title="Edit Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition hover:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('aset-ruangan.toggle-status', $ruangan->id_ruangan) }}" onsubmit="return confirm('Ubah status ruangan ini?')">
                                            @csrf
                                            @if ($ruangan->status === 'Nonaktif')
                                                <button type="submit" title="Aktifkan Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </button>
                                            @else
                                                <button type="submit" title="Nonaktifkan Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-amber-50 hover:text-amber-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                                                </button>
                                            @endif
                                        </form>
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
    <div id="ruangan-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-modal-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
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

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const storeUrl = @json(route('aset-ruangan.store'));
        const updateUrl = @json(route('aset-ruangan.update', ['ruangan' => '__ID__']));

        const modal = document.getElementById('ruangan-modal');
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

        modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
    </script>
    @endpush
    @endif
@endsection
