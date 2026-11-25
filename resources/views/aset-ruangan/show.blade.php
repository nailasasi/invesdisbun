@extends('layouts.app')

@section('title', 'Detail Aset Ruangan')

@section('page-title', 'Detail Aset Ruangan')

@section('content')
    {{-- Navigasi Kembali Minimalis di Atas Judul --}}
    <div class="mb-3">
        <a href="{{ route('aset-ruangan.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-900">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Aset Ruangan</span>
        </a>
    </div>

    {{-- Notifikasi Flash Pesan Error / Sukses --}}
    @if (session('error'))
        <div class="mb-4 flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800 shadow-xs">
            <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if (session('success'))
        <div class="mb-4 flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800 shadow-xs">
            <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Header Judul & Aksi Utama --}}
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $ruangan->nama_ruangan }}</h1>
            <p class="mt-1 text-sm text-slate-500">Aset yang ditempatkan di ruangan ini</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            @if ($bolehCetakDokumen)
            {{-- Cetak Label Ruangan (Admin Aset & Admin UPT) --}}
            <button type="button" id="btn-label-scope"
               class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-1.5 text-xs font-bold text-amber-700 shadow-2xs transition hover:bg-amber-100"
               title="Cetak label aset resmi dan/atau inventaris lokal di ruangan ini">
                <svg class="h-3.5 w-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.01"/></svg>
                <span>Cetak Label</span>
            </button>

            {{-- Cetak KIR (Admin Aset & Admin UPT) --}}
            <a href="{{ route('aset-ruangan.kir.download', $ruangan->id_ruangan) }}" id="btn-kir-scope"
               class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50/80 px-3 py-1.5 text-xs font-bold text-sky-700 shadow-2xs transition hover:bg-sky-100"
               title="{{ ($jumlahItemKIR ?? 0) === 0 ? 'Ruangan belum memiliki aset terfinalisasi/inventaris aktif untuk dicetak pada KIR' : 'Unduh Kartu Inventaris Ruangan (KIR)' }}">
                <svg class="h-3.5 w-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak KIR</span>
                @if (($jumlahItemKIR ?? 0) > 0)
                    <span class="ml-0.5 rounded-full bg-sky-200/70 px-1.5 py-0.2 text-[10px] text-sky-800 font-semibold">{{ $jumlahItemKIR }}</span>
                @endif
            </a>
            @endif

            {{-- Pasang Aset (Admin Aset only: existing master, tanpa pemegang) --}}
            @if ($isAdminAset && $adaKandidatPasang)
            <button type="button" id="btn-pasang"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-disbun-800"
                    title="Pasang master aset yang sudah ada ke ruangan ini">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span>Pasang Aset</span>
            </button>
            @endif
        </div>
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- Card Ringkasan Informasi Ruangan --}}
    <x-card class="mb-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Lantai</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $ruangan->lantai ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">SKPD</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $ruangan->skpd?->nama_skpd ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Barang Melekat</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ method_exists($asetMelekat, 'total') ? $asetMelekat->total() : $asetMelekat->count() }} aset</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Dipegang Pegawai</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $asetDipegang->count() }} aset</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Inventaris Lokal</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $inventarisLokal->count() }} item</p>
            </div>
        </div>
    </x-card>

    {{-- Tabel Barang Melekat pada Ruangan --}}
    @include('aset-ruangan.partials.tabel-barang-melekat')

    {{-- Tabel Inventaris Lokal Ruangan --}}
    @include('aset-ruangan.partials.tabel-inventaris-lokal')

    {{-- Tabel Barang Dipegang Pegawai --}}
    @include('aset-ruangan.partials.tabel-barang-dipegang')

    {{-- Modal-modal --}}
    @include('aset-ruangan.partials.modal-cetak-label')
    @include('aset-ruangan.partials.modal-inventaris')
    @include('aset-ruangan.partials.modal-mutasi-ruangan')
    @include('aset-ruangan.partials.modal-pasang-aset')
    @include('aset-ruangan.partials.modal-detach-aset')

    @push('scripts')
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const loginUrl = @json(route('login'));

            function openModal(el) {
                if (!el) return;
                el.classList.remove('hidden');
                el.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            }

            function closeModal(el) {
                if (!el) return;
                el.classList.add('hidden');
                el.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }

            function bindClose(modalEl, closeSelector) {
                if (!modalEl) return;
                modalEl.querySelectorAll(closeSelector).forEach(el => {
                    el.addEventListener('click', () => closeModal(modalEl));
                });
            }

            // ---- Cakupan Cetak Label Ruangan ----
            const labelScopeModal = document.getElementById('label-scope-modal');
            const labelScopeForm = document.getElementById('label-scope-form');
            const btnLabelScope = document.getElementById('btn-label-scope');
            const labelCetakAset = document.getElementById('label-cetak-aset');
            const labelCetakInventaris = document.getElementById('label-cetak-inventaris');
            const labelScopeError = document.getElementById('label-scope-error');

            if (btnLabelScope && labelScopeModal && labelScopeForm) {
                btnLabelScope.addEventListener('click', () => {
                    if (labelScopeError) labelScopeError.classList.add('hidden');
                    openModal(labelScopeModal);
                });
                bindClose(labelScopeModal, '[data-label-scope-close]');
                labelScopeForm.addEventListener('submit', (e) => {
                    if (!labelCetakAset?.checked && !labelCetakInventaris?.checked) {
                        e.preventDefault();
                        if (labelScopeError) labelScopeError.classList.remove('hidden');
                    }
                });
            }

            // ---- Inventaris Lokal (Kelola & Mutasi) ----
            const invStoreUrl = @json(route('aset-ruangan.inventaris.store', $ruangan->id_ruangan));
            const invUpdateUrl = @json(route('aset-ruangan.inventaris.update', ['ruangan' => $ruangan->id_ruangan, 'inventaris' => '__ID__']));
            const invMoveUrl = @json(route('aset-ruangan.inventaris.move', ['ruangan' => $ruangan->id_ruangan, 'inventaris' => '__ID__']));
            const invDelUrl = @json(route('aset-ruangan.inventaris.destroy', ['ruangan' => $ruangan->id_ruangan, 'inventaris' => '__ID__']));

            const invModal = document.getElementById('inv-modal');
            const invForm = document.getElementById('inv-form');
            const invMethod = document.getElementById('inv-method');
            const invTitle = document.getElementById('inv-modal-title');
            const invKodeInput = document.getElementById('inv-kode');
            const invKodeNote = document.getElementById('inv-kode-note');

            function clearInvErrors() {
                if (!invForm) return;
                invForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            }

            function showInvErrors(errors, form) {
                if (!form) return;
                form.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                Object.keys(errors || {}).forEach(field => {
                    const el = form.querySelector('[data-inv-error="' + field + '"]');
                    if (el) {
                        el.textContent = errors[field][0];
                        el.classList.remove('hidden');
                    }
                });
            }

            // Tambah Inventaris
            const btnAdd = document.getElementById('btn-inv-add');
            if (btnAdd && invForm && invModal) {
                btnAdd.addEventListener('click', () => {
                    invForm.reset();
                    invForm.action = invStoreUrl;
                    if (invMethod) invMethod.value = 'POST';
                    if (invTitle) invTitle.textContent = 'Tambah Inventaris';
                    if (invKodeInput) invKodeInput.value = '';
                    if (invKodeNote) invKodeNote.textContent = 'Kosongkan agar kode dibuat otomatis (contoh: RUA-INV-001).';
                    clearInvErrors();
                    openModal(invModal);
                });
            }

            // Ubah Inventaris
            document.querySelectorAll('[data-inv-edit]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!invForm || !invModal) return;
                    invForm.reset();
                    invForm.action = invUpdateUrl.replace('__ID__', btn.dataset.invEdit);
                    if (invMethod) invMethod.value = 'PUT';
                    if (invTitle) invTitle.textContent = 'Ubah Inventaris';
                    if (invKodeInput) invKodeInput.value = btn.dataset.invKode || '';
                    if (invKodeNote) invKodeNote.textContent = 'Kosongkan untuk mempertahankan kode saat ini.';
                    const elNama = document.getElementById('inv-nama');
                    const elMerk = document.getElementById('inv-merk');
                    const elKondisi = document.getElementById('inv-kondisi');
                    const elKeterangan = document.getElementById('inv-keterangan');
                    if (elNama) elNama.value = btn.dataset.invNama || '';
                    if (elMerk) elMerk.value = btn.dataset.invMerk || '';
                    if (elKondisi) elKondisi.value = btn.dataset.invKondisi || 'Baik';
                    if (elKeterangan) elKeterangan.value = btn.dataset.invKeterangan || '';
                    clearInvErrors();
                    openModal(invModal);
                });
            });

            if (invModal) bindClose(invModal, '[data-inv-close]');

            if (invForm) {
                invForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const kodeRaw = invKodeInput ? invKodeInput.value : '';
                    if (kodeRaw !== '' && kodeRaw.trim() === '') {
                        showInvErrors({ kode_inventaris: ['Kode inventaris tidak boleh hanya berisi spasi.'] }, invForm);
                        return;
                    }

                    const submit = document.getElementById('btn-inv-submit');
                    const original = submit ? submit.textContent : 'Simpan';
                    if (submit) {
                        submit.textContent = 'Menyimpan...';
                        submit.disabled = true;
                    }

                    try {
                        const res = await fetch(invForm.action, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: new FormData(invForm),
                        });
                        if (res.status === 422) {
                            const data = await res.json().catch(() => ({}));
                            showInvErrors(data.errors || {}, invForm);
                            if (submit) { submit.textContent = original; submit.disabled = false; }
                            return;
                        }
                        if (res.ok) { window.location.reload(); return; }
                        const data = await res.json().catch(() => ({}));
                        alert(data.message || 'Gagal menyimpan inventaris.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    } catch (err) {
                        alert('Koneksi bermasalah. Coba lagi.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    }
                });
            }

            // Mutasi Ruangan (Inventaris Lokal)
            const moveModal = document.getElementById('inv-move-modal');
            const moveForm = document.getElementById('inv-move-form');
            document.querySelectorAll('[data-inv-move]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!moveForm || !moveModal) return;
                    moveForm.reset();
                    moveForm.action = invMoveUrl.replace('__ID__', btn.dataset.invMove);
                    const tujuan = document.getElementById('inv-tujuan');
                    if (tujuan) tujuan.value = '';
                    const aset = document.getElementById('inv-move-aset');
                    if (aset) aset.textContent = btn.dataset.invNama || '-';
                    const kib = document.getElementById('inv-move-kib');
                    if (kib) kib.textContent = btn.dataset.invKode || '-';
                    const keterangan = document.getElementById('inv-move-keterangan');
                    if (keterangan) keterangan.value = btn.dataset.invKeterangan || '';
                    moveForm.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
                    openModal(moveModal);
                });
            });
            if (moveModal) bindClose(moveModal, '[data-inv-move-close]');

            if (moveForm) {
                moveForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const submit = document.getElementById('btn-inv-move-submit');
                    const original = submit ? submit.textContent : 'Pindahkan';
                    if (submit) {
                        submit.textContent = 'Memindahkan...';
                        submit.disabled = true;
                    }
                    try {
                        const res = await fetch(moveForm.action, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: new FormData(moveForm),
                        });
                        if (res.status === 422) {
                            const data = await res.json().catch(() => ({}));
                            moveForm.querySelectorAll('.field-error').forEach(el => el.classList.add('hidden'));
                            const el = moveForm.querySelector('[data-inv-error="id_ruangan_tujuan"]');
                            if (el) {
                                el.textContent = (data.errors && data.errors.id_ruangan_tujuan ? data.errors.id_ruangan_tujuan[0] : (data.message || 'Ruangan tujuan tidak valid.'));
                                el.classList.remove('hidden');
                            }
                            if (submit) { submit.textContent = original; submit.disabled = false; }
                            return;
                        }
                        if (res.ok) { window.location.reload(); return; }
                        const data = await res.json().catch(() => ({}));
                        alert(data.message || 'Gagal memindahkan inventaris.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    } catch (err) {
                        alert('Koneksi bermasalah. Coba lagi.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    }
                });
            }

            // Hapus Inventaris Lokal
            const delModal = document.getElementById('inv-del-modal');
            const delForm = document.getElementById('inv-del-form');
            document.querySelectorAll('[data-inv-del]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!delForm || !delModal) return;
                    delForm.action = invDelUrl.replace('__ID__', btn.dataset.invDel);
                    const elName = document.getElementById('inv-del-name');
                    if (elName) elName.textContent = btn.dataset.invNama || '';
                    openModal(delModal);
                });
            });
            if (delModal) bindClose(delModal, '[data-inv-del-close]');

            // ---- Keluarkan Aset (Admin Aset & Admin UPT) ----
            const detachModal = document.getElementById('detach-modal');
            const detachForm = document.getElementById('detach-form');
            const detachUrl = @json(route('aset-ruangan.aset.detach', ['ruangan' => $ruangan->id_ruangan, 'aset' => '__ID__']));

            document.querySelectorAll('[data-detach-target]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!detachForm || !detachModal) return;
                    detachForm.action = detachUrl.replace('__ID__', btn.dataset.detachTarget);
                    const elName = document.getElementById('detach-name');
                    if (elName) elName.textContent = btn.dataset.detachName || '';
                    openModal(detachModal);
                });
            });
            if (detachModal) bindClose(detachModal, '[data-detach-close]');

            // ---- Pasang Aset ke Ruangan (Admin Aset Only) ----
            const pasangModal = document.getElementById('pasang-modal');
            const pasangForm = document.getElementById('pasang-form');
            const btnPasang = document.getElementById('btn-pasang');
            const pasangKandidatUrl = @json(route('aset-ruangan.aset.pasang.kandidat', ['ruangan' => $ruangan->id_ruangan]));
            const pasangMassalUrl = @json(route('aset-ruangan.aset.pasang-massal', ['ruangan' => $ruangan->id_ruangan]));
            const jenisSelect = document.getElementById('field-jenis-select');
            const jenisSearch = document.getElementById('field-jenis-search');
            const jumlahInput = document.getElementById('field-jumlah');
            const pasangTersedia = document.getElementById('pasang-tersedia');
            const pasangRingkasan = document.getElementById('pasang-ringkasan');
            const ringkasanJenis = document.getElementById('ringkasan-jenis');
            const ringkasanJumlah = document.getElementById('ringkasan-jumlah');

            let pasangKandidat = [];

            function clearPasangErrors() {
                if (!pasangForm) return;
                pasangForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
            }

            function showPasangErrors(errors) {
                if (!pasangForm) return;
                pasangForm.querySelectorAll('.field-error').forEach(e => e.classList.add('hidden'));
                Object.keys(errors || {}).forEach(field => {
                    const err = pasangForm.querySelector('[data-error-for="' + field + '"]');
                    if (err) { err.textContent = errors[field][0]; err.classList.remove('hidden'); }
                });
            }

            function filterJenisOptions() {
                if (!jenisSelect) return;
                const term = jenisSearch ? jenisSearch.value.toLowerCase().trim() : '';
                Array.from(jenisSelect.options).forEach(opt => {
                    if (opt.disabled && !opt.value) return;
                    opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                });
            }

            if (jenisSearch && jenisSelect) {
                jenisSearch.addEventListener('input', filterJenisOptions);
            }

            function refreshRingkasan() {
                const opt = jenisSelect && jenisSelect.selectedIndex >= 0 ? jenisSelect.options[jenisSelect.selectedIndex] : null;
                const idBarang = (opt && opt.value) ? opt.value : '';
                const ditemukan = pasangKandidat.find(k => String(k.id_barang) === String(idBarang));
                const tersedia = ditemukan ? ditemukan.tersedia : 0;

                if (pasangTersedia) pasangTersedia.textContent = tersedia;
                if (jumlahInput && tersedia > 0) jumlahInput.max = tersedia;

                const jumlah = jumlahInput && parseInt(jumlahInput.value, 10) > 0 ? parseInt(jumlahInput.value, 10) : 0;

                if (pasangRingkasan && jumlah > 0 && opt && opt.value) {
                    pasangRingkasan.classList.remove('hidden');
                    ringkasanJenis.textContent = opt.dataset.nama || opt.textContent;
                    ringkasanJumlah.textContent = jumlah;
                } else if (pasangRingkasan) {
                    pasangRingkasan.classList.add('hidden');
                }
            }

            async function muatKandidat() {
                if (!jenisSelect) return;
                try {
                    const res = await fetch(pasangKandidatUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    const data = await res.json().catch(() => ({}));
                    pasangKandidat = data.kandidat || [];

                    const current = jenisSelect.value;
                    jenisSelect.innerHTML = '';
                    if (pasangKandidat.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.disabled = true;
                        opt.textContent = '-- Tidak ada barang yang dapat dipasang --';
                        jenisSelect.appendChild(opt);
                    } else {
                        pasangKandidat.forEach(k => {
                            const opt = document.createElement('option');
                            opt.value = k.id_barang;
                            opt.textContent = k.nama_barang + ' (' + k.tersedia + ' tersedia)';
                            opt.setAttribute('data-nama', k.nama_barang);
                            opt.className = 'py-1 px-2 cursor-pointer hover:bg-slate-100';
                            jenisSelect.appendChild(opt);
                        });
                    }

                    if (current) {
                        jenisSelect.value = current;
                    }

                    filterJenisOptions();
                    refreshRingkasan();
                } catch (err) {
                    pasangKandidat = [];
                }
            }

            function openPasangModal() {
                if (!pasangModal) return;
                openModal(pasangModal);
                clearPasangErrors();
                if (jenisSearch) {
                    jenisSearch.value = '';
                }
                if (jenisSelect) {
                    jenisSelect.value = '';
                    Array.from(jenisSelect.options).forEach(o => o.style.display = '');
                }
                if (jumlahInput) jumlahInput.value = '';
                refreshRingkasan();
                muatKandidat();
                setTimeout(() => {
                    if (jenisSearch) jenisSearch.focus();
                }, 50);
            }

            function closePasangModal() {
                if (!pasangModal) return;
                closeModal(pasangModal);
                clearPasangErrors();
            }

            if (btnPasang && pasangModal) {
                btnPasang.addEventListener('click', openPasangModal);
                bindClose(pasangModal, '[data-pasang-close]');
                muatKandidat();
            }

            if (jenisSelect) {
                jenisSelect.addEventListener('change', refreshRingkasan);
                jenisSelect.addEventListener('click', refreshRingkasan);
            }
            if (jumlahInput) jumlahInput.addEventListener('input', refreshRingkasan);

            if (pasangForm) {
                pasangForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const submit = document.getElementById('btn-pasang-submit');
                    const original = submit ? submit.textContent : 'Pasang';

                    const idBarang = jenisSelect ? jenisSelect.value : '';
                    const jumlah = jumlahInput ? parseInt(jumlahInput.value, 10) : 0;

                    if (!idBarang) {
                        showPasangErrors({ id_barang: ['Pilih jenis barang terlebih dahulu.'] });
                        return;
                    }
                    if (!jumlah || jumlah < 1) {
                        showPasangErrors({ jumlah: ['Jumlah minimal 1.'] });
                        return;
                    }

                    if (submit) {
                        submit.textContent = 'Memasang...';
                        submit.disabled = true;
                    }
                    try {
                        const body = new FormData();
                        body.append('id_barang', idBarang);
                        body.append('jumlah', jumlah);

                        const res = await fetch(pasangMassalUrl, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body,
                        });

                        if (res.status === 422) {
                            const data = await res.json().catch(() => ({}));
                            showPasangErrors(data.errors || {});
                            if (submit) { submit.textContent = original; submit.disabled = false; }
                            return;
                        }

                        const data = await res.json().catch(() => ({}));

                        if (res.ok) {
                            window.location.reload();
                            return;
                        }

                        alert(data.message || 'Gagal memasang aset.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    } catch (err) {
                        alert('Koneksi bermasalah. Coba lagi.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    }
                });
            }

            // ---- Mutasi Ruangan (Aset Melekat - Admin Aset Only) ----
            const mutasiRuanganModal = document.getElementById('mutasi-ruangan-modal');
            const mutasiRuanganForm = document.getElementById('mutasi-ruangan-form');
            const mutasiRuanganUrl = @json(route('aset-ruangan.aset.mutasi-ruangan', ['ruangan' => $ruangan->id_ruangan, 'aset' => '__ID__']));
            const mutasiRuanganAset = document.getElementById('mutasi-ruangan-aset');
            const mutasiRuanganKib = document.getElementById('mutasi-ruangan-kib');

            document.querySelectorAll('[data-mutasi-ruangan]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!mutasiRuanganModal || !mutasiRuanganForm) return;
                    mutasiRuanganForm.action = mutasiRuanganUrl.replace('__ID__', btn.getAttribute('data-mutasi-ruangan'));
                    if (mutasiRuanganAset) mutasiRuanganAset.textContent = btn.getAttribute('data-aset-nama') || '-';
                    if (mutasiRuanganKib) mutasiRuanganKib.textContent = btn.getAttribute('data-aset-kib') || '-';
                    const elTujuan = document.getElementById('mutasi-ruangan-tujuan');
                    const elKet = document.getElementById('mutasi-ruangan-keterangan');
                    if (elTujuan) elTujuan.value = '';
                    if (elKet) elKet.value = '';
                    openModal(mutasiRuanganModal);
                });
            });
            if (mutasiRuanganModal) bindClose(mutasiRuanganModal, '[data-mutasi-ruangan-close]');

            if (mutasiRuanganForm) {
                mutasiRuanganForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const submit = document.getElementById('btn-mutasi-ruangan-submit');
                    const original = submit ? submit.textContent : 'Pindahkan';
                    if (submit) {
                        submit.textContent = 'Memindahkan...';
                        submit.disabled = true;
                    }

                    try {
                        const res = await fetch(mutasiRuanganForm.action, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: new FormData(mutasiRuanganForm),
                        });

                        if (res.status === 422) {
                            const data = await res.json().catch(() => ({}));
                            const first = data.errors ? (Object.values(data.errors)[0]?.[0] || 'Validasi gagal.') : 'Validasi gagal.';
                            alert(first);
                            if (submit) { submit.textContent = original; submit.disabled = false; }
                            return;
                        }

                        const data = await res.json().catch(() => ({}));

                        if (res.ok) {
                            window.location.reload();
                            return;
                        }

                        if (res.status === 401 || res.status === 419) {
                            alert(data.message || 'Sesi Anda telah berakhir. Silakan masuk kembali.');
                            window.location.href = loginUrl;
                            return;
                        }

                        if (res.status === 403) {
                            alert(data.message || 'Anda tidak memiliki hak akses untuk memindahkan aset ini.');
                            if (submit) { submit.textContent = original; submit.disabled = false; }
                            return;
                        }

                        if (res.status === 404) {
                            alert(data.message || 'Endpoint mutasi tidak ditemukan. Muat ulang halaman lalu coba lagi.');
                            if (submit) { submit.textContent = original; submit.disabled = false; }
                            return;
                        }

                        if (res.status >= 500) {
                            alert(data.message || 'Terjadi kesalahan di server saat memindahkan aset. Silakan coba lagi.');
                            if (submit) { submit.textContent = original; submit.disabled = false; }
                            return;
                        }

                        alert(data.message || 'Gagal memindahkan aset.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    } catch (err) {
                        console.error('Mutasi Ruangan gagal:', err);
                        const pesanJaringan = (err instanceof TypeError) && /fetch|network|Failed to fetch/i.test(err.message || '');
                        alert(pesanJaringan
                            ? 'Koneksi bermasalah. Coba lagi.'
                            : 'Terjadi kesalahan pada halaman ini. Muat ulang halaman (Ctrl+F5) lalu coba lagi.');
                        if (submit) { submit.textContent = original; submit.disabled = false; }
                    }
                });
            }
        })();
    </script>
    @endpush
@endsection
